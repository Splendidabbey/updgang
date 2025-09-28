<?php

namespace XFRM\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function isPublishable(Entity $entity, $action)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */
		if (!$entity->is_review)
		{
			return false;
		}

		return true;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.User', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}
}