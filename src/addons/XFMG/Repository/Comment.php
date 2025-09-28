<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Repository;

class Comment extends Repository
{
	public function findCommentsForContent(\XF\Mvc\Entity\Entity $content, array $limits = [])
	{
		/** @var \XFMG\Finder\Comment $finder */
		$finder = $this->finder('XFMG:Comment');
		$finder
			->forContent($content, $limits)
			->with(['full', 'Rating'])
			->orderByDate();

		return $finder;
	}

	public function findCommentsForApi(\XF\Mvc\Entity\Entity $content)
	{
		$limits = [];
		if (\XF::isApiBypassingPermissions())
		{
			$limits['visibility'] = false;
		}

		/** @var \XFMG\Finder\Comment $finder */
		$finder = $this->finder('XFMG:Comment');
		$finder
			->forContent($content, $limits)
			->orderByDate()
			->with('api');

		return $finder;
	}

	public function findLatestCommentsForContent(\XF\Mvc\Entity\Entity $content, $newerThan, array $limits = [])
	{
		/** @var \XFMG\Finder\Comment $finder */
		$finder = $this->finder('XFMG:Comment');
		$finder
			->forContent($content, $limits)
			->orderByDate('DESC')
			->newerThan($newerThan)
			->with('full');

		return $finder;
	}

	public function findNextCommentsInContent(\XF\Mvc\Entity\Entity $content, $newerThan, array $limits = [])
	{
		/** @var \XFMG\Finder\Comment $finder */
		$finder = $this->finder('XFMG:Comment');
		$finder
			->forContent($content, $limits)
			->orderByDate()
			->newerThan($newerThan);

		return $finder;
	}

	public function findLatestCommentsForWidget()
	{
		$finder = $this->finder('XFMG:Comment');

		$finder
			->where('comment_state', 'visible')
			->where('comment_date', '>', $this->getReadMarkingCutOff())
			->orderByDate('DESC')
			->with([
				'Album.Category',
				'Media.Album',
				'Media.Category',
				'Rating'
			])
			->indexHint('FORCE', 'comment_date');

		$finder->whereIf(
			['content_type', 'xfmg_media'],
			function () use ($finder)
			{
				/** @var \XFMG\Finder\MediaItem $mediaFinder */
				$mediaFinder = $finder->Media;
				$mediaFinder->where('media_state', 'visible');
				$mediaFinder->visibleAlbumsOnly();
			},
			function () use ($finder)
			{
				$finder->whereIf(
					['content_type', 'xfmg_album'],
					function () use ($finder)
					{
						$albumFinder = $finder->Album;
						$albumFinder->where('album_state', 'visible');
					},
					false
				);
			}
		);

		return $finder;
	}

	public function findLatestCommentsForApi(\XFMG\Entity\Category $withinCategory = null)
	{
		/** @var \XFMG\Finder\Comment $finder */
		$finder = $this->finder('XFMG:Comment');

		$finder->where('comment_state', 'visible')
			->with('api|container')
			->orderByDate('DESC');

		$finder->where('comment_date', '>', $this->getReadMarkingCutOff());

		if (\XF::isApiCheckingPermissions())
		{
			$finder->whereContainerVisible($withinCategory);
		}

		return $finder;
	}

	public function getReadMarkingCutOff()
	{
		return \XF::$time - $this->options()->readMarkingDataLifetime * 86400;
	}

	public function sendModeratorActionAlert(\XFMG\Entity\Comment $comment, $action, $reason = '', array $extra = [])
	{
		if (!$comment->user_id || !$comment->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $comment->Content->title,
			'link' => $this->app()->router('public')->buildLink('nopath:media/comments', $comment),
			'reason' => $reason,
			'content_type' => $comment->content_type
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$comment->User,
			0, '',
			'user', $comment->user_id,
			"xfmg_comment_{$action}", $extra,
			['dependsOnAddOnId' => 'XFMG']
		);

		return true;
	}
}