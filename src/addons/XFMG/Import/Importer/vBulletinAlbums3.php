<?php

namespace XFMG\Import\Importer;

use XF\Import\Data\EntityEmulator;
use XF\Import\StepState;

class vBulletinAlbums3 extends AbstractMGImporter
{
	use vBulletinSourceTrait;

	protected $importableTypes = [
		'gif', 'jpg', 'jpeg', 'jpe', 'png'
	];

	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'vBulletin Albums 3.8',
		];
	}

	protected function getAttachPathConfig(array &$baseConfig, \XF\Db\Mysqli\Adapter $sourceDb)
	{
		try
		{
			$options = $sourceDb->fetchPairs("
				SELECT varname, value
				FROM setting
				WHERE varname IN('album_dataloc', 'album_picpath')
			");
		}
		catch (\XF\Db\Exception $e) {}

		if (!empty($options))
		{
			if (!empty($options['album_picpath']) && $options['album_dataloc'] != 'db')
			{
				$baseConfig['attachpath'] = trim($options['album_picpath']);
			}
		}
	}

	public function renderStepConfigOptions(array $vars)
	{
		$vars['stepConfig'] = $this->getStepConfigDefault();
		return $this->app->templater()->renderTemplate('admin:xfmg_import_step_config_vb_albums', $vars);
	}

	public function getSteps()
	{
		return [
			'albums' => [
				'title' => \XF::phrase('xfmg_albums')
			],
			'mediaItems' => [
				'title' => \XF::phrase('xfmg_media_items'),
				'depends' => ['albums']
			],
			'comments' => [
				'title' => \XF::phrase('xfmg_comments'),
				'depends' => ['albums', 'mediaItems']
			]
		];
	}

	// ############################## STEP: ALBUMS #########################

	public function getStepEndAlbums()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(albumid) FROM album") ?: 0;
	}

	public function stepAlbums(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$albums = $this->sourceDb->fetchAll("
			SELECT
				a.*,
				u.username
			FROM album AS a
			LEFT JOIN user AS u ON (a.userid = u.userid)
			WHERE a.albumid > ? AND a.albumid <= ?
			ORDER BY a.albumid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$albums)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($albums, 'userid');
		$this->lookup('user', $mapUserIds);

		$stringFormatter = $this->app->stringFormatter();

		foreach ($albums AS $album)
		{
			$oldId = $album['albumid'];
			$state->startAfter = $oldId;

			/** @var \XFMG\Import\Data\Album $albumImport */
			$albumImport = $this->newHandler('XFMG:Album');

			$albumImport->bulkSet($this->mapXfKeys($album, [
				'create_date' => 'createdate',
				'username',
				'media_count' => 'visible'
			]));

			$title = $stringFormatter->stripBbCode($album['title'], [
				'stripQuote' => true,
				'hideUnviewable' => false
			]);
			$albumImport->set('title', $title, [EntityEmulator::UNHTML_ENTITIES => true]);

			$description = $stringFormatter->stripBbCode($album['description'], [
				'stripQuote' => true,
				'hideUnviewable' => false
			]);
			$albumImport->set('description', $description, [EntityEmulator::UNHTML_ENTITIES => true]);

			$albumImport->user_id = $this->lookupId('user', $album['userid']);
			$albumImport->view_privacy = ($album['state'] == 'public' ? 'public' : 'private');

			$newId = $albumImport->save($oldId);
			if ($newId)
			{
				$state->imported++;
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $state->resumeIfNeeded();
	}

	// ############################## STEP: MEDIA ITEMS #########################

	public function getStepEndMediaItems()
	{
		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		return $this->sourceDb->fetchOne("
			SELECT 
				MAX(ap.pictureid)
			FROM albumpicture AS ap
			LEFT JOIN picture AS p ON (ap.pictureid = p.pictureid)
			WHERE p.extension IN($extensionsQuoted) AND p.state = 'visible'
		") ?: 0;
	}

	protected function getStepDataMediaItems(StepState $state, $limit = 500)
	{
		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		return $this->sourceDb->fetchAll("
			SELECT
				ap.*, 
				p.*,
				u.*,
				a.*,
				ap.pictureid
			FROM albumpicture AS ap
			LEFT JOIN picture AS p ON (ap.pictureid = p.pictureid)
			LEFT JOIN user AS u ON (p.userid = u.userid)
			LEFT JOIN album AS a ON (ap.albumid = a.albumid)
			WHERE ap.pictureid > ? AND ap.pictureid <= ? AND p.extension IN($extensionsQuoted) AND p.state = 'visible'
			ORDER BY ap.pictureid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
	}

	protected function getSourceFilePath(array $mediaItem, array $stepConfig)
	{
		return sprintf(
			'%s/%d/%d.picture',
			$stepConfig['path'],
			floor($mediaItem['pictureid'] / 1000),
			$mediaItem['pictureid']
		);
	}

	public function stepMediaItems(StepState $state, array $stepConfig, $maxTime)
	{
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->getStepDataMediaItems($state);

		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapAlbumIds = [];
		foreach ($mediaItems AS $mediaItem)
		{
			$mapUserIds[] = $mediaItem['userid'];
			$mapAlbumIds[] = $mediaItem['albumid'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_album', $mapAlbumIds);

		$stringFormatter = $this->app->stringFormatter();

		foreach ($mediaItems AS $mediaItem)
		{
			$oldId = $mediaItem['pictureid'];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $mediaItem['userid'], 0);
			$albumId = $this->lookupId('xfmg_album', $mediaItem['albumid']);

			if (!$albumId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\MediaItem $mediaImport */
			$mediaImport = $this->newHandler('XFMG:MediaItem');

			$mediaImport->bulkSet($this->mapXfKeys($mediaItem, [
				'media_date' => 'dateline',
				'username'
			]));
			$mediaImport->bulkSet([
				'album_id' => $albumId,
				'user_id' => $userId,
				'media_type' => 'image'
			]);

			$title = $stringFormatter->stripBbCode($mediaItem['caption'], [
				'stripQuote' => true,
				'hideUnviewable' => false
			]);
			$mediaImport->set('title', $title, [EntityEmulator::UNHTML_ENTITIES => true]);

			$sourceFile = null;

			if ($mediaItem['filedata'])
			{
				$sourceFile = \XF\Util\File::getTempFile();
				\XF\Util\File::writeFile($sourceFile, $mediaItem['filedata']);
			}
			if (!$sourceFile)
			{
				$path = $this->getSourceFilePath($mediaItem, $stepConfig);
				if (file_exists($path) && is_readable($path))
				{
					$sourceFile = $path;
				}
			}

			if (!$sourceFile)
			{
				continue;
			}

			/** @var \XF\Import\Data\Attachment $attachmentImport */
			$attachmentImport = $this->newHandler('XF:Attachment');

			$attachmentImport->attach_date = $mediaItem['dateline'];
			$attachmentImport->content_type = 'xfmg_media';
			$attachmentImport->unassociated = false;

			$filename = isset($mediaItem['filename']) ? $mediaItem['filename'] : "$mediaItem[pictureid].$mediaItem[extension]";

			$attachmentImport->setDataExtra('upload_date', $mediaItem['dateline']);
			$attachmentImport->setDataUserId($userId);
			$attachmentImport->setSourceFile($sourceFile, $filename);

			$mediaImport->addAttachment($attachmentImport);

			$newId = $mediaImport->save($oldId);
			if ($newId)
			{
				$state->imported++;
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		\XF\Util\File::cleanUpTempFiles();

		return $state->resumeIfNeeded();
	}

	// ############################## STEP: COMMENTS #########################

	public function getStepEndComments()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(commentid) FROM picturecomment") ?: 0;
	}

	protected function getStepDataComments(StepState $state, $limit = 500)
	{
		return $this->sourceDb->fetchAll("
			SELECT
				c.*,
				u.*,
				IF(u.username IS NULL, c.postusername, u.username) AS username
			FROM picturecomment AS c
			LEFT JOIN user AS u ON (c.postuserid = u.userid)
			WHERE
				c.commentid > ? AND c.commentid <= ?
			ORDER BY
				c.commentid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
	}

	public function stepComments(StepState $state, array $stepConfig, $maxTime)
	{
		$timer = new \XF\Timer($maxTime);

		$comments = $this->getStepDataComments($state);

		if (!$comments)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapMediaIds = [];

		foreach ($comments AS $comment)
		{
			$mapUserIds[] = $comment['postuserid'];
			$mapMediaIds[] = $comment['pictureid'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($comments AS $comment)
		{
			$oldId = $comment['commentid'];
			$state->startAfter = $oldId;

			$mediaId = $this->lookupId('xfmg_media', $comment['pictureid']);
			$userId = $this->lookupId('user', $comment['postuserid']);

			if (!$mediaId || !$userId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Comment $import */
			$import = $this->newHandler('XFMG:Comment');

			$import->bulkSet($this->mapXfKeys($comment, [
				'username',
				'comment_date' => 'dateline'
			]));

			$message = $comment['pagetext'];
			if ($comment['title'])
			{
				$message = $comment['title'] . "\n\n" . $message;
			}

			if ($comment['state'] == 'moderation')
			{
				$comment['state'] = 'moderated';
			}

			$import->bulkSet([
				'username' => $comment['username'],
				'message' => $message,
				'content_id' => $mediaId,
				'content_type' => 'xfmg_media',
				'user_id' => $userId,
				'comment_state' => $comment['state']
			]);

			if ($comment['ipaddress'] && is_int($comment['ipaddress']))
			{
				$import->setLoggedIp(long2ip($comment['ipaddress']));
			}

			$newId = $import->save($oldId);
			if ($newId)
			{
				$state->imported++;
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $state->resumeIfNeeded();
	}
}