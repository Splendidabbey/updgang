<?php

namespace XFMG\Sitemap;

use XF\Sitemap\AbstractHandler;
use XF\Sitemap\Entry;

class Media extends AbstractHandler
{
	public function getRecords($start)
	{
		$user = \XF::visitor();

		$ids = $this->getIds('xf_mg_media_item', 'media_id', $start);

		$finder = $this->app->finder('XFMG:MediaItem');
		$mediaItems = $finder
			->where('media_id', $ids)
			->with(['Category.Permissions|' . $user->permission_combination_id])
			->order('media_id')
			->fetch();

		return $mediaItems;
	}

	/**
	 * @param $record \XFMG\Entity\MediaItem
	 *
	 * @return Entry
	 */
	public function getEntry($record)
	{
		$router = $this->app->router('public');
		$url = $router->buildLink('canonical:media', $record);

		$data = [
			'lastmod' => $record->last_edit_date
		];
		if ($record->media_type == 'image')
		{
			$data['image'] = $router->buildLink('canonical:media/full', $record);
		}
		else
		{
			$data['image'] = $record->getThumbnailUrl(true);
		}
		return Entry::create($url,$data);
	}

	public function isIncluded($record)
	{
		/** @var $record \XFMG\Entity\MediaItem */
		if (!$record->isVisible())
		{
			return false;
		}
		return $record->canView();
	}
}