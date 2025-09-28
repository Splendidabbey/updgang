<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\Util\Arr;
use XFMG\Entity\MediaItem;

class Media extends Repository
{
	/**
	 * @return \XFMG\Finder\MediaItem
	 */
	public function findMediaForMixedList(array $limits = [])
	{
		$limits = array_replace([
			'categoryIds' => null,
			'includePersonalAlbums' => true,
			'visibility' => true,
			'allowOwnPending' => true
		], $limits);

		/** @var \XFMG\Finder\MediaItem $finder */
		$finder = $this->finder('XFMG:MediaItem');

		$finder->inCategoriesIncludePersonalAlbums($limits['categoryIds'], $limits['includePersonalAlbums']);

		if ($limits['visibility'])
		{
			$finder->applyVisibilityLimit($limits['allowOwnPending']);
		}

		$finder->orderByDate();

		return $finder;
	}

	/**
	 * @param mixed $categoryIds
	 * @param array $limits
	 *
	 * @return \XFMG\Finder\MediaItem
	 */
	public function findMediaForIndex($categoryIds = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;

		$finder = $this->findMediaForMixedList($limits);

		return $finder;
	}

	public function findMediaForUser(\XF\Entity\User $user, $categoryIds = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;

		$finder = $this->findMediaForMixedList($limits);
		$finder->byUser($user);

		return $finder;
	}

	/**
	 * @return \XFMG\Finder\MediaItem
	 */
	public function findMediaForList(array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true
		], $limits);

		/** @var \XFMG\Finder\MediaItem $finder */
		$finder = $this->finder('XFMG:MediaItem');

		if ($limits['visibility'])
		{
			$finder->applyVisibilityLimit($limits['allowOwnPending']);
		}

		$finder->orderByDate();

		return $finder;
	}

	public function findMediaInCategory($categoryId, array $limits = [])
	{
		$finder = $this->findMediaForList($limits);
		$finder->inCategory($categoryId);

		return $finder;
	}

	public function findMediaForAlbum($albumId, array $limits = [])
	{
		$finder = $this->findMediaForList($limits);
		$finder->inAlbum($albumId);

		return $finder;
	}

	public function findMediaForWatchedList($categoryIds = null, $userId = null, array $limits = [])
	{
		$limits['categoryIds'] = $categoryIds;
		$limits['visibility'] = false;

		$finder = $this->findMediaForMixedList($limits);

		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		$userId = intval($userId);

		$finder
			->with('Watch|' . $userId, true)
			->with('CommentRead|' . $userId)
			->with('LastComment')
			->with('LastCommenter')
			->where('media_state', 'visible')
			->setDefaultOrder('media_date', 'DESC');

		return $finder;
	}

	public function findMediaForWidget($categoryIds = null, $includePersonalAlbums = false)
	{
		/** @var \XFMG\Finder\MediaItem $finder */
		$finder = $this->finder('XFMG:MediaItem');

		if ($includePersonalAlbums)
		{
			$finder->includePersonalAlbums($categoryIds);
		}
		else
		{
			$finder->inCategory($categoryIds);
		}

		$finder->where('media_state', 'visible');

		return $finder;
	}

	public function findMediaForApi($typeLimit = null, \XFMG\Entity\Category $category = null)
	{
		/** @var \XFMG\Finder\MediaItem $finder */
		$finder = $this->finder('XFMG:MediaItem');
		$finder->with('api')->orderByDate();

		$categoryRepo = $this->repository('XFMG:Category');

		if ($category)
		{
			if ($category->category_type == 'container')
			{
				if (\XF::isApiCheckingPermissions())
				{
					$categoryIds = $categoryRepo->getViewableCategoryIds($category, false);
				}
				else
				{
					$categoryIds = $categoryRepo->getCategoryIds($category, false);
				}
			}
			else
			{
				$categoryIds = [$category->category_id];
			}

			$finder->inCategory($categoryIds);
		}
		else
		{
			$findViewableCategories = true;
			$includePersonalAlbums = true;

			switch ($typeLimit)
			{
				case 'category':
					$finder->where('category_id', '>', 0);
					$includePersonalAlbums = false;
					break;

				case 'personal':
					$finder->where('category_id', 0);
					$findViewableCategories = false;
					break;

			}

			if (\XF::isApiCheckingPermissions())
			{
				if ($findViewableCategories)
				{
					$viewableCategoryIds = $categoryRepo->getViewableCategoryIds();
				}
				else
				{
					$viewableCategoryIds = null;
				}

				$finder->inCategoriesIncludePersonalAlbums($viewableCategoryIds, $includePersonalAlbums);
			}
		}

		if (\XF::isApiCheckingPermissions())
		{
			$finder->applyVisibilityLimit();
		}

		return $finder;
	}

	public function getCurrentPositionInCategory(MediaItem $mediaItem, \XFMG\Entity\Category $category, array $limits = [])
	{
		$finder = $this->findMediaInCategory($category->category_id, $limits);

		$finder->whereOr([
			[
				['media_date', '>', $mediaItem->media_date]
			],
			[
				['media_date', '=', $mediaItem->media_date],
				['media_id', '>', $mediaItem->media_id]
			]
		]);

		return $finder->total();
	}

	public function getCurrentPositionInAlbum(MediaItem $mediaItem, \XFMG\Entity\Album $album, array $limits = [])
	{
		$finder = $this->findMediaForAlbum($album->album_id, $limits);

		$finder->whereOr([
			[
				['media_date', '>', $mediaItem->media_date]
			],
			[
				['media_date', '=', $mediaItem->media_date],
				['media_id', '>', $mediaItem->media_id]
			]
		]);

		return $finder->total();
	}

	public function getMediaTypes()
	{
		return [
			'image' => \XF::phrase('xfmg_media_type.image'),
			'video' => \XF::phrase('xfmg_media_type.video'),
			'audio' => \XF::phrase('xfmg_media_type.audio'),
			'embed' => \XF::phrase('xfmg_media_type.embed')
		];
	}

	protected function getEmbedDataHandlers()
	{
		$handlers = [
			'imgur' => 'XFMG:Imgur',
			'vimeo' => 'XFMG:Vimeo',
			'youtube' => 'XFMG:YouTube'
		];

		$this->app()->fire('xfmg_embed_data_handler_prepare', [&$handlers]);

		return $handlers;
	}

	/**
	 * @param $bbCodeMediaSiteId
	 *
	 * @return \XFMG\EmbedData\BaseData | null
	 */
	public function createEmbedDataHandler($bbCodeMediaSiteId)
	{
		$handlers = $this->getEmbedDataHandlers();

		if (isset($handlers[$bbCodeMediaSiteId]))
		{
			$handlerClass = $handlers[$bbCodeMediaSiteId];
		}
		else
		{
			$handlerClass = 'XFMG\EmbedData\BaseData';
		}

		if (strpos($handlerClass, ':') === false && strpos($handlerClass, '\\') === false)
		{
			$handlerClass = "XFMG:$handlerClass";
		}

		$handlerClass = \XF::stringToClass($handlerClass, '\%s\EmbedData\%s');
		$handlerClass = \XF::extendClass($handlerClass);

		return new $handlerClass($this->app());
	}

	/**
	 * @return string
	 */
	public function generateTempMediaHash()
	{
		$tempFinder = $this->finder('XFMG:MediaTemp');
		$mediaFinder = $this->finder('XFMG:MediaItem');

		do
		{
			$tempMediaHash = md5(microtime(true) . \XF::generateRandomString(8, true));

			$tempFound = $tempFinder->resetWhere()
				->where('media_hash', $tempMediaHash)
				->fetchOne();

			$mediaFound = $mediaFinder->resetWhere()
				->where('media_hash', $tempMediaHash)
				->fetchOne();

			if (!$tempFound && !$mediaFound)
			{
				break;
			}
		}
		while (true);

		return $tempMediaHash;
	}

	public function getMediaTypeFromAttachment(\XF\Entity\Attachment $attachment)
	{
		$data = $attachment->Data;
		if (!$data)
		{
			throw new \InvalidArgumentException("Attachment entity '$attachment->attachment_id' doesn't contain the expected Data relation.");
		}

		$extension = $data->getExtension();

		return $this->getMediaTypeFromExtension($extension);
	}

	public function getMediaTypeFromExtension($extension)
	{
		$options = $this->options();
		
		$imageExtensions = Arr::stringToArray($options->xfmgImageExtensions);
		$videoExtensions = Arr::stringToArray($options->xfmgVideoExtensions);
		$audioExtensions = Arr::stringToArray($options->xfmgAudioExtensions);

		if (in_array($extension, $imageExtensions))
		{
			return 'image';
		}
		else if (in_array($extension, $videoExtensions))
		{
			return 'video';
		}
		else if (in_array($extension, $audioExtensions))
		{
			return 'audio';
		}
		else
		{
			return false;
		}
	}

	public function logMediaView(MediaItem $mediaItem)
	{
		$this->db()->query("
			INSERT INTO xf_mg_media_view
				(media_id, total)
			VALUES
				(? , 1)
			ON DUPLICATE KEY UPDATE
				total = total + 1
		", $mediaItem->media_id);
	}

	public function batchUpdateMediaViews()
	{
		$db = $this->db();
		$db->query("
			UPDATE xf_mg_media_item AS m
			INNER JOIN xf_mg_media_view AS mv ON (m.media_id = mv.media_id)
			SET m.view_count = m.view_count + mv.total
		");
		$db->emptyTable('xf_mg_media_view');
	}

	public function markMediaViewedByVisitor($categoryIds = null, $newViewed = null)
	{
		$finder = $this->findMediaForIndex($categoryIds)
			->unviewedOnly();

		$mediaItems = $finder->fetch();

		foreach ($mediaItems AS $mediaItem)
		{
			$this->markMediaItemViewedByVisitor($mediaItem, $newViewed);
		}
	}

	public function markAllMediaCommentsReadByVisitor($categoryIds = null, $newRead = null)
	{
		$finder = $this->findMediaForIndex($categoryIds)
			->withUnreadCommentsOnly();

		$mediaItems = $finder->fetch();

		foreach ($mediaItems AS $mediaItem)
		{
			$this->markMediaCommentsReadByVisitor($mediaItem, $newRead);
		}
	}

	public function markAlbumMediaViewedByVisitor($albumId, $newViewed = null)
	{
		$finder = $this->findMediaForAlbum($albumId)
			->unviewedOnly();

		$mediaItems = $finder->fetch();

		foreach ($mediaItems AS $mediaItem)
		{
			$this->markMediaItemViewedByVisitor($mediaItem, $newViewed);
		}
	}

	public function markAlbumMediaCommentsReadByVisitor($albumId, $newViewed = null)
	{
		$finder = $this->findMediaForAlbum($albumId)
			->withUnreadCommentsOnly();

		$mediaItems = $finder->fetch();

		foreach ($mediaItems AS $mediaItem)
		{
			$this->markMediaCommentsReadByVisitor($mediaItem, $newViewed);
		}
	}

	public function markMediaItemViewedByVisitor(MediaItem $mediaItem, $newViewed = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($newViewed === null)
		{
			$newViewed = \XF::$time;
		}

		$cutOff = $this->getViewMarkingCutOff();
		if ($newViewed <= $cutOff)
		{
			return false;
		}

		$viewed = $mediaItem->Viewed[$visitor->user_id];
		if ($viewed && $newViewed <= $viewed->media_view_date)
		{
			return false;
		}

		$session = $this->app()->session();
		$mediaUnviewed = $session->get('xfmgUnviewedMedia');
		if (isset($mediaUnviewed['unviewed'][$mediaItem->media_id]))
		{
			unset($mediaUnviewed['unviewed'][$mediaItem->media_id]);
			$session->set('xfmgUnviewedMedia', $mediaUnviewed);
		}

		$this->db()->insert('xf_mg_media_user_view', [
			'media_id' => $mediaItem->media_id,
			'user_id' => $visitor->user_id,
			'media_view_date' => $newViewed
		], false, 'media_view_date = VALUES(media_view_date)');

		return true;
	}

	public function markMediaCommentsReadByVisitor(MediaItem $mediaItem, $newRead = null)
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($newRead === null)
		{
			$newRead = \XF::$time;
		}

		$cutOff = $this->getViewMarkingCutOff();
		if ($newRead <= $cutOff)
		{
			return false;
		}

		$viewed = $mediaItem->CommentRead[$visitor->user_id];
		if ($viewed && $newRead <= $viewed->comment_read_date)
		{
			return false;
		}

		$this->db()->insert('xf_mg_media_comment_read', [
			'media_id' => $mediaItem->media_id,
			'user_id' => $visitor->user_id,
			'comment_read_date' => $newRead
		], false, 'comment_read_date = VALUES(comment_read_date)');

		return true;
	}

	public function getViewMarkingCutOff()
	{
		return \XF::$time - $this->options()->readMarkingDataLifetime * 86400;
	}

	public function pruneMediaViewLogs($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = $this->getViewMarkingCutOff();
		}

		$this->db()->delete('xf_mg_media_user_view', 'media_view_date < ?', $cutOff);
	}

	public function pruneTempMedia($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400;
		}

		/** @var \XFMG\Entity\MediaTemp[] $mediaTempItems */
		$mediaTempItems = $this->finder('XFMG:MediaTemp')
			->where('temp_media_date', '<', $cutOff)
			->fetch(250);

		foreach ($mediaTempItems AS $mediaTemp)
		{
			// cleans up the thumbnail automatically
			$mediaTemp->delete();
		}
	}

	public function pruneTempAttachmentExif($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400;
		}

		$this->db()->delete('xf_mg_attachment_exif', 'attach_date < ?', $cutOff);
	}

	public function sendModeratorActionAlert(MediaItem $mediaItem, $action, $reason = '', array $extra = [])
	{
		if (!$mediaItem->user_id || !$mediaItem->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $mediaItem->title,
			'link' => $this->app()->router('public')->buildLink('nopath:media', $mediaItem),
			'reason' => $reason
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$mediaItem->User,
			0, '',
			'user', $mediaItem->user_id,
			"xfmg_media_{$action}", $extra,
			['dependsOnAddOnId' => 'XFMG']
		);

		return true;
	}

	public function sendTranscodeAlert(array $transcodeData, $success)
	{
		$user = $this->em->find('XF:User', $transcodeData['user_id']);
		if (!$user)
		{
			return;
		}

		if ($success)
		{
			$contentType = 'xfmg_media';
			$contentId = $transcodeData['media_id'];
			$action = 'transcode_success';
		}
		else
		{
			$contentType = 'user';
			$contentId = $user->user_id;
			$action = 'xfmg_transcode_failed';
		}

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$user,
			0, '',
			$contentType, $contentId,
			$action, ['transcodeData' => $transcodeData],
			['autoRead' => $action == 'xfmg_transcode_failed']
		);
	}

	public function getAbstractedWatermarkPath($watermarkHash)
	{
		return sprintf('data://xfmg/watermark/%s.jpg',
			$watermarkHash
		);
	}

	public function getWatermarkAsTempFile()
	{
		$option = $this->app()->options()->xfmgWatermarking;

		return \XF\Util\File::copyAbstractedPathToTempFile(
			$this->getAbstractedWatermarkPath($option['watermark_hash'])
		);
	}

	public function generateRandomMediaCache()
	{
		$limit = 5;
		$iterations = 100;

		$maxId = (int)$this->db()->fetchOne('SELECT MAX(media_id) FROM xf_mg_media_item');

		$mediaIds = [];
		while ($iterations > 0)
		{
			$iterations--;

			$gt = mt_rand(0, max(0, $maxId - $limit));

			$mediaIds = array_merge($mediaIds, $this->db()->fetchAllColumn('
				SELECT media_id
				FROM xf_mg_media_item
				WHERE media_id > ?
				LIMIT ?
			', [$gt, $limit]));
		}

		return array_unique($mediaIds);
	}

	public function addGalleryEmbedsToContent($content, $metadataKey = 'embed_metadata', $mediaGetterKey = 'GalleryMedia', $albumGetterKey = 'GalleryAlbums')
	{
		if (!$content)
		{
			return;
		}

		$mediaIds = [];
		$albumIds = [];
		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['galleryEmbeds']['media']))
			{
				$mediaIds = array_merge($mediaIds, $metadata['galleryEmbeds']['media']);
			}
			if (isset($metadata['galleryEmbeds']['album']))
			{
				$albumIds = array_merge($albumIds, $metadata['galleryEmbeds']['album']);
			}
		}

		$visitor = \XF::visitor();

		$albums = [];
		$mediaItems = [];

		if ($albumIds)
		{
			$albums = $this->finder('XFMG:Album')
				->with('Category.Permissions|' . $visitor->permission_combination_id)
				->whereIds(array_unique($albumIds))
				->fetch();

			foreach ($albums AS $album)
			{
				$mediaIds = array_merge($mediaIds, $album->media_item_cache);
			}
		}

		if ($mediaIds)
		{
			$mediaItems = $this->finder('XFMG:MediaItem')
				->with('Category.Permissions|' . $visitor->permission_combination_id)
				->whereIds(array_unique($mediaIds))
				->orderByDate()
				->fetch();

			foreach ($albums AS $album)
			{
				$mediaCache = [];
				$mediaItemCache = $album->media_item_cache;
				if ($mediaItemCache)
				{
					foreach ($mediaItemCache AS $mediaId)
					{
						if (isset($mediaItems[$mediaId]))
						{
							$mediaCache[$mediaId] = $mediaItems[$mediaId];
						}
					}
				}
				$album->setMediaCache(new ArrayCollection($mediaCache));
			}
		}

		foreach ($content AS $item)
		{
			$metadata = $item->{$metadataKey};
			if (isset($metadata['galleryEmbeds']['media']))
			{
				$galleryMedia = [];
				foreach ($metadata['galleryEmbeds']['media'] AS $id)
				{
					if (!isset($mediaItems[$id]))
					{
						continue;
					}
					$galleryMedia[$id] = $mediaItems[$id];
				}

				$item->{"set$mediaGetterKey"}($galleryMedia);
			}
			if (isset($metadata['galleryEmbeds']['album']))
			{
				$galleryAlbums = [];
				foreach ($metadata['galleryEmbeds']['album'] AS $id)
				{
					if (!isset($albums[$id]))
					{
						continue;
					}
					$galleryAlbums[$id] = $albums[$id];
				}

				$item->{"set$albumGetterKey"}($galleryAlbums);
			}
		}
	}

	public function getUserMediaCount($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_mg_media_item AS item
			LEFT JOIN xf_mg_album AS album ON
				(item.album_id = album.album_id)
			WHERE item.user_id = ?
				AND item.media_state = 'visible'
				AND IF(album.album_id > 0, album.album_state = 'visible', 1=1)
		", $userId);
	}

	public function rebuildImageThumb(MediaItem $mediaItem, $log = true)
	{
		$attachData = $mediaItem->Attachment->Data;
		$imageManager = $this->app()->imageManager();

		if ($imageManager->canResize($attachData->width, $attachData->height))
		{
			$abstractedPath = $mediaItem->getAvailableAbstractedDataPath();
			if (!$abstractedPath || !$this->app()->fs()->has($abstractedPath))
			{
				return false;
			}

			$tempFile = \XF\Util\File::copyAbstractedPathToTempFile($abstractedPath);

			/** @var \XFMG\Service\Media\ThumbnailGenerator $generatorService */
			$generatorService = $this->app()->service('XFMG:Media\ThumbnailGenerator');
			$tempThumb = $generatorService->generateThumbnailFromFile($tempFile);
			if (!$tempThumb)
			{
				return false;
			}

			$thumbPath = $mediaItem->getAbstractedThumbnailPath();
			try
			{
				\XF\Util\File::copyFileToAbstractedPath($tempThumb, $thumbPath);
				return true;
			}
			catch (\Exception $e)
			{
				if ($log)
				{
					$this->app()->logException($e, false, "Image thumb rebuild for media item #{$mediaItem->media_id}: ");
				}
				return false;
			}
		}

		return false;
	}

	public function rebuildFFmpegThumb(MediaItem $mediaItem, $log = true)
	{
		$ffmpegOptions = $this->options()->xfmgFfmpeg;
		if (!$ffmpegOptions['ffmpegPath'] || !$ffmpegOptions['thumbnail'])
		{
			return false;
		}

		$abstractedPath = $mediaItem->getAbstractedDataPath();
		if (!$abstractedPath || !$this->app()->fs()->has($abstractedPath))
		{
			return false;
		}

		/** @var \XFMG\Service\Media\ThumbnailGenerator $generatorService */
		$generatorService = $this->app()->service('XFMG:Media\ThumbnailGenerator');
		$thumbPath = $mediaItem->getAbstractedThumbnailPath();

		try
		{
			$tempFrame = $generatorService->getTempFrameFromFfMpeg($abstractedPath, $mediaItem->media_type);
			if (!$tempFrame)
			{
				return false;
			}

			return $generatorService->getTempThumbnailFromImage($tempFrame, $thumbPath);
		}
		catch (\Exception $e)
		{
			if ($log)
			{
				$this->app()->logException($e, false, ucfirst($mediaItem->media_type) . " thumb rebuild for media item #{$mediaItem->media_id}: ");
			}
			return false;
		}
	}

	public function rebuildFFmpegPoster(MediaItem $mediaItem, $log = true)
	{
		$ffmpegOptions = $this->options()->xfmgFfmpeg;
		if (!$ffmpegOptions['ffmpegPath'] || !$ffmpegOptions['poster'])
		{
			return false;
		}

		$abstractedPath = $mediaItem->getAbstractedDataPath();
		if (!$abstractedPath || !$this->app()->fs()->has($abstractedPath))
		{
			return false;
		}

		/** @var \XFMG\Service\Media\ThumbnailGenerator $generatorService */
		$generatorService = $this->app()->service('XFMG:Media\ThumbnailGenerator');
		$posterPath = $mediaItem->getAbstractedPosterPath();

		try
		{
			if ($mediaItem->custom_thumbnail_date)
			{
				$thumbOrigPath = $mediaItem->getAbstractedCustomThumbnailOriginalPath();
				$tempPoster = \XF\Util\File::copyAbstractedPathToTempFile($thumbOrigPath);
			}
			else
			{
				$tempPoster = $generatorService->getTempFrameFromFfMpeg($abstractedPath, $mediaItem->media_type);
			}

			if (!$tempPoster)
			{
				return false;
			}

			return $generatorService->getTempPosterFromImage($tempPoster, $posterPath);
		}
		catch (\Exception $e)
		{
			if ($log)
			{
				$this->app()->logException($e, false, ucfirst($mediaItem->media_type) . " poster rebuild for media item #{$mediaItem->media_id}: ");
			}
			return false;
		}
	}

	public function rebuildEmbedThumb(MediaItem $mediaItem, $log = true)
	{
		$mediaSiteId = $mediaItem->getMediaSiteId();
		$siteMediaId = $mediaItem->getSiteMediaId();

		if ($mediaSiteId && $siteMediaId)
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->app()->repository('XFMG:Media');

			$embedDataHandler = $mediaRepo->createEmbedDataHandler($mediaSiteId);
			$tempFile = $embedDataHandler->getTempThumbnailPath($mediaItem->media_embed_url, $mediaSiteId, $siteMediaId);

			/** @var \XFMG\Service\Media\ThumbnailGenerator $generatorService */
			$generatorService = $this->app()->service('XFMG:Media\ThumbnailGenerator');
			$tempThumb = $generatorService->generateThumbnailFromFile($tempFile);
			if (!$tempThumb)
			{
				return false;
			}

			$thumbPath = $mediaItem->getAbstractedThumbnailPath();
			try
			{
				\XF\Util\File::copyFileToAbstractedPath($tempThumb, $thumbPath);
				return true;
			}
			catch (\Exception $e)
			{
				if ($log)
				{
					$this->app()->logException($e, false, "Embed thumb rebuild for media item #{$mediaItem->media_id}: ");
				}
				return false;
			}
		}

		return false;
	}

	public function rebuildCustomThumbnail(MediaItem $mediaItem, $log = true)
	{
		$origCustomThumbPath = $mediaItem->getAbstractedCustomThumbnailOriginalPath();
		if (!$this->app()->fs()->has($origCustomThumbPath))
		{
			return false;
		}

		$imageManager = $this->app()->imageManager();

		$tempFile = \XF\Util\File::copyAbstractedPathToTempFile($origCustomThumbPath);
		$imageInfo = getimagesize($tempFile);
		if (!$imageInfo)
		{
			return false;
		}

		$imageType = $imageInfo[2];
		switch ($imageType)
		{
			case IMAGETYPE_GIF:
			case IMAGETYPE_JPEG:
			case IMAGETYPE_PNG:
				break;

			default:
				return false;
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];

		if ($imageManager->canResize($width, $height))
		{
			/** @var \XFMG\Service\Media\ThumbnailGenerator $generatorService */
			$generatorService = $this->app()->service('XFMG:Media\ThumbnailGenerator');
			$tempThumb = $generatorService->generateThumbnailFromFile($tempFile);
			if (!$tempThumb)
			{
				return false;
			}

			$customThumbPath = $mediaItem->getAbstractedCustomThumbnailPath();
			try
			{
				\XF\Util\File::copyFileToAbstractedPath($tempThumb, $customThumbPath);
				return true;
			}
			catch (\Exception $e)
			{
				if ($log)
				{
					$this->app()->logException($e, false, "Custom thumb rebuild for media item #{$mediaItem->media_id}: ");
				}
				return false;
			}
		}

		return false;
	}

	/**
	 * @param string $contentType
	 * @param array  $contentIds
	 *
	 * @return AbstractCollection|\XFMG\XF\Entity\Attachment[]
	 */
	public function getMirroredAttachmentsForContent(string $contentType, array $contentIds)
	{
		return $this->finder('XF:Attachment')
			->where([
				'content_type' => $contentType,
				'content_id' => $contentIds,
				'xfmg_is_mirror_handler' => 1
			])
			->with('Data.XfmgMirrorMedia', true)
			->fetch();
	}
}