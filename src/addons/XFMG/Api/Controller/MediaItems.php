<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class MediaItems extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet(ParameterBag $params)
	{
		$page = $this->filterPage();
		$perPage = $this->options()->xfmgAlbumsPerPage;

		$mediaFinder = $this->setupMediaFinder()->limitByPage($page, $perPage);
		$total = $mediaFinder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$media = $mediaFinder->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$media = $media->filterViewable();
		}

		return $this->apiResult([
			'media' => $media->toApiResults(),
			'pagination' => $this->getPaginationData($media, $page, $perPage, $total)
		]);
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFMG\Finder\MediaItem
	 */
	protected function setupMediaFinder(&$filters = [], &$sort = null)
	{
		$mediaFinder = $this->repository('XFMG:Media')->findMediaForApi();

		/** @var \XFMG\Api\ControllerPlugin\MediaItem $mediaPlugin */
		$mediaPlugin = $this->plugin('XFMG:Api:MediaItem');

		$filters = $mediaPlugin->applyMediaListFilters($mediaFinder);
		$sort = $mediaPlugin->applyMediaListSort($mediaFinder);

		return $mediaFinder;
	}

	public function actionPost()
	{
		$albumId = $this->filter('album_id', 'uint');
		$categoryId = $this->filter('category_id', 'uint');

		if ($albumId)
		{
			/** @var \XFMG\Entity\Album $album */
			$album = $this->assertViewableApiRecord('XFMG:Album', $albumId);
			if (\XF::isApiCheckingPermissions() && !$album->canAddMedia($error))
			{
				return $this->noPermission($error);
			}

			$container = $album;
		}
		else if ($categoryId)
		{
			/** @var \XFMG\Entity\Category $category */
			$category = $this->assertViewableApiRecord('XFMG:Category', $categoryId);
			if (\XF::isApiCheckingPermissions() && !$category->canAddMedia($error))
			{
				return $this->noPermission($error);
			}

			$container = $category;
		}
		else
		{
			return $this->requiredInputMissing(['album_id', 'category_id']);
		}

		$adder = $this->setupAddMedia($container);
		if ($adder instanceof \XFMG\Service\Media\Creator)
		{
			if (\XF::isApiCheckingPermissions())
			{
				$adder->checkForSpam();
			}

			if (!$adder->validate($errors))
			{
				return $this->error($errors);
			}

			/** @var \XFMG\Entity\MediaItem $media */
			$media = $adder->save();
			$this->finalizeMedia($adder);

			return $this->apiSuccess([
				'media' => $media->toApiResult(Entity::VERBOSITY_VERBOSE)
			]);
		}
		else if ($adder instanceof \XFMG\Service\Media\TranscodeEnqueuer)
		{
			if (!$adder->validate($errors))
			{
				return $this->error($errors);
			}

			/** @var \XFMG\Entity\MediaItem $media */
			$adder->save();
			$this->finalizeTranscode($adder);

			return $this->apiSuccess([
				'transcoding' => true
			]);
		}
		else
		{
			throw new \LogicException("Did not return the expected service class, got " . get_class($adder));
		}
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $container
	 *
	 * @return \XFMG\Service\Media\Creator|\XFMG\Service\Media\TranscodeEnqueuer
	 */
	protected function setupAddMedia(\XF\Mvc\Entity\Entity $container)
	{
		$embedOutput = null;

		$embedUrl = $this->filter('embed_url', 'str');
		if ($embedUrl)
		{
			$tempCreator = $this->setupEmbedTempCreator($embedUrl, $container, $embedOutput);
			if (!$tempCreator->validate($errors))
			{
				throw $this->errorException($errors);
			}

			/** @var \XFMG\Entity\MediaTemp $mediaTemp */
			$mediaTemp = $tempCreator->save();
		}
		else
		{
			$file = $this->request->getFile('file');
			if (!$file)
			{
				throw $this->exception($this->requiredInputMissing(['embed_url', 'file']));
			}

			$mediaTemp = $this->setupUploadTempMedia($file, $container);
		}

		if ($mediaTemp->attachment_id)
		{
			$attachment = $this->em()->find('XF:Attachment', $mediaTemp->attachment_id, 'Data');
			if (!$attachment)
			{
				throw new \LogicException("Media temp attachment not found");
			}
		}
		else
		{
			$attachment = null;
		}

		$input = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'tags' => 'array-str',
			'custom_fields' => 'array-str'
		]);

		if ($input['title'] === null)
		{
			$input['title'] = $mediaTemp->title;
		}
		if ($input['description'] === null)
		{
			$input['description'] = $mediaTemp->description;
		}

		if ($mediaTemp->requires_transcoding)
		{
			/** @var \XFMG\Service\Media\TranscodeEnqueuer $enqueuer */
			$enqueuer = $this->service('XFMG:Media\TranscodeEnqueuer', $mediaTemp);

			$enqueuer->setContainer($container);
			$enqueuer->setTitle($input['title'], $input['description']);

			if ($container->canEditTags())
			{
				$enqueuer->setTags($input['tags']);
			}

			$enqueuer->setCustomFields($input['custom_fields']);

			if ($mediaTemp->attachment_id)
			{
				$enqueuer->setAttachment($attachment);
			}

			return $enqueuer;
		}
		else
		{
			/** @var \XFMG\Service\Media\Creator $creator */
			$creator = $this->service('XFMG:Media\Creator', $mediaTemp);

			$creator->setContainer($container);
			$creator->setTitle($input['title'], $input['description']);

			if ($container->canEditTags())
			{
				$creator->setTags($input['tags']);
			}

			$creator->setCustomFields($input['custom_fields']);

			if ($mediaTemp->attachment_id)
			{
				$creator->setAttachment($attachment);
			}
			else if ($embedOutput)
			{
				$creator->setMediaSite($embedOutput['url'], $embedOutput['media_tag']);
			}

			return $creator;
		}
	}

	/**
	 * @param $embedUrl
	 * @param Entity $container
	 * @param array $output
	 *
	 * @return \XFMG\Service\Media\TempCreator
	 */
	protected function setupEmbedTempCreator($url, \XF\Mvc\Entity\Entity $container, &$output = [])
	{
		$output = [
			'url' => null,
			'media_site_id' => null,
			'media_site_media_id' => null,
			'media_tag' => null
		];

		$this->validateContainerPermissionCheck($container, 'canEmbedMedia');

		/** @var \XFMG\Service\Media\TempCreator $tempCreator */
		$tempCreator = $this->service('XFMG:Media\TempCreator');

		$matchBbCode = $tempCreator->validateAndSetEmbedUrl($url, $matchError);
		if (!$matchBbCode)
		{
			throw $this->errorException($matchError);
		}

		$output['url'] = $tempCreator->getMediaSiteUrl();
		$output['media_site_id'] = $tempCreator->getMediaSiteId();
		$output['media_site_media_id'] = $tempCreator->getMediaSiteMediaId();
		$output['media_tag'] = $matchBbCode;

		return $tempCreator;
	}

	/**
	 * @param \XF\Http\Upload $file
	 * @param Entity $container
	 *
	 * @return \XFMG\Entity\MediaTemp
	 */
	protected function setupUploadTempMedia(\XF\Http\Upload $file, \XF\Mvc\Entity\Entity $container)
	{
		$this->validateContainerPermissionCheck($container, 'canUploadMedia');

		/** @var \XF\Api\ControllerPlugin\Attachment $attachmentPlugin */
		$attachmentPlugin = $this->plugin('XF:Api:Attachment');

		$handler = $this->repository('XF:Attachment')->getAttachmentHandler('xfmg_media');
		$context = [];
		$tempHash = md5(microtime(true) . \XF::generateRandomString(8, true));

		$attachment = $attachmentPlugin->uploadFile($file, $handler, $context, $tempHash);

		/** @var \XFMG\Entity\MediaTemp $tempMedia */
		$tempMedia = \XF::em()->findOne('XFMG:MediaTemp', ['attachment_id' => $attachment->attachment_id]);

		return $tempMedia;
	}

	protected function validateContainerPermissionCheck(\XF\Mvc\Entity\Entity $container, $permMethod)
	{
		if ($container instanceof \XFMG\Entity\Album)
		{
			if (\XF::isApiCheckingPermissions() && !$container->$permMethod($error))
			{
				throw $this->errorException($error);
			}
		}
		else if ($container instanceof \XFMG\Entity\Category)
		{
			if (\XF::isApiCheckingPermissions() && !$container->$permMethod($error))
			{
				throw $this->errorException($error);
			}
		}
		else
		{
			throw new \LogicException("Unexpected type passed in, got " . get_class($container));
		}
	}

	protected function finalizeMedia(\XFMG\Service\Media\Creator $creator)
	{
		$creator->sendNotifications();

		$mediaItem = $creator->getMediaItem();

		$this->repository('XFMG:Media')->markMediaItemViewedByVisitor($mediaItem);

		/** @var \XFMG\Repository\MediaWatch $watchRepo */
		$watchRepo = $this->repository('XFMG:MediaWatch');
		$watchRepo->autoWatchMediaItem($mediaItem,  \XF::visitor(), true);
	}

	protected function finalizeTranscode(\XFMG\Service\Media\TranscodeEnqueuer $enqueuer)
	{
		$enqueuer->afterInsert();
	}
}