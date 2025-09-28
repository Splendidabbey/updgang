<?php

namespace XFRM\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceItem extends AbstractHandler
{
	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('XFRM:ResourceItem\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('xfrm_undelete_resources'),
			'canUndelete',
			function(Entity $entity)
			{
				/** @var \XFRM\Entity\ResourceItem $entity */
				if ($entity->resource_state == 'deleted')
				{
					$entity->resource_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('xfrm_approve_resources'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFRM\Entity\ResourceItem $entity */
				if ($entity->resource_state == 'moderated')
				{
					/** @var \XFRM\Service\ResourceItem\Approve $approver */
					$approver = \XF::service('XFRM:ResourceItem\Approve', $entity);
					$approver->setNotifyRunTime(1); // may be a lot happening
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('xfrm_unapprove_resources'),
			'canApproveUnapprove',
			function(Entity $entity)
			{
				/** @var \XFRM\Entity\ResourceItem $entity */
				if ($entity->resource_state == 'visible')
				{
					$entity->resource_state = 'moderated';
					$entity->save();
				}
			}
		);

		$actions['feature'] = $this->getSimpleActionHandler(
			\XF::phrase('xfrm_feature_resources'),
			'canFeatureUnfeature',
			function(Entity $entity)
			{
				/** @var \XFRM\Service\ResourceItem\Feature $featurer */
				$featurer = $this->app->service('XFRM:ResourceItem\Feature', $entity);
				$featurer->feature();
			}
		);

		$actions['unfeature'] = $this->getSimpleActionHandler(
			\XF::phrase('xfrm_unfeature_resources'),
			'canFeatureUnfeature',
			function(Entity $entity)
			{
				/** @var \XFRM\Service\ResourceItem\Feature $featurer */
				$featurer = $this->app->service('XFRM:ResourceItem\Feature', $entity);
				$featurer->unfeature();
			}
		);

		$actions['reassign'] = $this->getActionHandler('XFRM:ResourceItem\Reassign');
		$actions['move'] = $this->getActionHandler('XFRM:ResourceItem\Move');
		$actions['apply_prefix'] = $this->getActionHandler('XFRM:ResourceItem\ApplyPrefix');

		return $actions;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}