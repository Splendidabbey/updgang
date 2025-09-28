<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Comments extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media');
	}

	public function actionGet()
	{
		$commentRepo = $this->repository('XFMG:Comment');

		$finder = $commentRepo->findLatestCommentsForApi();

		$total = $finder->total();
		$page = $this->filterPage();
		$perPage = $this->options()->xfmgCommentsPerPage;

		$this->assertValidApiPage($page, $perPage, $total);

		$comments = $finder->limitByPage($page, $perPage)->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$comments = $comments->filterViewable();
		}

		return $this->apiResult([
			'comments' => $comments->toApiResults(Entity::VERBOSITY_NORMAL, ['with_container' => true]),
			'pagination' => $this->getPaginationData($comments, $page, $perPage, $total)
		]);
	}

	public function actionPost()
	{
		$this->assertRequiredApiInput(['message']);

		$albumId = $this->filter('album_id', 'uint');
		$mediaId = $this->filter('media_id', 'uint');

		if ($albumId)
		{
			/** @var \XFMG\Entity\Album $album */
			$album = $this->assertViewableApiRecord('XFMG:Album', $albumId);
			if (\XF::isApiCheckingPermissions() && !$album->canAddComment($error))
			{
				return $this->noPermission($error);
			}

			$content = $album;
		}
		else if ($mediaId)
		{
			/** @var \XFMG\Entity\MediaItem $media */
			$media = $this->assertViewableApiRecord('XFMG:MediaItem', $mediaId);
			if (\XF::isApiCheckingPermissions() && !$media->canAddComment($error))
			{
				return $this->noPermission($error);
			}

			$content = $media;
		}
		else
		{
			return $this->requiredInputMissing(['album_id', 'media_id']);
		}

		$creator = $this->setupAddComment($content);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var \XFMG\Entity\Comment $comment */
		$comment = $creator->save();
		$this->finalizeComment($creator);

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $content
	 *
	 * @return \XFMG\Service\Comment\Creator
	 */
	protected function setupAddComment(\XF\Mvc\Entity\Entity $content)
	{
		/** @var \XFMG\Service\Comment\Creator $creator */
		$creator = $this->service('XFMG:Comment\Creator', $content);

		$message = $this->filter('message', 'str');
		$creator->setMessage($message);

		return $creator;
	}

	protected function finalizeComment(\XFMG\Service\Comment\Creator $creator)
	{
		$creator->sendNotifications();

		$content = $creator->getContent();
		$visitor = \XF::visitor();

		if ($visitor->user_id != $content->user_id)
		{
			if ($content->content_type == 'xfmg_media')
			{
				/** @var \XFMG\Repository\MediaWatch $watchRepo */
				$watchRepo = $this->repository('XFMG:MediaWatch');
				$watchRepo->autoWatchMediaItem($content, $visitor);
			}
			else
			{
				/** @var \XFMG\Repository\AlbumWatch $watchRepo */
				$watchRepo = $this->repository('XFMG:AlbumWatch');
				$watchRepo->autoWatchAlbum($content, $visitor);
			}
		}
	}
}