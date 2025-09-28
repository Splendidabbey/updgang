<?php

namespace XFMG\Import\Importer;

use XF\Import\StepState;
use XF\Util\Arr;
use XF\Util\File;

class PhotopostPro extends AbstractMGImporter
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'Photopost Pro 8.x',
		];
	}

	protected function getBaseConfigDefault()
	{
		return [
			'db'         => [
				'host'     => '',
				'username' => '',
				'password' => '',
				'dbname'   => '',
				'port'     => 3306,
				'tablePrefix'   => '',
				'charset'  => '', // used for the DB connection
			],
			'datafull'    => null,
			'integration' => null,
			'attempt_match' => false,
			'import_log_path' => null
		];
	}

	public function renderBaseConfigOptions(array $vars)
	{
		if (empty($vars['fullConfig']['db']['host']))
		{
			$configPath = getcwd() . '/gallery/config-inc.php';
			if (file_exists($configPath) && is_readable($configPath))
			{
				// config file attempts to include header by defaukt
				// setting this variable prevents that
				$skip_exheader = true;

				/**
				 * @var string $host
				 * @var string $sqlport
				 * @var string $mysql_user,
				 * @var string $mysql_password,
				 * @var string $database
				 * @var string $pp_db_prefix
				 * @var string $ppcharset
				 */
				include($configPath);

				$vars['db'] = [
					'host'        => $host,
					'port'        => $sqlport,
					'username'    => $mysql_user,
					'password'    => $mysql_password,
					'dbname'      => $database,
					'tablePrefix' => $pp_db_prefix,
					'charset'     => $ppcharset
				];
			}
			else
			{
				$vars['db'] = [
					'host' => $this->app->config['db']['host'],
					'port' => $this->app->config['db']['port'],
					'username' => $this->app->config['db']['username']
				];
			}
		}

		return $this->app->templater()->renderTemplate('admin:xfmg_import_config_photopost', $vars);
	}

	public function validateBaseConfig(array &$baseConfig, array &$errors)
	{
		$baseConfig['db']['tablePrefix'] = preg_replace('/[^a-z0-9_]/i', '', $baseConfig['db']['tablePrefix']);

		$fullConfig = array_replace_recursive($this->getBaseConfigDefault(), $baseConfig);
		$missingFields = false;

		if ($fullConfig['db']['host'])
		{
			$validDbConnection = false;

			try
			{
				$sourceDb = new \XF\Db\Mysqli\Adapter($fullConfig['db'], false);
				$sourceDb->getConnection();
				$validDbConnection = true;
			}
			catch (\XF\Db\Exception $e)
			{
				$errors[] = \XF::phrase('source_database_connection_details_not_correct_x', ['message' => $e->getMessage()]);
			}

			if ($validDbConnection)
			{
				try
				{
					$datafull = $sourceDb->fetchOne("
						SELECT setting
						FROM settings
						WHERE varname = 'datafull'
					");
				}
				catch (\XF\Db\Exception $e)
				{
					if ($fullConfig['db']['dbname'] === '')
					{
						$errors[] = \XF::phrase('please_enter_database_name');
					}
					else
					{
						$errors[] = \XF::phrase('table_prefix_or_database_name_is_not_correct');
					}
				}

				if (!$fullConfig['integration'])
				{
					$errors[] = \XF::phrase('xfmg_photopost_integration_source_must_be_configured');
				}

				$supportsForumLog = false;
				$forumLogRequired = false;
				if ($fullConfig['integration'] == 'forum' || $fullConfig['integration'] == 'xenforo')
				{
					$supportsForumLog = true;
					$forumLogRequired = $fullConfig['integration'] == 'forum';
				}

				if ($supportsForumLog)
				{
					if ($fullConfig['forum_import_log'])
					{
						$logExists = $this->app->db()->getSchemaManager()->tableExists($fullConfig['forum_import_log']);
						if (!$logExists)
						{
							$errors[] = \XF::phrase('forum_import_log_cannot_be_found');
						}
					}
					else if ($forumLogRequired)
					{
						$missingFields = true;
					}
				}

				$baseConfig['datafull'] = trim($datafull);
			}
			else
			{
				$missingFields = true;
			}
		}

		if ($missingFields)
		{
			$errors[] = \XF::phrase('please_complete_required_fields');
		}

		return $errors ? false : true;
	}

	protected function getStepConfigDefault()
	{
		return [
			'mediaItems' => [
				'path' => $this->baseConfig['datafull']
			]
		];
	}

	public function renderStepConfigOptions(array $vars)
	{
		$vars['stepConfig'] = $this->getStepConfigDefault();
		return $this->app->templater()->renderTemplate('admin:xfmg_import_step_config_photopost', $vars);
	}

	public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
	{
		$path = realpath(trim($stepConfig['mediaItems']['path']));

		if (!file_exists($path) || !is_dir($path) || !is_readable($path))
		{
			$errors['datafull'] = \XF::phrase('directory_specified_as_x_y_not_found_is_not_readable', [
				'type' => 'datafull',
				'dir'  => $stepConfig['mediaItems']['path']
			]);
		}

		$config['mediaItems']['path'] = $path;

		return $errors ? false : true;
	}

	public function getSteps()
	{
		return [
			'albums' => [
				'title' => \XF::phrase('xfmg_albums')
			],
			'categories' => [
				'title' => \XF::phrase('xfmg_categories')
			],
			'mediaItems' => [
				'title' => \XF::phrase('xfmg_media_items'),
				'depends' => ['albums', 'categories']
			],
			'ratings' => [
				'title' => \XF::phrase('xfmg_ratings'),
				'depends' => ['mediaItems']
			],
			'comments' => [
				'title' => \XF::phrase('xfmg_comments'),
				'depends' => ['mediaItems']
			],
			'contentTags' => [
				'title' => \XF::phrase('tags'),
				'depends' => ['mediaItems']
			]
		];
	}

	protected function doInitializeSource()
	{
		$this->sourceDb = new \XF\Db\Mysqli\Adapter($this->baseConfig['db'], false);

		if ($this->baseConfig['forum_import_log'])
		{
			$this->forumLog = new \XF\Import\Log(
				$this->app->db(), $this->baseConfig['forum_import_log']
			);
		}
	}

	// ############################## STEP: ALBUMS #########################

	public function getStepEndAlbums()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(id) 
			FROM categories
			WHERE cattype = 'a'
		") ?: 0;
	}

	public function stepAlbums(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$albums = $this->sourceDb->fetchAll("
			SELECT a.*, u.username, u.email
			FROM categories AS a
			LEFT JOIN users AS u ON (a.parent = u.userid)
			WHERE a.id > ? AND a.id <= ? AND a.cattype = 'a'
			ORDER BY a.id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$albums)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($albums, 'parent');
		$this->preparePhotopostUserIdMap($mapUserIds);

		foreach ($albums AS $album)
		{
			$oldId = $album['id'];
			$state->startAfter = $oldId;

			/** @var \XFMG\Import\Data\Album $albumImport */
			$albumImport = $this->newHandler('XFMG:Album');

			$userId = $this->lookupPhotopostUserId(
				$album['parent'], $album['username'], $album['email']
			);

			$createDate = $this->sourceDb->fetchOne("
				SELECT `date`
				FROM photos
				WHERE cat = ?
				ORDER BY id ASC
				LIMIT 1
			", $oldId) ?: time();

			$albumImport->bulkSet($this->mapXfKeys($album, [
				'title' => 'catname',
				'description',
				'username',
				'media_count' => 'photos'
			]));

			$albumImport->user_id = $userId;
			$albumImport->create_date = $createDate;
			$albumImport->view_privacy = $album['private'] == 'yes' ? 'private' : 'public';

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

	// ############################## STEP: CATEGORIES #########################

	public function stepCategories(StepState $state)
	{
		$categories = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM categories
			WHERE cattype = 'c'
			ORDER BY id
		", 'id');

		$categoryTreeMap = [];
		foreach ($categories AS $categoryId => $category)
		{
			$categoryTreeMap[$category['parent']][] = $categoryId;
		}

		$state->imported = $this->importCategoryTree($categories, $categoryTreeMap);

		return $state->complete();
	}

	protected function importCategoryTree(array $categories, array $tree, $oldParentId = 0, $newParentId = 0)
	{
		if (!isset($tree[$oldParentId]))
		{
			return 0;
		}

		$total = 0;

		foreach ($tree[$oldParentId] AS $oldCategoryId)
		{
			$category = $categories[$oldCategoryId];

			/** @var \XFMG\Import\Data\Category $categoryImport */
			$categoryImport = $this->newHandler('XFMG:Category');

			$categoryImport->bulkSet($this->mapXfKeys($category, [
				'title' => 'catname',
				'description',
				'display_order' => 'catorder',
				'media_count' => 'photos'
			]));

			$categoryImport->parent_category_id = $newParentId;

			if ($category['ismember'] || $oldCategoryId == 500)
			{
				$categoryImport->category_type = 'album';
			}
			else
			{
				$categoryImport->category_type = 'media';
			}

			$newCategoryId = $categoryImport->save($oldCategoryId);
			if ($newCategoryId)
			{
				$total++;
				$total += $this->importCategoryTree($categories, $tree, $oldCategoryId, $newCategoryId);
			}
		}

		return $total;
	}

	// ############################## STEP: MEDIA ITEMS #########################

	public function getStepEndMediaItems()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(id)
			FROM photos
		") ?: 0;
	}

	public function stepMediaItems(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->sourceDb->fetchAll("
			SELECT
				p.*,
			    c.cattype,
			    c.ismember,
			    IF(u.username IS NULL, p.user, u.username) AS username,
			    u.email
			FROM photos AS p
			LEFT JOIN categories AS c ON (p.cat = c.id)
			LEFT JOIN users AS u ON (p.userid = u.userid)
			WHERE p.id > ? AND p.id <= ?
			ORDER BY p.id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($mediaItems, 'userid');
		$this->preparePhotopostUserIdMap($mapUserIds);

		$mapAlbumIds = [];
		$mapCategoryIds = [];
		foreach ($mediaItems AS $mediaItem)
		{
			if ($mediaItem['cattype'] == 'a')
			{
				$mapAlbumIds[] = $mediaItem['cat'];
			}
			else if ($mediaItem['cattype'] == 'c')
			{
				$mapCategoryIds[] = $mediaItem['cat'];
			}
		}

		$this->lookup('xfmg_album', $mapAlbumIds);
		$this->lookup('xfmg_category', $mapCategoryIds);

		// special case logging - maps user IDs to album IDs
		$this->lookup('xfmg_member_album', $mapUserIds);

		foreach ($mediaItems AS $mediaItem)
		{
			$oldId = $mediaItem['id'];
			$state->startAfter = $oldId;

			$userId = $this->lookupPhotopostUserId(
				$mediaItem['userid'], $mediaItem['username'], $mediaItem['email']
			);

			$albumId = 0;
			$categoryId = 0;
			$isMemberCat = false;
			if ($mediaItem['cattype'] == 'a')
			{
				$albumId = $this->lookupId('xfmg_album', $mediaItem['cat']);
				if (!$albumId)
				{
					continue;
				}
			}
			else if ($mediaItem['cattype'] == 'c')
			{
				$categoryId = $this->lookupId('xfmg_category', $mediaItem['cat']);
				if (!$categoryId)
				{
					continue;
				}
				$isMemberCat = ($mediaItem['ismember'] || $mediaItem['cat'] == 500);
			}

			if ($isMemberCat)
			{
				// image is inside a member category. check if we have already created an album for this member.
				$albumId = $this->lookupId('xfmg_member_album', $mediaItem['userid']);

				if (!$albumId)
				{
					/** @var \XFMG\Import\Data\Album $albumImport */
					$albumImport = $this->newHandler('XFMG:Album');

					$createDate = $this->sourceDb->fetchOne("
						SELECT `date`
						FROM photos
						WHERE cat = ?
						ORDER BY id ASC
						LIMIT 1
					", $mediaItem['cat']) ?: time();

					$albumImport->bulkSet([
						'title' => $mediaItem['username'],
						'username' => $mediaItem['username'],
						'user_id' => $userId,
						'category_id' => $categoryId,
						'create_date' => $createDate,
						'view_privacy' => 'public'
					]);

					$albumImport->preventRetainIds();
					$albumImport->log(false);

					$albumId = $albumImport->save($oldId);
					if ($albumId)
					{
						$this->log('xfmg_member_album', $mediaItem['userid'], $albumId);
					}
				}
			}

			if (!$categoryId && !$albumId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\MediaItem $mediaImport */
			$mediaImport = $this->newHandler('XFMG:MediaItem');

			$mediaImport->bulkSet($this->mapXfKeys($mediaItem, [
				'title',
				'description',
				'media_date' => 'date',
				'username',
				'view_count' => 'views',
				'comment_count' => 'numcom'
			]));
			$mediaImport->bulkSet([
				'album_id' => $albumId ?: 0,
				'category_id' => $categoryId ?: 0,
				'user_id' => $userId
			]);

			if ($mediaItem['ipaddress'])
			{
				$mediaImport->setLoggedIp($mediaItem['ipaddress']);
			}

			$sourceFile = null;

			$filename = $mediaItem['bigimage'];
			$extension = File::getFileExtension($filename);

			list ($mediaType, $filePath) = $this->getMediaTypeAndFilePathFromExtension($extension);

			if (!$mediaType)
			{
				continue;
			}

			$mediaImport->media_type = $mediaType;

			if (isset($mediaItem['storecat']) && $mediaItem['storecat'] > 0)
			{
				$mediaItem['cat'] = $mediaItem['storecat'];
			}

			$sourceFile = sprintf(
				'%s/%d/%s',
				$stepConfig['path'],
				$mediaItem['cat'],
				$filename
			);

			if (!file_exists($sourceFile) || !is_readable($sourceFile))
			{
				continue;
			}

			/** @var \XF\Import\Data\Attachment $attachmentImport */
			$attachmentImport = $this->newHandler('XF:Attachment');

			$attachmentImport->attach_date = $mediaItem['date'];
			$attachmentImport->content_type = 'xfmg_media';
			$attachmentImport->unassociated = false;

			$attachmentImport->setDataExtra('upload_date', $mediaItem['date']);
			if ($filePath)
			{
				$attachmentImport->setDataExtra('file_path', $filePath);
			}
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

		File::cleanUpTempFiles();

		return $state->resumeIfNeeded();
	}

	protected function getRatingsTableName()
	{
		if ($this->sourceDb->getSchemaManager()->tableExists($this->baseConfig['db']['tablePrefix'] . 'ratings'))
		{
			return 'ratings';
		}
		else
		{
			return 'comments';
		}
	}

	// ############################## STEP: RATINGS #########################

	public function getStepEndRatings()
	{
		$tableName = $this->getRatingsTableName();
		return $this->sourceDb->fetchOne("SELECT MAX(id) FROM {$tableName} WHERE rating > 0") ?: 0;
	}

	public function stepRatings(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$tableName = $this->getRatingsTableName();
		$ratings = $this->sourceDb->fetchAll("
			SELECT r.*,
				IF(u.username IS NULL, r.username, u.username) AS username,
				u.email
			FROM $tableName AS r
			LEFT JOIN users AS u ON (r.userid = u.userid)
			WHERE r.id > ? AND r.id <= ? AND r.rating > 0
			ORDER BY r.id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$ratings)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($ratings, 'userid');
		$this->preparePhotopostUserIdMap($mapUserIds);

		$mapMediaIds = [];
		foreach ($ratings AS $rating)
		{
			$mapMediaIds[] = $rating['photo'];
		}

		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($ratings AS $rating)
		{
			$oldId = $rating['id'];
			$state->startAfter = $oldId;

			$contentId = $this->lookupId('xfmg_media', $rating['photo']);
			$userId = $this->lookupPhotopostUserId($rating['userid'], $rating['username'], $rating['email']);

			if (!$contentId || !$userId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Rating $import */
			$import = $this->newHandler('XFMG:Rating');

			$import->bulkSet([
				'username' => $rating['username'],
				'content_type' => 'xfmg_media',
				'content_id' => $contentId,
				'user_id' => $userId,
				'rating' => $rating['rating'] / 2, // Photopost ratings are out of 10
				'rating_date' => $rating['date']
			]);

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

	// ############################## STEP: COMMENTS #########################

	public function getStepEndComments()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(id) FROM comments") ?: 0;
	}

	public function stepComments(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$comments = $this->sourceDb->fetchAll("
			SELECT
				c.*,
				IF(u.username IS NULL, c.username, u.username) AS username,
			    u.email
			FROM comments AS c
			LEFT JOIN users AS u ON (c.userid = u.userid)
			WHERE
				c.id > ? AND c.id <= ? AND c.approved = 1
			ORDER BY
				c.id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$comments)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($comments, 'userid');
		$this->preparePhotopostUserIdMap($mapUserIds);

		$mapMediaIds = [];
		foreach ($comments AS $comment)
		{
			$mapMediaIds[] = $comment['photo'];
		}

		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($comments AS $comment)
		{
			$oldId = $comment['id'];
			$state->startAfter = $oldId;

			$mediaId = $this->lookupId('xfmg_media', $comment['photo']);
			$userId = $this->lookupPhotopostUserId($comment['userid'], $comment['username'], $comment['email']);

			if (!$mediaId || !$userId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Comment $import */
			$import = $this->newHandler('XFMG:Comment');

			$import->bulkSet([
				'username' => $comment['username'],
				'comment_date' => $comment['date'],
				'message' => $comment['comment'],
				'content_id' => $mediaId,
				'content_type' => 'xfmg_media',
				'user_id' => $userId
			]);

			if ($comment['ipaddress'])
			{
				$import->setLoggedIp($comment['ipaddress']);
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

	// ########################### STEP: TAGS ###############################

	public function getStepEndContentTags()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(id)
			FROM photos
			WHERE keywords <> ''
		") ?: 0;
	}

	public function stepContentTags(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->sourceDb->fetchAllKeyed("
			SELECT
				p.*,
				IF(u.username IS NULL, p.user, u.username) AS username,
			    u.email
			FROM photos AS p
			LEFT JOIN users AS u ON (p.userid = u.userid)
			WHERE p.id > ? AND p.id <= ?
			AND p.keywords <> ''
			ORDER BY p.id
			LIMIT {$limit} 
		", 'id', [$state->startAfter, $state->end]);

		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($mediaItems, 'userid');
		$this->preparePhotopostUserIdMap($mapUserIds);

		$mapMediaIds = [];
		foreach ($mediaItems AS $mediaItem)
		{
			$mapMediaIds[] = $mediaItem['id'];
		}

		$this->lookup('xfmg_media', $mapMediaIds);

		/** @var \XF\Import\DataHelper\Tag $tagHelper */
		$tagHelper = $this->getDataHelper('XF:Tag');

		foreach ($mediaItems AS $oldId => $mediaItem)
		{
			$state->startAfter = $oldId;

			if (!$newMediaId = $this->lookupId('xfmg_media', $oldId))
			{
				continue;
			}

			$userId = $this->lookupPhotopostUserId($mediaItem['userid'], $mediaItem['username'], $mediaItem['email']);

			$tags = $this->decodeValue($mediaItem['keywords'], 'list-comma');
			foreach ($tags AS $tag)
			{
				$tag = trim($tag);

				$newId = $tagHelper->importTag(htmlspecialchars_decode($tag), 'xfmg_media', $newMediaId, [
					'add_user_id' => $userId,
					'add_date' => $mediaItem['date'],
					'visible' => 1,
					'content_date' => $mediaItem['date']
				]);

				if ($newId)
				{
					$state->imported++;
				}
			}

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $state->resumeIfNeeded();
	}

	protected function preparePhotopostUserIdMap(array $mapUserIds)
	{
		switch ($this->baseConfig['integration'])
		{
			case 'forum':
			case 'xenforo':
				if ($this->baseConfig['forum_import_log'])
				{
					$this->lookup('user', $mapUserIds);
				}
		}
	}

	protected function lookupPhotopostUserId($oldId, $username = null, $email = null)
	{
		switch ($this->baseConfig['integration'])
		{
			case 'forum':
			case 'xenforo':
				if ($this->baseConfig['forum_import_log'])
				{
					return $this->lookupId('user', $oldId);
				}
				else
				{
					return $oldId;
				}
				break;
			case 'photopost':
				if ($this->baseConfig['attempt_match'] && ($username || $email))
				{
					$where = [];
					$params = [];
					if ($username)
					{
						$where[] = 'username = ?';
						$params[] = $username;
					}
					if ($email)
					{
						$where[] = 'email = ?';
						$params[] = $email;
					}

					$db = $this->db();

					$userId = $db->fetchOne('
						SELECT user_id
						FROM xf_user
						WHERE ' . implode(' OR ', $where) . '
						ORDER BY user_id
						LIMIT 1
					', $params);

					return $userId ?: 0;
				}
				else
				{
					return 0;
				}
				break;

			default:
				return 0;
		}
	}
}