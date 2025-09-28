<?php

namespace XFMG\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Album extends AbstractHandler
{
	public function reactionsCounted(Entity $entity)
	{
		return ($entity->album_state == 'visible');
	}
}