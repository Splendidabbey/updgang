<?php

namespace XFMG\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	public function getPossibleActions()
	{
		$actions = [];

		$actions['move'] = $this->getActionHandler('XFMG:Media\Move');

		$actions['delete'] = $this->getActionHandler('XFMG:Media\Delete');

		$actions['add_watermark'] = $this->getActionHandler('XFMG:Media\AddWatermark');
		$actions['remove_watermark'] = $this->getActionHandler('XFMG:Media\RemoveWatermark');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_undelete_media_items'),
			'canUndelete',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\MediaItem $entity */
				if ($entity->media_state == 'deleted')
				{
					$entity->media_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_approve_media_items'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\MediaItem $entity */
				if ($entity->media_state == 'moderated')
				{
					$entity->media_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_unapprove_media_items'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFMG\Entity\MediaItem $entity */
				if ($entity->media_state == 'visible')
				{
					$entity->media_state = 'moderated';
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