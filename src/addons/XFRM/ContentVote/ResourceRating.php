<?php

namespace XFRM\ContentVote;

use XF\ContentVote\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceRating extends AbstractHandler
{
	public function isCountedForContentUser(Entity $entity)
	{
		/** @var \XFRM\Entity\ResourceRating $entity */

		if ($entity->is_anonymous)
		{
			return false;
		}

		return $entity->isVisible();
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();
		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}
}