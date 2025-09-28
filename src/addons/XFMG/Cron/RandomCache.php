<?php

namespace XFMG\Cron;

class RandomCache
{
	public static function generateRandomMediaCache()
	{
		$cache = \XF::app()->simpleCache()->XFMG;
		$repo = \XF::repository('XFMG:Media');

		$cache->randomMediaCache = $repo->generateRandomMediaCache();
	}

	public static function generateRandomAlbumCache()
	{
		$cache = \XF::app()->simpleCache()->XFMG;
		$repo = \XF::repository('XFMG:Album');

		$cache->randomAlbumCache = $repo->generateRandomAlbumCache();
	}
}