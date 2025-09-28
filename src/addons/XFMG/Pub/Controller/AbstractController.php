<?php

namespace XFMG\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

abstract class AbstractController extends \XF\Pub\Controller\AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \XFMG\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!$visitor->canViewMedia())
		{
			throw $this->exception($this->noPermission());
		}

		if ($this->options()->xfmgOverrideStyle)
		{
			$this->setViewOption('style_id', $this->options()->xfmgOverrideStyle);
		}
	}

	protected function postDispatchController($action, ParameterBag $params, AbstractReply &$reply)
	{
		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			$viewParams = $reply->getParams();
			$category = null;

			if (isset($viewParams['album']))
			{
				$category = $viewParams['album']->Category;
			}
			if (isset($viewParams['category']))
			{
				$category = $viewParams['category'];
			}
			if (isset($viewParams['mediaItem']))
			{
				$category = $viewParams['mediaItem']->Category;
			}
			if ($category)
			{
				$reply->setContainerKey('xfmgCategory-' . $category->category_id);
			}
		}
	}

	protected function assertViewableMediaItem($mediaId, array $extraWith = [])
	{
		/** @var \XFMG\Entity\MediaItem $mediaItem */
		$mediaItem = $this->em()->find('XFMG:MediaItem', $mediaId, $extraWith);

		if (!$mediaItem)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfmg_requested_media_item_not_found')));
		}
		if (!$mediaItem->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $mediaItem;
	}

	/**
	 * @param $noteId
	 *
	 * @return \XFMG\Entity\MediaNote
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertMediaNoteExists($noteId)
	{
		/** @var \XFMG\Entity\MediaNote $note */
		$note = $this->em()->find('XFMG:MediaNote', $noteId);

		if (!$note)
		{
			throw $this->exception($this->notFound());
		}

		return $note;
	}

	protected function assertViewableAlbum($albumId, array $extraWith = [])
	{
		/** @var \XFMG\Entity\Album $album */
		$album = $this->em()->find('XFMG:Album', $albumId, $extraWith);

		if (!$album)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfmg_requested_album_not_found')));
		}
		if (!$album->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $album;
	}

	protected function assertViewableCategory($categoryId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;

		/** @var \XFMG\Entity\Category $category */
		$category = $this->em()->find('XFMG:Category', $categoryId, $extraWith);

		if (!$category)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfmg_requested_category_not_found')));
		}
		if (!$category->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $category;
	}

	protected function assertViewableComment($commentId, array $extraWith = [])
	{
		/** @var \XFMG\Entity\Comment $comment */
		$comment = $this->em()->find('XFMG:Comment', $commentId, $extraWith);

		if (!$comment)
		{
			throw $this->exception($this->notFound(\XF::phrase('xfmg_requested_comment_not_found')));
		}
		if (!$comment->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $comment;
	}

	/**
	 * @return \XFMG\Repository\Album
	 */
	protected function getAlbumRepo()
	{
		return $this->repository('XFMG:Album');
	}

	/**
	 * @return \XFMG\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFMG:Category');
	}

	/**
	 * @return \XFMG\Repository\Comment
	 */
	protected function getCommentRepo()
	{
		return $this->repository('XFMG:Comment');
	}

	/**
	 * @return \XFMG\Repository\Media
	 */
	protected function getMediaRepo()
	{
		return $this->repository('XFMG:Media');
	}

	/**
	 * @return \XFMG\Repository\MediaNote
	 */
	protected function getNoteRepo()
	{
		return $this->repository('XFMG:MediaNote');
	}
}