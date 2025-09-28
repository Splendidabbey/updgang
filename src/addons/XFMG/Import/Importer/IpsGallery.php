<?php

namespace XFMG\Import\Importer;

use XF\Import\PlatformUtil\Ips;
use XF\Import\StepState;
use XF\Util\File;

class IpsGallery extends AbstractMGImporter
{
	/**
	 * @var \XF\Db\Mysqli\Adapter
	 */
	protected $sourceDb;

	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'Invision Community Gallery 4.x',
		];
	}

	protected function getBaseConfigDefault()
	{
		return Ips::getDefaultImportConfig();
	}

	public function validateBaseConfig(array &$baseConfig, array &$errors)
	{
		Ips::validateImportConfig($baseConfig, $errors, true);
		return $errors ? false : true;
	}

	public function renderBaseConfigOptions(array $vars)
	{
		$vars['db'] = Ips::getDbConfig();
		return $this->app->templater()->renderTemplate('admin:xfmg_import_config_ips_gallery', $vars);
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

	public function getSteps()
	{
		// TODO: Reactions
		return [
			'categories' => [
				'title' => \XF::phrase('xfmg_categories')
			],
			'albums' => [
				'title' => \XF::phrase('xfmg_albums'),
				'depends' => ['categories']
			],
			'mediaItems' => [
				'title' => \XF::phrase('xfmg_media_items'),
				'depends' => ['albums', 'categories']
			],
			'albumRatings' => [
				'title' => \XF::phrase('xfmg_album_ratings'),
				'depends' => ['albums']
			],
			'mediaRatings' => [
				'title' => \XF::phrase('xfmg_media_ratings'),
				'depends' => ['mediaItems']
			],
			'albumComments' => [
				'title' => \XF::phrase('xfmg_album_comments'),
				'depends' => ['albums']
			],
			'mediaComments' => [
				'title' => \XF::phrase('xfmg_media_comments'),
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
		$this->sourceDb = new \XF\Db\Mysqli\Adapter($this->baseConfig['db'], true);

		$this->forumLog = new \XF\Import\Log(
			$this->app->db(), $this->baseConfig['forum_import_log']
		);
	}

	// ############################## STEP: CATEGORIES #########################

	public function stepCategories(StepState $state)
	{
		$categories = $this->sourceDb->fetchAllKeyed("
			SELECT c.*, 
			       lt.word_custom AS title, 
			       ld.word_custom AS description
			FROM gallery_categories AS c
			LEFT JOIN core_sys_lang_words AS lt ON
				(lt.lang_id = 1 AND lt.word_key = CONCAT('gallery_category_', c.category_id))
			LEFT JOIN core_sys_lang_words AS ld ON
				(ld.lang_id = 1 AND ld.word_key = CONCAT('gallery_category_', c.category_id, '_desc'))			
			ORDER BY c.category_id
		", 'category_id');

		$categoryTreeMap = [];
		foreach ($categories AS $categoryId => $category)
		{
			$categoryTreeMap[$category['category_parent_id']][] = $categoryId;
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
				'display_order' => 'category_position',
				'media_count' => 'category_count_imgs'
			]));

			$categoryImport->description = Ips::stripRichText($category['description']);
			$categoryImport->parent_category_id = $newParentId;

			if ($category['category_allow_albums'] > 0)
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

	// ############################## STEP: ALBUMS #########################

	public function getStepEndAlbums()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(album_id) 
			FROM gallery_albums
		") ?: 0;
	}

	public function stepAlbums(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$albums = $this->sourceDb->fetchAll("
			SELECT a.*,
			       m.name,
			       IFNULL(m.joined, UNIX_TIMESTAMP()) AS create_date
			FROM gallery_albums AS a
			LEFT JOIN core_members AS m ON (a.album_owner_id = m.member_id)
			WHERE a.album_id > ? AND a.album_id <= ?
			ORDER BY a.album_id
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$albums)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($albums, 'album_owner_id');
		$this->lookup('user', $mapUserIds);

		$mapCategoryIds = array_column($albums, 'album_category_id');
		$this->lookup('xfmg_category', $mapCategoryIds);

		foreach ($albums AS $album)
		{
			$oldId = $album['album_id'];
			$state->startAfter = $oldId;

			/** @var \XFMG\Import\Data\Album $albumImport */
			$albumImport = $this->newHandler('XFMG:Album');

			$userId = $this->lookupId('user', $album['album_owner_id'], 0);
			$categoryId = $this->lookupId('xfmg_category', $album['album_category_id'], 0);

			$createDate = $this->sourceDb->fetchOne("
				SELECT image_date
				FROM gallery_images
				WHERE image_album_id = ?
				ORDER BY image_id ASC
				LIMIT 1
			", $oldId) ?: $album['create_date'];

			$albumImport->bulkSet([
				'title' => $album['album_name'],
				'user_id' => $userId,
				'username' => $album['name'],
				'create_date' => $createDate
			]);

			$albumImport->description = Ips::stripRichText($album['album_description']);

			$viewPrivacy = null;
			switch ($album['album_type'])
			{
				case 1:
					if ($categoryId)
					{
						$albumImport->view_privacy = 'inherit';
						$albumImport->category_id = $categoryId;
					}
					else
					{
						$albumImport->view_privacy = 'public';
					}
					break;
				case 2:
					$albumImport->view_privacy = 'private';
					$albumImport->category_id = 0; // we do not support privacy in category albums
					break;
				case 3:
					$albumImport->view_privacy = 'shared';
					$albumImport->category_id = 0;  // we do not support privacy in category albums

					$groupMemberIds = $this->sourceDb->fetchAllColumn("
						SELECT member_id
						FROM core_sys_social_group_members
						WHERE group_id = ?
					", $album['album_allowed_access']);

					$albumImport->view_users = $this->lookup('user', $groupMemberIds);
					break;
			}

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
		return $this->sourceDb->fetchOne("
			SELECT MAX(image_id)
			FROM gallery_images
		") ?: 0;
	}

	public function stepMediaItems(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$mediaItems = $this->sourceDb->fetchAll("
			SELECT
				i.*,
			    c.category_allow_albums,
			    a.album_type,
				m.name,
				IFNULL(m.joined, UNIX_TIMESTAMP()) AS create_date
			FROM gallery_images AS i
			LEFT JOIN gallery_categories AS c ON (i.image_category_id = c.category_id)
			LEFT JOIN gallery_albums AS a ON (i.image_album_id = a.album_id)
			LEFT JOIN core_members AS m ON (i.image_member_id = m.member_id)
			WHERE i.image_id > ? AND i.image_id <= ?
			ORDER BY i.image_id
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
			$mapUserIds[] = $mediaItem['image_member_id'];
			$mapAlbumIds[] = $mediaItem['image_album_id'];
			$mapCategoryIds[] = $mediaItem['image_category_id'];
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_album', $mapAlbumIds);
		$this->lookup('xfmg_category', $mapCategoryIds);

		// special case logging - maps user IDs to specially created album IDs
		$this->lookup('xfmg_member_album', $mapUserIds);

		foreach ($mediaItems AS $mediaItem)
		{
			$oldId = $mediaItem['image_id'];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $mediaItem['image_member_id'], 0);
			$albumId = $this->lookupId('xfmg_album', $mediaItem['image_album_id']);
			$categoryId = $this->lookupId('xfmg_category', $mediaItem['image_category_id']);

			if (!$albumId && $mediaItem['category_allow_albums'] == 1)
			{
				// media item in mixed type category, need to create new album (if not already created)
				$albumId = $this->lookupId('xfmg_member_album', $mediaItem['image_member_id']);

				if (!$albumId)
				{
					/** @var \XFMG\Import\Data\Album $albumImport */
					$albumImport = $this->newHandler('XFMG:Album');

					$createDate = $this->sourceDb->fetchOne("
						SELECT image_date
						FROM gallery_images
						WHERE image_category_id = ?
						ORDER BY image_id ASC
						LIMIT 1
					", $mediaItem['image_category_id']) ?: $mediaItem['create_date'];

					$albumImport->bulkSet([
						'title' => $mediaItem['name'],
						'username' => $mediaItem['name'],
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
						$this->log('xfmg_member_album', $mediaItem['image_member_id'], $albumId);
					}
				}
			}

			// if we somehow have an image from a private/shared album inside a category
			// we created the album as a personal album so don't import the category ID
			if ($albumId && $categoryId && $mediaItem['album_type'] > 1)
			{
				$categoryId = 0;
			}

			if (!$categoryId && !$albumId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\MediaItem $mediaImport */
			$mediaImport = $this->newHandler('XFMG:MediaItem');

			$mediaImport->bulkSet([
				'title' => $mediaItem['image_caption'],
				'description' => Ips::stripRichText($mediaItem['image_description']),
				'user_id' => $userId,
				'username' => $mediaItem['name'],
				'view_count' => $mediaItem['image_views'],
				'media_date' => $mediaItem['image_date'],
				'album_id' => $albumId,
				'category_id' => $categoryId
			]);

			$sourceFile = null;

			$filename = $mediaItem['image_original_file_name'];
			$extension = File::getFileExtension($filename);

			list ($mediaType, $filePath) = $this->getMediaTypeAndFilePathFromExtension($extension);

			if (!$mediaType)
			{
				continue;
			}

			$mediaImport->media_type = $mediaType;

			$sourceFile = sprintf(
				'%s/uploads/%s',
				$this->baseConfig['ips_path'],
				$filename
			);

			if (!file_exists($sourceFile) || !is_readable($sourceFile))
			{
				continue;
			}

			/** @var \XF\Import\Data\Attachment $attachmentImport */
			$attachmentImport = $this->newHandler('XF:Attachment');

			$attachmentImport->attach_date = $mediaItem['image_date'];
			$attachmentImport->content_type = 'xfmg_media';
			$attachmentImport->unassociated = false;

			$attachmentImport->setDataExtra('upload_date', $mediaItem['image_date']);
			if ($filePath)
			{
				$attachmentImport->setDataExtra('file_path', $filePath);
			}
			$attachmentImport->setDataUserId($userId);
			$attachmentImport->setSourceFile($sourceFile, $mediaItem['image_file_name']);

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

	// ############################## RATINGS HELPERS #########################

	protected function getStepEndRatingsForType($table, $primaryKey = 'review_id')
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX($primaryKey)
			FROM $table
		") ?: 0;
	}

	protected function runStepRatingsForType(StepState $state, array $stepConfig, $maxTime, $contentType, $primaryKey = 'review_id', $limit = 500)
	{
		if ($contentType == 'xfmg_album')
		{
			$table = 'gallery_album_reviews';
			$contentIdField = 'review_album_id';
		}
		else
		{
			$table = 'gallery_reviews';
			$contentIdField = 'review_image_id';
		}

		$timer = new \XF\Timer($maxTime);

		$ratings = $this->sourceDb->fetchAll("
			SELECT
				r.*,
				IF(m.name IS NULL, r.review_author_name, m.name) AS username
			FROM {$table} AS r
			LEFT JOIN core_members AS m ON (r.review_author = m.member_id)
			WHERE r.{$primaryKey} > ? AND r.{$primaryKey} <= ?
			ORDER BY r.{$primaryKey}
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$ratings)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($ratings, 'review_author');
		$mapContentIds = array_column($ratings, $contentIdField);

		$this->lookup('user', $mapUserIds);
		$this->lookup($contentType, $mapContentIds);

		foreach ($ratings AS $rating)
		{
			$oldId = $rating[$primaryKey];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $rating['review_author']);
			$contentId = $this->lookupId($contentType, $rating[$contentIdField]);

			if (!$contentId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Rating $import */
			$import = $this->newHandler('XFMG:Rating');

			$import->bulkSet([
				'username' => $rating['username'],
				'rating_date' => $rating['review_date'],
				'rating' => $rating['review_rating'],
				'content_id' => $contentId,
				'content_type' => $contentType,
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

	// ############################## STEP: ALBUM RATINGS #########################

	public function getStepEndAlbumRatings()
	{
		return $this->getStepEndRatingsForType('gallery_album_reviews');
	}

	public function stepAlbumRatings(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->runStepRatingsForType($state, $stepConfig, $maxTime, 'xfmg_album');
	}

	// ############################## STEP: MEDIA COMMENTS #########################

	public function getStepEndMediaRatings()
	{
		return $this->getStepEndRatingsForType('gallery_reviews');
	}

	public function stepMediaRatings(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->runStepRatingsForType($state, $stepConfig, $maxTime, 'xfmg_media');
	}

	// ############################## COMMENTS HELPERS #########################

	protected function getStepEndCommentsForType($table, $primaryKey = 'comment_id')
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX($primaryKey)
			FROM $table
		") ?: 0;
	}

	protected function runStepCommentsForType(StepState $state, array $stepConfig, $maxTime, $contentType, $primaryKey = 'comment_id', $limit = 500)
	{
		if ($contentType == 'xfmg_album')
		{
			$table = 'gallery_album_comments';
			$contentIdField = 'comment_album_id';
		}
		else
		{
			$table = 'gallery_comments';
			$contentIdField = 'comment_img_id';
		}

		$timer = new \XF\Timer($maxTime);

		$comments = $this->sourceDb->fetchAll("
			SELECT
				c.*,
				IF(m.name IS NULL, c.comment_author_name, m.name) AS username
			FROM {$table} AS c
			LEFT JOIN core_members AS m ON (c.comment_author_id = m.member_id)
			WHERE c.{$primaryKey} > ? AND c.{$primaryKey} <= ?
			ORDER BY c.{$primaryKey}
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);

		if (!$comments)
		{
			return $state->complete();
		}

		$mapUserIds = [];
		$mapContentIds = [];
		$mapRatingIds = [];

		foreach ($comments AS $comment)
		{
			$mapUserIds[] = $comment['comment_author_id'];
			$mapContentIds[] = $comment[$contentIdField];
			if (isset($comment['rating_id']))
			{
				$mapRatingIds[] = $comment['rating_id'];
			}
		}

		$this->lookup('user', $mapUserIds);
		$this->lookup($contentType, $mapContentIds);
		$this->lookup('xfmg_rating', $mapRatingIds);

		foreach ($comments AS $comment)
		{
			$oldId = $comment[$primaryKey];
			$state->startAfter = $oldId;

			$userId = $this->lookupId('user', $comment['comment_author_id'], 0);
			$contentId = $this->lookupId($contentType, $comment[$contentIdField]);

			$ratingId = 0;
			if (isset($comment['rating_id']))
			{
				$ratingId = $this->lookupId('xfmg_rating', $comment['rating_id'], 0);
			}

			if (!$contentId)
			{
				continue;
			}

			/** @var \XFMG\Import\Data\Comment $import */
			$import = $this->newHandler('XFMG:Comment');

			$message = Ips::convertContentToBbCode($comment['comment_text'], 'xfmg-comment');

			$import->bulkSet([
				'username' => $comment['username'],
				'comment_date' => $comment['comment_post_date'],
				'message' => $comment['comment_text'],
				'content_id' => $contentId,
				'content_type' => $contentType,
				'user_id' => $userId,
				'rating_id' => $ratingId
			]);

			$helper = $this->getHelper();

			$quotedMessageIds = $helper->getQuotedContentIds($message, 'xfmg-comment');
			$mentionedUserIds = $helper->getMentionedUserIds($message);

			$this->lookup('xfmg_comment', $quotedMessageIds);
			$this->lookup('user', $mentionedUserIds);

			$message = $helper->rewriteQuotesInBbCode($message, 'xfmg-comment', 'xfmg_comment');
			$message = $helper->rewriteMentionsInBbCode($message);
			$import->message = $message;

			if ($comment['comment_ip_address'])
			{
				$import->setLoggedIp($comment['comment_ip_address']);
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

	// ############################## STEP: ALBUM COMMENTS #########################

	public function getStepEndAlbumComments()
	{
		return $this->getStepEndCommentsForType('gallery_album_comments');
	}

	public function stepAlbumComments(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->runStepCommentsForType($state, $stepConfig, $maxTime, 'xfmg_album');
	}

	// ############################## STEP: MEDIA COMMENTS #########################

	public function getStepEndMediaComments()
	{
		return $this->getStepEndCommentsForType('gallery_comments');
	}

	public function stepMediaComments(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->runStepCommentsForType($state, $stepConfig, $maxTime, 'xfmg_media');
	}

	// ########################### STEP: TAGS ###############################

	public function getStepEndContentTags()
	{
		return $this->sourceDb->fetchOne("
			SELECT MAX(tag_id)
			FROM core_tags
			WHERE tag_meta_app = 'gallery'
			AND tag_meta_area = 'gallery'
		") ?: 0;
	}

	public function stepContentTags(StepState $state, array $stepConfig, $maxTime)
	{
		$limit = 500;
		$timer = new \XF\Timer($maxTime);

		$tags = $this->sourceDb->fetchAll("
			SELECT *
			FROM core_tags
			WHERE tag_id > ? AND tag_id <= ?
			AND tag_meta_app = 'gallery' AND tag_meta_area = 'gallery'
			ORDER BY tag_id
			LIMIT {$limit} 
		", [$state->startAfter, $state->end]);

		if (!$tags)
		{
			return $state->complete();
		}

		$mapUserIds = array_column($tags, 'tag_member_id');
		$mapMediaIds = array_column($tags, 'tag_meta_id');

		$this->lookup('user', $mapUserIds);
		$this->lookup('xfmg_media', $mapMediaIds);

		/** @var \XF\Import\DataHelper\Tag $tagHelper */
		$tagHelper = $this->getDataHelper('XF:Tag');

		foreach ($tags AS $tag)
		{
			$state->startAfter = $tag['tag_id'];

			if (!$newMediaId = $this->lookupId('xfmg_media', $tag['tag_meta_id']))
			{
				continue;
			}

			$mediaItem = $this->em()->find('XFMG:MediaItem', $newMediaId);
			if (!$mediaItem)
			{
				continue;
			}

			$userId = $this->lookupId('user', $tag['tag_member_id'], 0);

			$tagText = trim($tag['tag_text']);

			$newId = $tagHelper->importTag($tagText, 'xfmg_media', $newMediaId, [
				'add_user_id' => $userId,
				'add_date' => $tag['tag_added'],
				'visible' => $mediaItem->media_state == 'visible',
				'content_date' => $mediaItem->media_date
			]);

			if ($newId)
			{
				$state->imported++;
			}

			$this->em()->detachEntity($mediaItem);

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		return $state->resumeIfNeeded();
	}
}