<?php

namespace XFMG\Cron;

class Views
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->batchUpdateMediaViews();

		/** @var \XFMG\Repository\Album $albumRepo */
		$albumRepo = $app->repository('XFMG:Album');
		$albumRepo->batchUpdateAlbumViews();
	}
}