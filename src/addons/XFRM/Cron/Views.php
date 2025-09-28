<?php

namespace XFRM\Cron;

class Views
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var \XFRM\Repository\ResourceItem $resourceRepo */
		$resourceRepo = $app->repository('XFRM:ResourceItem');
		$resourceRepo->batchUpdateResourceViews();
	}
}