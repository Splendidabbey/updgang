<?php

namespace XFMG\Sitemap;

use XF\Sitemap\AbstractHandler;
use XF\Sitemap\Entry;

class Album extends AbstractHandler
{
	public function getRecords($start)
	{
		$user = \XF::visitor();

		$ids = $this->getIds('xf_mg_album', 'album_id', $start);

		$finder = $this->app->finder('XFMG:Album');
		$albums = $finder
			->where('album_id', $ids)
			->with(['Category.Permissions|' . $user->permission_combination_id])
			->order('album_id')
			->fetch();

		return $albums;
	}

	/**
	 * @param $record \XFMG\Entity\Album
	 *
	 * @return Entry
	 */
	public function getEntry($record)
	{
		$router = $this->app->router('public');
		$url = $router->buildLink('canonical:media/albums', $record);

		return Entry::create($url,[
			'lastmod' => $record->last_update_date,
			'image' => $record->getThumbnailUrl(true)
		]);
	}

	public function isIncluded($record)
	{
		/** @var $record \XFMG\Entity\Album */
		if (!$record->isVisible())
		{
			return false;
		}
		return $record->canView();
	}
}