<?php

namespace XFMG\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->media_state == 'visible');
	}
}