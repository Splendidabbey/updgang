<?php

namespace XFRM\Sitemap;

use XF\Sitemap\AbstractHandler;
use XF\Sitemap\Entry;

class ResourceItem extends AbstractHandler
{
	public function getRecords($start)
	{
		$user = \XF::visitor();

		$ids = $this->getIds('xf_rm_resource', 'resource_id', $start);

		$finder = $this->app->finder('XFRM:ResourceItem');
		$resources = $finder
			->where('resource_id', $ids)
			->with(['Category', 'Category.Permissions|' . $user->permission_combination_id])
			->order('resource_id')
			->fetch();

		return $resources;
	}

	public function getEntry($record)
	{
		$url = $this->app->router('public')->buildLink('canonical:resources', $record);
		return Entry::create($url, [
			'lastmod' => $record->last_update
		]);
	}

	public function isIncluded($record)
	{
		/** @var $record \XFRM\Entity\ResourceItem */
		if (!$record->isVisible())
		{
			return false;
		}
		return $record->canView();
	}
}