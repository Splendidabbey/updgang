<?php

namespace XFMG\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Comment extends AbstractHandler
{
	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('XFMG:Comment\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_undelete_comments'),
			'canUndelete',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Comment $entity */
				if ($entity->comment_state == 'deleted')
				{
					$entity->comment_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_approve_comments'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Comment $entity */
				if ($entity->comment_state == 'moderated')
				{
					$entity->comment_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_unapprove_comments'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\Comment $entity */
				if ($entity->comment_state == 'visible')
				{
					$entity->comment_state = 'moderated';
					$entity->save();
				}
			}
		);

		return $actions;
	}

	public function getEntityWith()
	{
		return 'User';
	}
}