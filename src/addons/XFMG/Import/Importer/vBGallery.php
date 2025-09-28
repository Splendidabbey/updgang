<?php

namespace XFMG\Import\Importer;

use XF\Import\StepState;

class vBGallery extends AbstractMGImporter
{
	use vBulletinSourceTrait;

	const OPTION_VIEW = 0x01;
	const OPTION_UPLOAD = 0x02;
	const OPTION_REPLY = 0x04;
	const OPTION_RATE = 0x08;

	// Videos can be uploaded but do not display.
	// Should be rare and gallery seems focused on
	// images so only import images at this point.
	protected $importableTypes = [
		'gif', 'jpg', 'jpeg', 'jpe', 'png'
	];

	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'Photopost vBGallery for vBulletin',
		];
	}

	protected function getAttachPathConfig(array &$baseConfig, \XF\Db\Mysqli\Adapter $sourceDb)
	{
		try
		{
			$baseConfig['attachpath'] = $sourceDb->fetchOne("
				SELECT `value`
				FROM ppgal_setting
				WHERE varname = 'gallery_filedirectory'
			");
		}
		catch (\XF\Db\Exception $e) {}
	}

	public function renderStepConfigOptions(array $vars)
	{
		$vars['stepConfig'] = $this->getStepConfigDefault();
		return $this->app->templater()->renderTemplate('admin:xfmg_import_step_config_photopost', $vars);
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
			'mediaFields' => [
				'title' => \XF::phrase('xfmg_media_fields')
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

	// ############################## STEP: ALBUMS #########################

	public function getStepEndAlbums()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(catid) 
			FROM ppgal_categories
			WHERE catuserid > 0
			AND membercat = 0
		") ?: 0;
	}

	public function stepAlbums(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$albums = $this->sourceDb->fetchAll("
			SELECT
				a.*,
				u.username,
			    IFNULL(u.joindate, UNIX_TIMESTAMP()) AS createdate
			FROM ppgal_categories AS a
			LEFT JOIN user AS u ON (a.catuserid = u.userid)
			WHERE a.catid > ? AND a.catid <= ? AND a.catuserid > 0 AND a.membercat = 0
			ORDER BY a.catid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$albums)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($albums, 'catuserid');
		$this->lookup('user', $mapUserIds);

		foreach ($albums AS $album)
		{
			$oldId = $album['catid'];
			$state->startAfter = $oldId;

			/** @var \XFMG\Import\Data\Album $albumImport */
			$albumImport = $this->newHandler('XFMG:Album');

			$userId = $this->lookupId('user', $album['catuserid'], 0);

			$createDate = $this->sourceDb->fetchOne("
				SELECT dateline
				FROM ppgal_images
				WHERE catid = ?
				ORDER BY imageid ASC
				LIMIT 1
			", $oldId) ?: $album['createdate'];

			$albumImport->bulkSet($this->mapXfKeys($album, [
				'title',
				'description',
				'username',
				'media_count' => 'imagecount'
			]));

			$albumImport->user_id = $userId;
			$albumImport->create_date = $createDate;
			$albumImport->view_privacy = $album['useroptions'] & self::OPTION_VIEW ? 'public' : 'private';

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
			FROM ppgal_categories
			WHERE catuserid = 0
			ORDER BY catid
		", 'catid');

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
				'title',
				'description',
				'display_order' => 'displayorder',
				'media_count' => 'imagecount'
			]));

			$categoryImport->parent_category_id = $newParentId;

			if ($category['membercat'])
			{
				$categoryImport->category_type = 'album';
			}
			else
			{
				$categoryImport->category_type = $category['hasimages'] ? 'media' : 'container';
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

	// ############################## STEP: MEDIA FIELDS #########################

	public function stepMediaFields(StepState $state)
	{
		$fields = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM ppgal_customfields
			ORDER BY fieldid
		", 'fieldid');

		$categoryIds = $this->sourceDb->fetchAllColumn("
			SELECT catid
			FROM ppgal_categories
			WHERE catuserid = 0
			ORDER BY catid
		");
		$mapCategoryIds = $this->lookup('xfmg_category', $categoryIds);

		$existingFields = $this->db()->fetchPairs("SELECT field_id, field_id FROM xf_mg_media_field");

		foreach ($fields AS $oldId => $field)
		{
			if ($field['type'] == 'text')
			{
				$field['type'] = 'textbox';
			}

			/** @var \XFMG\Import\Data\MediaField $import */
			$import = $this->newHandler('XFMG:MediaField');
			$import->bulkSet($this->mapXfKeys($field, [
				'display_order' => 'displayorder',
				'max_length' => 'maxlength',
				'required'
			]));

			$fieldId = $this->convertToUniqueId($field['title'], $existingFields, 25);

			$import->field_id = $fieldId;
			$import->display_group = 'below_media';
			$import->field_type = $field['type'];
			$import->field_choices = $this->decodeValue($field['options'], 'list-lines');

			$import->setTitle($field['title'], $field['description']);

			$import->setCategories($mapCategoryIds);

			$import->save($oldId);

			$state->imported++;
		}

		return $state->complete();
	}

	// ############################## STEP: MEDIA ITEMS #########################

	public function getStepEndMediaItems()
	{
		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		return $this->sourceDb->fetchOne("
			SELECT MAX(imageid)
			FROM ppgal_images
			WHERE extension IN($extensionsQuoted)
		") ?: 0;
	}

	public function stepMediaItems(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		$mediaItems = $this->sourceDb->fetchAll("
			SELECT
				i.*,
			    c.catuserid,
			    c.membercat,
			    f.*,
				u.*,
			    IF(u.username IS NULL, i.username, u.username) AS username,
				IFNULL(u.joindate, UNIX_TIMESTAMP()) AS createdate
			FROM ppgal_images AS i
			LEFT JOIN ppgal_categories AS c ON (i.catid = c.catid)
			LEFT JOIN ppgal_customfields_entries AS f ON (i.imageid = f.imgid)
			LEFT JOIN user AS u ON (i.userid = u.userid)
			WHERE i.imageid > ? AND i.imageid <= ? AND i.extension IN($extensionsQuoted)
			ORDER BY i.imageid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapAlbumIds = [];
		$mapCategoryIds = [];
		foreach ($mediaItems AS $mediaItem)
		{
			$mapUserIds[] = $mediaItem['userid'];

			if ($mediaItem['catuserid'] > 0 && !$mediaItem['membercat'])
			{
				$mapAlbumIds[] = $mediaItem['catid'];
			}
			else if ($mediaItem['catuserid'] == 0)
			{
				$mapCategoryIds[] = $mediaItem['catid'];
			}
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_album', $mapAlbumIds);
		$this->lookup('xfmg_category', $mapCategoryIds);

		// special case logging - maps user IDs to album IDs
		$this->lookup('xfmg_member_album', $mapUserIds);

		$fieldDefinitions = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM ppgal_customfields
			ORDER BY fieldid
		", 'fieldid');
		$mapFieldIds = $this->lookup('xfmg_media_field', array_keys($fieldDefinitions));

		foreach ($mediaItems AS $mediaItem)
		{
			$oldId = $mediaItem['imageid'];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $mediaItem['userid'], 0);

			$albumId = 0;
			$categoryId = 0;
			$isMemberCat = false;
			if ($mediaItem['catuserid'] > 0 && !$mediaItem['membercat'])
			{
				$albumId = $this->lookupId('xfmg_album', $mediaItem['catid']);
				if (!$albumId)
				{
					continue;
				}
			}
			else if ($mediaItem['catuserid'] == 0)
			{
				$categoryId = $this->lookupId('xfmg_category', $mediaItem['catid']);
				if (!$categoryId)
				{
					continue;
				}
				$isMemberCat = boolval($mediaItem['membercat']);
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
						SELECT dateline
						FROM ppgal_images
						WHERE catid = ?
						ORDER BY dateline ASC
						LIMIT 1
					", $mediaItem['catid']) ?: $mediaItem['createdate'];

					$albumImport->bulkSet([
						'title' => $mediaItem['username'],
						'username' => $mediaItem['username'],
						'user_id' => $userId,
						'category_id' => $categoryId,
						'create_date' => $createDate,
						'view_privacy' => 'inherit'
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
				'description',
				'media_date' => 'dateline',
				'username',
				'view_count' => 'views',
				'comment_count' => 'posts'
			]));
			$mediaImport->bulkSet([
				'title' => $mediaItem['title'] ?: $mediaItem['filename'],
				'album_id' => $albumId ?: 0,
				'category_id' => $categoryId ?: 0,
				'user_id' => $userId,
				'media_type' => 'image'
			]);

			if ($mediaItem['ipaddress'])
			{
				$mediaImport->setLoggedIp($mediaItem['ipaddress']);
			}

			$fieldValues = [];
			foreach ($mapFieldIds AS $oldFieldId => $newFieldId)
			{
				if (!isset($fieldDefinitions[$oldFieldId]))
				{
					continue;
				}

				$fieldDefinition = $fieldDefinitions[$oldFieldId];
				$fieldName = 'field' . $oldFieldId;
				$fieldValue = null;

				if (isset($mediaItem[$fieldName]) && $mediaItem[$fieldName] !== '')
				{
					$options = $this->decodeValue($fieldDefinition['options'], 'list-lines');

					switch ($fieldDefinition['type'])
					{
						case 'select':
						case 'radio':
							$fieldValue = array_search($mediaItem[$fieldName], $options, true);
							break;

						case 'checkbox':
							$fieldValue = [];
							$decoded = $this->decodeValue($mediaItem[$fieldName], 'list-comma');
							if ($decoded)
							{
								foreach ($decoded AS $value)
								{
									$key = array_search(trim($value), $options, true);
									$fieldValue[$key] = $key;
								}
							}
							break;

						default:
							$fieldValue = $mediaItem[$fieldName];
							break;
					}

					if (!empty($fieldValue))
					{
						$fieldValues[$newFieldId] = \XF\Util\Arr::htmlSpecialCharsDecodeArray($fieldValue);
					}
				}
			}

			if ($fieldValues)
			{
				$mediaImport->setCustomFields($fieldValues);
			}

			$sourceFile = null;

			$filename = $mediaItem['originalname'] ?: $mediaItem['filename'];

			$safeMode = $this->sourceDb->fetchOne("
				SELECT `value`
				FROM ppgal_setting
				WHERE varname = 'gallery_insafemode'
			");

			if ($safeMode)
			{
				$sourceFile = sprintf(
					'%s/%s',
					$stepConfig['path'],
					$filename
				);
			}
			else
			{
				$sourceFile = sprintf(
					'%s/%s/%s',
					$stepConfig['path'],
					implode('/', str_split($mediaItem['userid'])),
					$filename
				);
			}

			if (!file_exists($sourceFile) || !is_readable($sourceFile))
			{
				continue;
			}

			/** @var \XF\Import\Data\Attachment $attachmentImport */
			$attachmentImport = $this->newHandler('XF:Attachment');

			$attachmentImport->attach_date = $mediaItem['dateline'];
			$attachmentImport->content_type = 'xfmg_media';
			$attachmentImport->unassociated = false;

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

	// ############################## STEP: RATINGS #########################

	public function getStepEndRatings()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(rateid) FROM ppgal_rate") ?: 0;
	}

	public function stepRatings(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$ratings = $this->sourceDb->fetchAll("
			SELECT r.*,
			       u.username
			FROM ppgal_rate AS r
			LEFT JOIN user AS u ON (r.userid = u.userid)
			WHERE r.rateid > ? AND r.rateid <= ?
			ORDER BY r.rateid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$ratings)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapMediaIds = [];

		foreach ($ratings AS $rating)
		{
			$mapUserIds[] = $rating['userid'];
			$mapMediaIds[] = $rating['imageid'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($ratings AS $rating)
		{
			$oldId = $rating['rateid'];
			$state->startAfter = $oldId;

			$contentId = $this->lookupId('xfmg_media', $rating['imageid']);
			$userId = $this->lookupId('user', $rating['userid']);

			if (!$contentId || !$userId)
			{
				continue;
			}

			// check if this rating has already been imported by virtue
			// of a user being merged during import and skip if exists
			$exists = (bool)$this->db()->fetchOne("
				SELECT rating_id
				FROM xf_mg_rating
				WHERE content_type = 'xfmg_media'
				AND content_id = ?
				AND user_id = ?
			", [$contentId, $userId]);

			if ($exists)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Rating $import */
			$import = $this->newHandler('XFMG:Rating');

			$import->bulkSet($this->mapKeys($rating, [
				'username',
				'rating'
			]));
			$import->bulkSet([
				'content_type' => 'xfmg_media',
				'content_id' => $contentId,
				'user_id' => $userId,
				'rating_date' => time()
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
		return $this->sourceDb->fetchOne("SELECT MAX(commentid) FROM picturecomment") ?: 0;
	}

	public function stepComments(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$comments = $this->sourceDb->fetchAll("
			SELECT
				c.*,
				u.*,
				IF(u.username IS NULL, c.username, u.username) AS username
			FROM ppgal_posts AS c
			LEFT JOIN user AS u ON (c.userid = u.userid)
			WHERE
				c.postid > ? AND c.postid <= ? AND c.visible = 1
			ORDER BY
				c.postid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$comments)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapMediaIds = [];

		foreach ($comments AS $comment)
		{
			$mapUserIds[] = $comment['userid'];
			$mapMediaIds[] = $comment['imageid'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($comments AS $comment)
		{
			$oldId = $comment['postid'];
			$state->startAfter = $oldId;

			$mediaId = $this->lookupId('xfmg_media', $comment['imageid']);
			$userId = $this->lookupId('user', $comment['userid']);

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

			$import->bulkSet([
				'message' => $message,
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
			SELECT MAX(imageid)
			FROM ppgal_images
			WHERE keywords <> ''
		") ?: 0;
	}

	public function stepContentTags(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM ppgal_images
			WHERE imageid > ? AND imageid <= ?
			AND keywords <> ''
			ORDER BY imageid
			LIMIT {$limit} 
		", 'imageid', [$state->startAfter, $state->end]);

		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		foreach ($mediaItems AS $mediaItem)
		{
			$mapUserIds[] = $mediaItem['userid'];
		}

		$this->lookup('xfmg_media', array_keys($mediaItems));
		$this->lookup('user', $mapUserIds);

		/** @var \XF\Import\DataHelper\Tag $tagHelper */
		$tagHelper = $this->getDataHelper('XF:Tag');

		foreach ($mediaItems AS $oldId => $mediaItem)
		{
			$state->startAfter = $oldId;

			if (!$newMediaId = $this->lookupId('xfmg_media', $oldId))
			{
				continue;
			}

			$userId = $this->lookupId('user', $mediaItem['userid']);

			$tags = $this->decodeValue($mediaItem['keywords'], 'list-comma');
			foreach ($tags AS $tag)
			{
				$tag = trim($tag);

				$newId = $tagHelper->importTag(htmlspecialchars_decode($tag), 'xfmg_media', $newMediaId, [
					'add_user_id' => $userId,
					'add_date' => $mediaItem['dateline'],
					'visible' => 1,
					'content_date' => $mediaItem['dateline']
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
}