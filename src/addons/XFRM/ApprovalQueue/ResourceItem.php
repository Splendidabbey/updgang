<?php

namespace XFRM\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceItem extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \XFRM\Entity\ResourceItem */
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id, 'User', 'Description'];
	}

	public function actionApprove(\XFRM\Entity\ResourceItem $resource)
	{
		/** @var \XFRM\Service\ResourceItem\Approve $approver */
		$approver = \XF::service('XFRM:ResourceItem\Approve', $resource);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	public function actionDelete(\XFRM\Entity\ResourceItem $resource)
	{
		$this->quickUpdate($resource, 'resource_state', 'deleted');
	}

	public function actionSpamClean(\XFRM\Entity\ResourceItem $resource)
	{
		if (!$resource->User)
		{
			return;
		}

		$this->_spamCleanInternal($resource->User);
	}
}