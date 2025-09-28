<?php

namespace XFMG\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class Rating extends AbstractHandler
{
	public function canViewContent(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\Rating $entity */
		return $entity->canView($error);
	}

	public function getEntityWith()
	{
		return ['Album', 'Media', 'Comment', 'User'];
	}
}