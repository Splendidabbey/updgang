<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\Finder;

class Comment extends Finder
{
	public function forContent(\XF\Mvc\Entity\Entity $content, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false
		], $limits);

		if ($content instanceof \XFMG\Entity\MediaItem)
		{
			$this->where('content_type', 'xfmg_media');
			$this->where('content_id', $content->media_id);
		}
		else
		{
			$this->where('content_type', 'xfmg_album');
			$this->where('content_id', $content->album_id);
		}

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInContent($content, $limits['allowOwnPending']);
		}

		return $this;
	}

	public function applyVisibilityChecksInContent(\XF\Mvc\Entity\Entity $content, $allowOwnPending = true)
	{
		/** @var \XFMG\Entity\MediaItem | \XFMG\Entity\Album $content */

		$conditions = [];
		$viewableStates = ['visible'];

		if ($content->canViewDeletedComments())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		$visitor = \XF::visitor();
		if ($content->canViewModeratedComments())
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'comment_state' => 'moderated',
				'user_id' => $visitor->user_id
			];
		}

		$conditions[] = ['comment_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	public function whereContainerVisible(\XFMG\Entity\Category $withinCategory = null)
	{
		/** @var \XFMG\Repository\Category $categoryRepo */
		$categoryRepo = $this->app()->repository('XFMG:Category');

		$categoryIds = $categoryRepo->getViewableCategoryIds($withinCategory);
		$includePersonalAlbums = $withinCategory ? false : true;

		$this->whereIf(
			['content_type', 'xfmg_media'],
			function() use ($categoryIds, $includePersonalAlbums)
			{
				/** @var MediaItem $mediaFinder */
				$mediaFinder = $this->Media;
				$mediaFinder->inCategoriesIncludePersonalAlbums($categoryIds, $includePersonalAlbums);
			},
			function() use ($categoryIds, $includePersonalAlbums)
			{
				$this->whereIf(
					['content_type', 'xfmg_album'],
					function() use ($categoryIds, $includePersonalAlbums)
					{
						/** @var Album $albumFinder */
						$albumFinder = $this->Album;
						$albumFinder->inCategoriesIncludePersonalAlbums($categoryIds, $includePersonalAlbums);
					},
					false
				);
			}
		);

		return $this;
	}

	public function orderByDate($direction = 'ASC')
	{
		$this->setDefaultOrder([
			['comment_date', $direction],
			['comment_id', $direction]
		]);

		return $this;
	}

	public function newerThan($date)
	{
		$this->where('comment_date', '>', $date);

		return $this;
	}

	/**
	 * @deprecated Use with('full') instead
	 *
	 * @return $this
	 */
	public function forFullView()
	{
		$this->with('full');

		return $this;
	}
}