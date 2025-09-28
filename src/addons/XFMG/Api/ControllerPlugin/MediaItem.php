<?php

namespace XFMG\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;

class MediaItem extends AbstractPlugin
{
	public function applyMediaListFilters(\XFMG\Finder\MediaItem $mediaFinder, \XFMG\Entity\Category $category = null)
	{
		$filters = [];

		$mediaType = $this->filter('media_type', 'str');
		switch ($mediaType)
		{
			case 'image':
			case 'audio':
			case 'video':
			case 'embed':
				$mediaFinder->where('media_type', $mediaType);
		}

		$userId = $this->filter('user_id', 'uint');
		if ($userId)
		{
			$mediaFinder->where('user_id', $userId);
			$filters['starter_id'] = $userId;
		}

		return $filters;
	}

	public function applyMediaListSort(\XFMG\Finder\MediaItem $mediaFinder, \XFMG\Entity\Category $category = null)
	{
		$order = $this->filter('order', 'str');
		if (!$order)
		{
			return null;
		}

		$direction = $this->filter('direction', 'str');
		if ($direction !== 'asc')
		{
			$direction = 'desc';
		}

		switch ($order)
		{
			case 'media_date':
			case 'comment_count':
			case 'rating_weighted':
			case 'reaction_score':
			case 'view_count':
				$mediaFinder->order($order, $direction);
				return [$order, $direction];
		}

		return null;
	}
}