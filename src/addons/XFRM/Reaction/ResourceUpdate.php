<?php

namespace XFRM\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		if (!$entity->Resource || !$entity->Resource->Category)
		{
			return false;
		}

		return ($entity->message_state == 'visible' && $entity->Resource->resource_state == 'visible');
	}

	public function getContentUserId(Entity $entity)
	{
		return $entity->Resource->user_id;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}
}