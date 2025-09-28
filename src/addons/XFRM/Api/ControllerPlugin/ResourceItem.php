<?php

namespace XFRM\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;

class ResourceItem extends AbstractPlugin
{
	public function applyResourceListFilters(\XFRM\Finder\ResourceItem $resourceFinder, \XFRM\Entity\Category $category = null)
	{
		$filters = [];

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId)
		{
			$resourceFinder->where('prefix_id', $prefixId);
			$filters['prefix_id'] = $prefixId;
		}

		$type = $this->filter('type', 'str');
		if ($type)
		{
			switch ($type)
			{
				case 'free':
					$resourceFinder->where('price', 0);
					$filters['type'] = 'free';
					break;

				case 'paid':
					$resourceFinder->where('price', '>', 0);
					$filters['type'] = 'paid';
					break;
			}
		}

		$creatorId = $this->filter('creator_id', 'uint');
		if ($creatorId)
		{
			$resourceFinder->where('user_id', $creatorId);
			$filters['creator_id'] = $creatorId;
		}

		return $filters;
	}

	public function applyResourceListSort(\XFRM\Finder\ResourceItem $resourceFinder, \XFRM\Entity\Category $category = null)
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
			case 'last_update':
			case 'resource_date':
			case 'rating_weighted':
			case 'download_count':
			case 'title':
				$resourceFinder->order($order, $direction);
				return [$order, $direction];
		}

		return null;
	}
}