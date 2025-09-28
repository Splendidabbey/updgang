<?php

namespace XFMG\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Comment extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->comment_state == 'visible');
	}
}