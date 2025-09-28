<?php

namespace XFMG\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\ArrayCollection;
use XFMG\Entity\MediaItem;

class FilmStrip extends AbstractPlugin
{
	protected $filmStripLimit = 7;

	protected function getDefaultOffset()
	{
		return floor($this->filmStripLimit / 2);
	}

	public function getFilmStripParamsForView(MediaItem $mediaItem)
	{
		if ($mediaItem->category_id && $mediaItem->Category)
		{
			if ($mediaItem->Category->category_index_limit !== null)
			{
				$limitDays = $mediaItem->Category->category_index_limit;
			}
			else
			{
				$limitDays = $this->options()->xfmgMediaIndexLimit;
			}
		}
		else
		{
			$limitDays = $this->options()->xfmgMediaIndexLimit;
		}

		if ($limitDays && $mediaItem->media_date <= \XF::$time - ($limitDays * 86400))
		{
			return [];
		}

		$limit = $this->filmStripLimit;
		$offset = $this->getDefaultOffset();

		$position = $this->getPositionInContainer($mediaItem);

		$finder = $this->getMediaFinderFromPosition($mediaItem, $position, 2, -$offset - 1);

		$mediaItems = $finder->fetch();
		$mediaIds = $mediaItems->keys();

		$hasPrev = false;
		$hasNext = false;

		$selectedIndex = array_search($mediaItem->media_id, $mediaIds);

		if ($selectedIndex > 3)
		{
			$hasPrev = true;
			$mediaItems = $mediaItems->slice(1);
			$selectedIndex = 3;
			$mediaIds = $mediaItems->keys();
		}

		if ($mediaItems->count() > $limit)
		{
			$hasNext = true;
			$mediaItems = $mediaItems->slice(0, $limit);
			$mediaIds = $mediaItems->keys();
		}

		list($prevItem, $nextItem) = $this->getPrevAndNextItems($mediaItems, $selectedIndex, $mediaIds);

		return [
			'mediaItems' => $mediaItems,
			'firstItem' => $mediaItems->first(),
			'lastItem' => $mediaItems->last(),
			'hasPrev' => $hasPrev,
			'hasNext' => $hasNext,
			'prevItem' => $prevItem,
			'nextItem' => $nextItem,
			'prevPlaceholders' => 0,
			'nextPlaceholders' => 0
		];
	}

	public function getFilmStripParamsForJump(MediaItem $jumpFrom, $direction)
	{
		$limit = $this->filmStripLimit;

		$position = $this->getPositionInContainer($jumpFrom);

		$hasPrev = false;
		$hasNext = false;

		if ($direction == 'prev')
		{
			$limitExtra = 1;
			$offsetExtra = -$limit - 1;
			$hasNext = true;
		}
		else
		{
			$limitExtra = 2;
			$offsetExtra = 0;
			$hasPrev = true;
		}

		$finder = $this->getMediaFinderFromPosition($jumpFrom, $position, $limitExtra, $offsetExtra);

		$mediaItems = $finder->fetch();

		if ($direction == 'prev')
		{
			$mediaIds = $mediaItems->keys();

			$jumpFromIndex = array_search($jumpFrom->media_id, $mediaIds);
			if ($jumpFromIndex)
			{
				$mediaItems = $mediaItems->slice(0, $jumpFromIndex);
			}

			if ($mediaItems->count() > $limit)
			{
				$hasPrev = true;
				$mediaItems = $mediaItems->slice(1);
			}
		}
		else
		{
			$mediaItems = $mediaItems->slice(1);

			if ($mediaItems->count() > $limit)
			{
				$hasNext = true;
				$mediaItems = $mediaItems->slice(0, $limit);
			}
		}

		return [
			'mediaItems' => $mediaItems,
			'firstItem' => $mediaItems->first(),
			'lastItem' => $mediaItems->last(),
			'hasPrev' => $hasPrev,
			'hasNext' => $hasNext,
			'prevPlaceholders' => 0,
			'nextPlaceholders' => 0
		];
	}

	public function getPositionInContainer(MediaItem $mediaItem)
	{
		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		$limits = ['allowOwnPending' => $allowOwnPending];

		if ($mediaItem->album_id)
		{
			return $mediaRepo->getCurrentPositionInAlbum($mediaItem, $mediaItem->Album, $limits);
		}
		else
		{
			return $mediaRepo->getCurrentPositionInCategory($mediaItem, $mediaItem->Category, $limits);
		}
	}

	/**
	 * @param MediaItem $mediaItem
	 * @param $position
	 * @param int $limitExtra
	 * @param int $offsetExtra
	 *
	 * @return \XFMG\Finder\MediaItem
	 */
	public function getMediaFinderFromPosition(MediaItem $mediaItem, $position, $limitExtra = 0, $offsetExtra = 0)
	{
		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');

		$limit = $this->filmStripLimit + $limitExtra;
		$filmStripOffset = $position + $offsetExtra;

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;
		$limits = ['allowOwnPending' => $allowOwnPending];

		if ($mediaItem->album_id)
		{
			$finder = $mediaRepo->findMediaForAlbum($mediaItem->album_id, $limits);
		}
		else
		{

			$finder = $mediaRepo->findMediaInCategory($mediaItem->category_id, $limits);
		}

		return $finder->limit($limit, $filmStripOffset);
	}

	public function getPrevAndNextItems(ArrayCollection $mediaItems, $selectedIndex, array $mediaIds)
	{
		$prevItemId = isset($mediaIds[$selectedIndex - 1]) ? $mediaIds[$selectedIndex - 1] : null;
		$nextItemId = isset($mediaIds[$selectedIndex + 1]) ? $mediaIds[$selectedIndex + 1] : null;

		if ($prevItemId)
		{
			$prevItem = $mediaItems[$prevItemId];
		}
		else
		{
			$prevItem = null;
		}
		if ($nextItemId)
		{
			$nextItem = $mediaItems[$nextItemId];
		}
		else
		{
			$nextItem = null;
		}

		return [$prevItem, $nextItem];
	}

	public function getPrevAndNextPlaceholders(ArrayCollection &$mediaItems, $selectedIndex = 0)
	{
		$limit = $this->filmStripLimit;
		$offset = $this->getDefaultOffset();

		$prevPlaceholders = $offset - $selectedIndex;
		$nextPlaceholders = 0;

		if ($prevPlaceholders)
		{
			$mediaItems = $mediaItems->slice(0, $limit - $prevPlaceholders);
		}

		$count = $mediaItems->count();

		if ($count < $limit + $prevPlaceholders)
		{
			$nextPlaceholders = $limit - $prevPlaceholders - $count;
		}

		return [$prevPlaceholders, $nextPlaceholders];
	}
}