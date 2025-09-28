<?php

namespace AddonFlare\AwardSystem\Help;

use XF\Mvc\Controller;
use XF\Mvc\Reply\View;

class Awards
{
	public static function renderAwards(Controller $controller, View &$response)
	{
		$awardRepo = $controller->repository('AddonFlare\AwardSystem:Award');
		$categoryId = -1;
		$awardData = $awardRepo->getAwardListData();

		$response->setParam('awardData', $awardData);
		$response->setParam('categoryId', $categoryId);
	}
}