<?php

namespace XFMG\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class Album extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['User', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}