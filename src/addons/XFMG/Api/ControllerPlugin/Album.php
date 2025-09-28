<?php

namespace XFMG\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;

class Album extends AbstractPlugin
{
	public function applyAlbumListFilters(\XFMG\Finder\Album $albumFinder, \XFMG\Entity\Category $category = null)
	{
		$filters = [];

		$userId = $this->filter('user_id', 'uint');
		if ($userId)
		{
			$albumFinder->where('user_id', $userId);
			$filters['starter_id'] = $userId;
		}

		return $filters;
	}

	public function applyAlbumListSort(\XFMG\Finder\Album $albumFinder, \XFMG\Entity\Category $category = null)
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
			case 'create_date':
			case 'media_count':
			case 'comment_count':
			case 'rating_weighted':
			case 'reaction_score':
			case 'view_count':
				$albumFinder->order($order, $direction);
				return [$order, $direction];
		}

		return null;
	}
}