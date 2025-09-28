<?php

namespace XFMG\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Album extends AbstractHandler
{
	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('XFMG:Album\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_undelete_albums'),
			'canUndelete',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Album $entity */
				if ($entity->album_state == 'deleted')
				{
					$entity->album_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_approve_albums'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Album $entity */
				if ($entity->album_state == 'moderated')
				{
					$entity->album_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_unapprove_albums'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Album $entity */
				if ($entity->album_state == 'visible')
				{
					$entity->album_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['change_privacy'] = $this->getActionHandler('XFMG:Album\ChangePrivacy');
		$actions['move'] = $this->getActionHandler('XFMG:Album\Move');

		return $actions;
	}

	public function getEntityWith()
	{
		return 'User';
	}
}