<?php

namespace XFMG\InlineMod;

use XF\Mvc\Entity\AbstractCollection;

trait AlertSendableTrait
{
	public function canSendAlert(AbstractCollection $entities)
	{
		$userIds = array_unique($entities->pluckNamed('user_id'));
		if (count($userIds) > 1)
		{
			// permission checks have determined user has permission to perform action
			// against items which are owned by multiple users - likely a moderator.
			return true;
		}

		$visitor = \XF::visitor();
		$userId = reset($userIds);

		// performing actions against items owned by a single user
		// if the single user is not the visitor then we can send an alert.
		return ($userId !== $visitor->user_id);
	}
}