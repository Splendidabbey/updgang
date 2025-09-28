<?php

namespace XFMG\Import\Importer;

use XF\Import\Data\AbstractData;
use XF\Import\StepState;
use XF\Import\Importer\XenForoSourceTrait;
use XFMG\Import\DataHelper\Watch;

class XFMG2 extends AbstractMGImporter
{
	use XenForoSourceTrait;

	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'XenForo Media Gallery 2.0',
		];
	}

	protected function requiresForumImportLog()
	{
		return true;
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFMG'");
		if (!$versionId || intval($versionId) < 902000031 || intval($versionId) >= 902010031)
		{
			$error = \XF::phrase('xfmg_you_may_only_import_from_xenforo_media_gallery_x', ['version' => '2.0']);
			return false;
		}

		return true;
	}

	protected function getStepConfigDefault()
	{
		return [];
	}

	public function renderStepConfigOptions(array $vars)
	{
		return '';
	}

	public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
	{
		return true;
	}

	protected function doInitializeSource()
	{
		$this->sourceDb = new \XF\Db\Mysqli\Adapter(
			$this->baseConfig['db'],
			$this->app->config('fullUnicode')
		);

		$this->forumLog = new \XF\Import\Log(
			$this->app->db(), $this->baseConfig['forum_import_log']
		);
	}

	public function getSteps()
	{
		return [
			'categories' => [
				'title' => \XF::phrase('xfmg_categories')
			],
			'categoryPermissions' => [
				'title' => \XF::phrase('xfmg_category_permissions'),
				'depends' => ['categories']
			],
			'albums' => [
				'title' => \XF::phrase('xfmg_albums')
			],
			'mediaFields' => [
				'title' => \XF::phrase('xfmg_media_fields'),
				'depends' => ['categories']
			],
			'mediaItems' => [
				'title' => \XF::phrase('xfmg_media_items'),
				'depends' => ['categories', 'albums']
			],
			'ratings' => [
				'title' => \XF::phrase('xfmg_ratings'),
				'depends' => [] // handle dependencies in step
			],
			'comments' => [
				'title' => \XF::phrase('xfmg_comments'),
				'depends' => [] // handles dependencies in step
			],
			'likes' => [
				'title' => \XF::phrase('likes'),
				'depends' => [] // handles dependencies in step
			],
			'tags' => [
				'title' => \XF::phrase('tags'),
				'depends' => ['mediaItems']
			],
		];
	}

	// ############################## STEP: CATEGORIES #########################

	public function stepCategories(StepState $state)
	{
		$categories = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM xf_mg_category
			ORDER BY category_id
		", 'category_id');

		$categoryTreeMap = [];
		foreach ($categories AS $categoryId => $category)
		{
			$categoryTreeMap[$category['parent_category_id']][] = $categoryId;
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

			/** @var \XFMG\Import\Data\Category $importCategory */
			$importCategory = $this->newHandler('XFMG:Category');

			$this->handleWatchers($importCategory, $oldCategoryId);

			$importCategory->bulkSet($this->mapKeys($category, [
				'title',
				'description',
				'display_order',
				'category_type',
				'media_count',
				'album_count',
				'comment_count',
				'min_tags'
			]));
			$importCategory->parent_category_id = $newParentId;
			$importCategory->allowed_types = $this->decodeValue($category['allowed_types'], 'serialized-json-array');

			$newCategoryId = $importCategory->save($oldCategoryId);
			if ($newCategoryId)
			{
				$total++;
				$total += $this->importCategoryTree($categories, $tree, $oldCategoryId, $newCategoryId);
			}
		}

		return $total;
	}

	// ############################## STEP: CATEGORY PERMISSIONS #########################

	public function stepCategoryPermissions(StepState $state)
	{
		$this->typeMap('user_group');
		$this->typeMap('xfmg_category');

		$entries = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_permission_entry_content
			WHERE content_type = 'xfmg_category'
		");

		$mapUserIds = [];
		foreach ($entries AS $entry)
		{
			if ($entry['user_id'])
			{
				$mapUserIds[] = $entry['user_id'];
			}
		}

		$mappedUserIds = $this->lookup('user', $mapUserIds);

		$groupedEntries = [];
		foreach ($entries AS $entry)
		{
			$newCategoryId = $this->lookupId('xfmg_category', $entry['content_id']);
			if (!$newCategoryId)
			{
				continue;
			}

			if ($entry['user_id'])
			{
				$type = 'user';
				$newInsertId = $mappedUserIds[$entry['user_id']];
				if (!$newInsertId)
				{
					continue;
				}
			}
			else if ($entry['user_group_id'])
			{
				$type = 'group';
				$newInsertId = $this->lookupId('user_group', $entry['user_group_id']);
				if (!$newInsertId)
				{
					continue;
				}
			}
			else
			{
				$type = 'global';
				$newInsertId = 0;
			}

			if ($entry['permission_value'] == 'use_int')
			{
				$permValue = $entry['permission_value_int'];
			}
			else
			{
				$permValue = $entry['permission_value'];
			}

			$groupedEntries[$newCategoryId][$type][$newInsertId][$entry['permission_group_id']][$entry['permission_id']] = $permValue;
		}

		/** @var \XF\Import\DataHelper\Permission $permHelper */
		$permHelper = $this->dataManager->helper('XF:Permission');
		foreach ($groupedEntries AS $categoryId => $groupedTypeEntries)
		{
			foreach ($groupedTypeEntries AS $type => $typeEntries)
			{
				foreach ($typeEntries AS $typeId => $permsGrouped)
				{
					if ($type == 'user')
					{
						$permHelper->insertContentUserPermissions('xfmg_category', $categoryId, $typeId, $permsGrouped);
					}
					else if ($type == 'group')
					{
						$permHelper->insertContentUserGroupPermissions('xfmg_category', $categoryId, $typeId, $permsGrouped);
					}
					else
					{
						$permHelper->insertContentGlobalPermissions('xfmg_category', $categoryId, $permsGrouped);
					}

					$state->imported++;
				}
			}
		}

		return $state->complete();
	}

	// ############################## STEP: ALBUMS #########################

	public function getStepEndAlbums()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(album_id) FROM xf_mg_album") ?: 0;
	}

	public function stepAlbums(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$albums = $this->sourceDb->fetchAll("
			SELECT
				album.*,
				user.*,
				IF(user.username IS NULL, album.username, user.username) AS username,
				ip.ip,
				d.delete_date,
				d.delete_user_id,
				d.delete_username,
				d.delete_reason
				FROM
					xf_mg_album AS album
				LEFT JOIN xf_user AS user ON (album.user_id = user.user_id)
				LEFT JOIN xf_ip AS ip ON (album.ip_id = ip.ip_id)
				LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'xfmg_album' AND d.content_id = album.album_id)
			WHERE
				album.album_id > ? AND album.album_id <= ?
			ORDER BY
				album.album_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$albums)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapWarningIds = [];
		$mapCategoryIds = [];

		foreach ($albums AS $album)
		{
			$mapUserIds[] = $album['user_id'];
			$mapWarningIds[] = $album['warning_id'];
			$mapCategoryIds[] = $album['category_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('warning', $mapWarningIds);
		$this->lookup('xfmg_category', $mapCategoryIds);

		foreach ($albums AS $album)
		{
			$oldId = $album['album_id'];
			$state->startAfter = $oldId;

			/** @var \XFMG\Import\Data\Album $import */
			$import = $this->newHandler('XFMG:Album');

			$this->handleWatchers($import, $oldId);

			$import->bulkSet($this->mapKeys($album, [
				'album_hash',
				'title',
				'description',
				'create_date',
				'last_update_date',
				'view_privacy',
				'add_privacy',
				'album_state',
				'username',
				'media_count',
				'view_count',
				'warning_message'
			]));
			$import->bulkSet([
				'user_id' => $this->lookupId('user', $album['user_id'], 0),
				'warning_id' => $this->lookupId('warning', $album['warning_id'], 0),
				'category_id' => $this->lookupId('xfmg_category', $album['category_id'], 0),
			]);

			$import->setLoggedIp($album['ip']);
			$import->setDeletionLogData($this->extractDeletionLogData($album));

			if ($album['view_privacy'] == 'shared')
			{
				$viewUsers = $this->decodeValue($album['view_users'], 'json-array');
				foreach ($viewUsers AS $viewUserId)
				{
					$newUserId = $this->lookupId('user', $viewUserId, 0);
					if ($newUserId)
					{
						$import->addSharedUserView($newUserId);
					}
				}
			}

			if ($album['add_privacy'] == 'shared')
			{
				$addUsers = $this->decodeValue($album['add_users'], 'json-array');
				foreach ($addUsers AS $addUserId)
				{
					$newUserId = $this->lookupId('user', $addUserId, 0);
					if ($newUserId)
					{
						$import->addSharedUserAdd($newUserId);
					}
				}
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

	// ############################## STEP: MEDIA FIELDS #########################

	public function stepMediaFields(StepState $state)
	{
		$this->typeMap('xfmg_category');
		$this->typeMap('user_group');

		$fields = $this->sourceDb->fetchAllKeyed("
			SELECT field.*,
				ptitle.phrase_text AS title,
				pdesc.phrase_text AS description
			FROM xf_mg_media_field AS field
			INNER JOIN xf_phrase AS ptitle ON
				(ptitle.language_id = 0 AND ptitle.title = CONCAT('xfmg_media_field_title.', field.field_id))
			INNER JOIN xf_phrase AS pdesc ON
				(pdesc.language_id = 0 AND pdesc.title = CONCAT('xfmg_media_field_desc.', field.field_id))
		", 'field_id');

		$existingFields = $this->db()->fetchPairs("SELECT field_id, field_id FROM xf_mg_media_field");

		$fieldCategoryMap = [];
		$categoryFields = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_mg_category_field
		");
		foreach ($categoryFields AS $categoryField)
		{
			$newCategoryId = $this->lookupId('xfmg_category', $categoryField['category_id']);
			if ($newCategoryId)
			{
				$fieldCategoryMap[$categoryField['field_id']][] = $newCategoryId;
			}
		}

		foreach ($fields AS $oldId => $field)
		{
			if (!empty($existingFields[$oldId]))
			{
				// don't import a field if we already have one called that - this assumes the same structure
				$this->logHandler('XFMG:MediaField', $oldId, $oldId);
			}
			else
			{
				/** @var \XFMG\Import\Data\MediaField $import */
				$import = $this->setupCustomFieldImport('XFMG:MediaField', $field);

				if (!empty($fieldCategoryMap[$oldId]))
				{
					$import->setCategories($fieldCategoryMap[$oldId]);
				}

				$import->save($oldId);
			}

			$state->imported++;
		}

		return $state->complete();
	}

	// ############################## STEP: MEDIA ITEMS #########################

	public function getStepEndMediaItems()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(media_id) FROM xf_mg_media_item") ?: 0;
	}

	public function stepMediaItems(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 250;
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->sourceDb->fetchAll("
			SELECT
				mitem.*,
				a.*,
				ad.*,
				user.*,
				IF(user.username IS NULL, mitem.username, user.username) AS username,
				ip.ip,
				d.delete_date,
				d.delete_user_id,
				d.delete_username,
				d.delete_reason
				FROM
					xf_mg_media_item AS mitem
				INNER JOIN xf_attachment AS a ON (mitem.media_id = a.content_id AND a.content_type = 'xfmg_media')
				INNER JOIN xf_attachment_data AS ad ON (a.data_id = ad.data_id)
				LEFT JOIN xf_user AS user ON (mitem.user_id	= user.user_id)
				LEFT JOIN xf_ip AS ip ON (mitem.ip_id = ip.ip_id)
				LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'xfmg_media' AND d.content_id = mitem.media_id)
			WHERE mitem.media_id > ? AND mitem.media_id <= ?
			ORDER BY mitem.media_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$mediaItems)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapWarningIds = [];
		$mapCategoryIds = [];
		$mapAlbumIds = [];

		foreach ($mediaItems AS $mediaItem)
		{
			$mapUserIds[] = $mediaItem['user_id'];
			$mapWarningIds[] = $mediaItem['warning_id'];
			$mapCategoryIds[] = $mediaItem['category_id'];
			$mapAlbumIds[] = $mediaItem['album_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('warning', $mapWarningIds);
		$this->lookup('xfmg_category', $mapCategoryIds);
		$this->lookup('xfmg_album', $mapAlbumIds);

		foreach ($mediaItems AS $mediaItem)
		{
			$oldId = $mediaItem['media_id'];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $mediaItem['user_id'], 0);
			$warningId = $this->lookupId('warning', $mediaItem['warning_id'], 0);
			$albumId = $this->lookupId('xfmg_album', $mediaItem['album_id'], 0);
			$categoryId = $this->lookupId('xfmg_category', $mediaItem['category_id'], 0);

			if (!$albumId && !$categoryId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\MediaItem $mediaImport */
			$mediaImport = $this->newHandler('XFMG:MediaItem');

			$dataDir = $this->baseConfig['data_dir'];
			$internalDataDir = $this->baseConfig['internal_data_dir'];

			$oldGroupId = floor($oldId / 1000);
			$mediaHash = $mediaItem['media_hash'];

			$oldThumbPath = "$dataDir/xfmg/thumbnail/$oldGroupId/$oldId-$mediaHash.jpg";
			$mediaImport->setThumbnailPath($oldThumbPath);

			if ($mediaItem['custom_thumbnail_date'])
			{
				$oldCustomThumbPath = "$dataDir/xfmg/custom_thumbnail/$oldGroupId/$oldId-$mediaHash.jpg";
				$mediaImport->setCustomThumbnailPath($oldCustomThumbPath);
			}

			if ($mediaItem['watermarked'])
			{
				$oldOriginalPath = "$internalDataDir/xfmg/original/$oldGroupId/$oldId-$mediaHash.data";
				$mediaImport->setOriginalPath($oldOriginalPath);
			}

			$this->handleWatchers($mediaImport, $oldId);

			$mediaNotes = $this->sourceDb->fetchAll("
				SELECT
					*
				FROM
					xf_mg_media_note
				WHERE
					media_id = ?
				ORDER BY
					note_id
			", $oldId);
			if ($mediaNotes)
			{
				$mapNoteUserIds = [];

				foreach ($mediaNotes AS $mediaNote)
				{
					$mapNoteUserIds[] = $mediaNote['user_id'];
					if ($mediaNote['tagged_user_id'])
					{
						$mapNoteUserIds[] = $mediaNote['tagged_user_id'];
					}
				}

				$this->lookup('user', $mapNoteUserIds);

				foreach ($mediaNotes AS $mediaNote)
				{
					$oldNoteId = $mediaNote['note_id'];

					/** @var \XFMG\Import\Data\MediaNote $noteImport */
					$noteImport = $this->newHandler('XFMG:MediaNote');

					$noteImport->bulkSet($this->mapKeys($mediaNote, [
						'note_type',
						'tagged_username',
						'note_text',
						'note_date',
						'username',
						'tag_state',
						'tag_state_date'
					]));

					$noteData = $this->decodeValue($mediaNote['note_data'], 'serialized-json-array');
					$taggedUserId = $this->lookupId('user', $mediaNote['tagged_user_id'], 0);
					$noteUserId = $this->lookupId('user', $mediaNote['user_id'], 0);

					$noteImport->note_data = $noteData;
					$noteImport->tagged_user_id = $taggedUserId;
					$noteImport->user_id = $noteUserId;

					$mediaImport->addNote($oldNoteId, $noteImport);
				}
			}

			$mediaImport->bulkSet($this->mapKeys($mediaItem, [
				'media_hash',
				'title',
				'description',
				'media_date',
				'last_edit_date',
				'media_type',
				'media_tag',
				'media_embed_url',
				'media_state',
				'username',
				'view_count',
				'watermarked',
				'warning_message',
				'custom_thumbnail_date'
			]));
			$mediaImport->bulkSet([
				'album_id' => $albumId,
				'category_id' => $categoryId,
				'user_id' => $userId,
				'warning_id' => $warningId,
				'exif_data' => $this->decodeValue($mediaItem['exif_data'], 'json-array')
			]);

			$mediaImport->setLoggedIp($mediaItem['ip']);
			$mediaImport->setDeletionLogData($this->extractDeletionLogData($mediaItem));

			$customFields = $this->decodeValue($mediaItem['custom_fields'], 'serialized-json-array');
			if ($customFields)
			{
				$mediaImport->setCustomFields($this->mapCustomFields('xfmg_media_field', $customFields));
			}

			$attachmentImport = null;

			if ($mediaItem['media_type'] != 'embed')
			{
				$sourceFile = $this->getSourceAttachmentDataPath(
					$mediaItem['data_id'], $mediaItem['file_path'], $mediaItem['file_hash']
				);
				if (!file_exists($sourceFile) || !is_readable($sourceFile))
				{
					continue;
				}

				/** @var \XF\Import\Data\Attachment $attachmentImport */
				$attachmentImport = $this->newHandler('XF:Attachment');
				$attachmentImport->bulkSet($this->mapKeys($mediaItem, [
					'content_type',
					'attach_date',
					'temp_hash',
					'unassociated',
					'view_count'
				]));
				$attachmentImport->setDataExtra('upload_date', $mediaItem['media_date']);
				$attachmentImport->setDataExtra('file_path', $mediaItem['file_path']);
				$attachmentImport->setDataUserId($this->lookupId('user', $mediaItem['user_id'], 0));
				$attachmentImport->setSourceFile($sourceFile, $mediaItem['filename']);

				$mediaImport->addAttachment($attachmentImport);
			}

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
		return $this->sourceDb->fetchOne("SELECT MAX(rating_id) FROM xf_mg_rating") ?: 0;
	}

	public function stepRatings(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$contentTypes = [];
		$stepsRun = $this->session->getStepsRun();

		if (in_array('albums', $stepsRun))
		{
			$contentTypes[] = 'xfmg_album';
		}
		if (in_array('mediaItems', $stepsRun))
		{
			$contentTypes[] = 'xfmg_media';
		}

		if (!$contentTypes)
		{
			return $state->complete();
		}

		$contentTypesQuoted = $this->sourceDb->quote($contentTypes);

		$ratings = $this->sourceDb->fetchAll("
			SELECT
				rating.*,
				user.*,
				IF(user.username IS NULL, rating.username, user.username) AS username
				FROM
					xf_mg_rating AS rating
				LEFT JOIN xf_user AS user ON (rating.user_id = user.user_id)
			WHERE
				rating.rating_id > ? AND rating.rating_id <= ? AND rating.content_type IN($contentTypesQuoted)
			ORDER BY
				rating.rating_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$ratings)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapAlbumIds = [];
		$mapMediaIds = [];

		foreach ($ratings AS $rating)
		{
			$mapUserIds[] = $rating['user_id'];
			if ($rating['content_type'] == 'xfmg_album')
			{
				$mapAlbumIds[] = $rating['content_id'];
			}
			else if ($rating['content_type'] == 'xfmg_media')
			{
				$mapMediaIds[] = $rating['content_id'];
			}
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_album', $mapAlbumIds);
		$this->lookup('xfmg_media', $mapMediaIds);

		foreach ($ratings AS $rating)
		{
			$oldId = $rating['rating_id'];
			$state->startAfter = $oldId;

			$contentId = $this->lookupId($rating['content_type'], $rating['content_id']);
			$userId = $this->lookupId('user', $rating['user_id']);

			if (!$contentId || !$userId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Rating $import */
			$import = $this->newHandler('XFMG:Rating');

			$import->bulkSet($this->mapKeys($rating, [
				'content_type',
				'username',
				'rating',
				'rating_date'
			]));
			$import->bulkSet([
				'content_id' => $contentId,
				'user_id' => $userId
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
		return $this->sourceDb->fetchOne("SELECT MAX(comment_id) FROM xf_mg_comment") ?: 0;
	}

	public function stepComments(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$contentTypes = [];
		$stepsRun = $this->session->getStepsRun();

		if (in_array('albums', $stepsRun))
		{
			$contentTypes[] = 'xfmg_album';
		}
		if (in_array('mediaItems', $stepsRun))
		{
			$contentTypes[] = 'xfmg_media';
		}

		if (!$contentTypes)
		{
			return $state->complete();
		}

		$contentTypesQuoted = $this->sourceDb->quote($contentTypes);

		$comments = $this->sourceDb->fetchAll("
			SELECT
				comment.*,
				user.*,
				IF(user.username IS NULL, comment.username, user.username) AS username,
				ip.ip,
				d.delete_date,
				d.delete_user_id,
				d.delete_username,
				d.delete_reason
				FROM
					xf_mg_comment AS comment
				LEFT JOIN xf_user AS user ON (comment.user_id = user.user_id)
				LEFT JOIN xf_ip AS ip ON (comment.ip_id = ip.ip_id)
				LEFT JOIN xf_deletion_log AS d ON (d.content_type = 'xfmg_comment' AND d.content_id = comment.comment_id)
			WHERE
				comment.comment_id > ? AND comment.comment_id <= ? AND comment.content_type IN($contentTypesQuoted)
			ORDER BY
				comment.comment_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
		if (!$comments)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapAlbumIds = [];
		$mapMediaIds = [];
		$mapRatingIds = [];
		$mapWarningIds = [];

		foreach ($comments AS $comment)
		{
			$mapUserIds[] = $comment['user_id'];
			$mapUserIds[] = $comment['last_edit_user_id'];
			if ($comment['content_type'] == 'xfmg_album')
			{
				$mapAlbumIds[] = $comment['content_id'];
			}
			else if ($comment['content_type'] == 'xfmg_media')
			{
				$mapMediaIds[] = $comment['content_id'];
			}
			$mapRatingIds[] = $comment['rating_id'];
			$mapWarningIds[] = $comment['warning_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_album', $mapAlbumIds);
		$this->lookup('xfmg_media', $mapMediaIds);
		$this->lookup('xfmg_rating', $mapRatingIds);
		$this->lookup('warning', $mapWarningIds);

		foreach ($comments AS $comment)
		{
			$oldId = $comment['comment_id'];
			$state->startAfter = $oldId;

			$contentId = $this->lookupId($comment['content_type'], $comment['content_id']);
			$userId = $this->lookupId('user', $comment['user_id'], 0);

			if (!$contentId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Comment $import */
			$import = $this->newHandler('XFMG:Comment');

			$import->bulkSet($this->mapKeys($comment, [
				'content_type',
				'username',
				'comment_date',
				'comment_state',
				'warning_message',
				'last_edit_date',
				'edit_count'
			]));
			$import->bulkSet([
				'message' => $this->rewriteQuotes($comment['message'], 'xfmg_comment'),
				'content_id' => $contentId,
				'user_id' => $userId,
				'rating_id' => $this->lookupId('xfmg_rating', $comment['rating_id'], 0),
				'warning_id' => $this->lookupId('warning', $comment['warning_id'], 0),
				'last_edit_user_id' => $this->lookupId('user', $comment['last_edit_user_id'], 0),
			]);

			$import->setLoggedIp($comment['ip']);
			$import->setDeletionLogData($this->extractDeletionLogData($comment));

			if ($comment['edit_count'])
			{
				$edits = $this->sourceDb->fetchAll("
					SELECT *
					FROM xf_edit_history
					WHERE content_id = ?
						AND content_type = 'xfmg_comment'
					ORDER BY edit_history_id
				", $oldId);

				if ($edits)
				{
					foreach ($edits AS $edit)
					{
						/** @var \XF\Import\Data\EditHistory $editHistory */
						$editHistory = $this->newHandler('XF:EditHistory');
						$editHistory->bulkSet($this->mapKeys($edit, [
							'edit_date',
							'old_text'
						]));
						$editHistory->content_type = 'xfmg_comment';
						$editHistory->edit_user_id = $this->lookupId('user', $edit['edit_user_id'], 0);

						$import->addHistory($edit['edit_history_id'], $editHistory);
					}
				}
			}

			$embedMetadata = $this->decodeValue($comment['embed_metadata'], 'json-array');
			if (!empty($embedMetadata['galleryEmbeds']))
			{
				$updatedAlbums = [];
				$updatedMedia = [];

				foreach ((array)$embedMetadata['galleryEmbeds']['album'] AS $oldAlbumId)
				{
					$newAlbumId = $this->lookupId('xfmg_album', $oldAlbumId);
					if ($newAlbumId)
					{
						$updatedAlbums[$newAlbumId] = $newAlbumId;
					}
				}
				foreach ((array)$embedMetadata['galleryEmbeds']['media'] AS $oldMediaId)
				{
					$newMediaId = $this->lookupId('xfmg_media', $oldMediaId);
					if ($newMediaId)
					{
						$updatedMedia[$newMediaId] = $newMediaId;
					}
				}

				$embedMetadata['galleryEmbeds']['album'] = $updatedAlbums;
				$embedMetadata['galleryEmbeds']['media'] = $updatedMedia;
			}

			$import->embed_metadata = $embedMetadata;

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

	// ########################### STEP: LIKES ###############################

	public function getStepEndLikes()
	{
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems', 'comments'
		]);

		if (!$contentTypes)
		{
			return 0;
		}

		return $this->getMaxLikeIdForContentTypes($contentTypes);
	}

	public function stepLikes(StepState $state, array $stepConfig, $maxTime)
	{
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems', 'comments'
		]);

		if (!$contentTypes)
		{
			return $state->complete();
		}

		return $this->getLikesStepStateForContentTypes(
			$contentTypes, $state, $stepConfig, $maxTime
		);
	}

	// ########################### STEP: TAGS ###############################

	public function getStepEndTags()
	{
		return $this->getMaxTagContentIdForContentTypes('xfmg_media');
	}

	public function stepTags(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getTagsStepStateForContentTypes('xfmg_media', $state, $stepConfig, $maxTime);
	}

	/**
	 * @param \XFMG\Import\Data\HasWatchTrait $import
	 * @param $oldId
	 */
	protected function handleWatchers(AbstractData $import, $oldId)
	{
		/** @var Watch $watchHelper */
		$watchHelper = $this->getDataHelper('XFMG:Watch');

		list ($columnName, $tableName) = $watchHelper->getTableConfigForType($import->getImportType());

		$watchers = $this->sourceDb->fetchAllKeyed("
			SELECT *
			FROM {$tableName}
			WHERE {$columnName} = ?
		", 'user_id', $oldId);
		if ($watchers)
		{
			$watcherUserIdMap = $this->lookup('user', array_keys($watchers));
			foreach ($watchers AS $watcherUserId => $params)
			{
				if (!empty($watcherUserIdMap[$watcherUserId]))
				{
					$import->addWatcher($watcherUserIdMap[$watcherUserId], $params);
				}
			}
		}
	}

	protected function mapStepsToContentTypes()
	{
		return [
			'categories' => 'xfmg_category',
			'albums' => 'xfmg_album',
			'mediaItems' => 'xfmg_media',
			'ratings' => 'xfmg_rating',
			'comments' => 'xfmg_comment',
		];
	}

	protected function getContentTypesFromRunSteps(array $steps)
	{
		$contentTypes = [];
		$stepsRun = $this->session->getStepsRun();

		foreach ($steps AS $step)
		{
			if (in_array($step, $stepsRun))
			{
				$contentTypes[] = $this->mapStepsToContentTypes()[$step];
			}
		}

		return $contentTypes;
	}
}