<?php

namespace XFRM\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \XFRM\Entity\ResourceUpdate */
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return [
			'Resource',
			'Resource.Category',
			'Resource.Category.Permissions|' . $visitor->permission_combination_id,
			'Resource.User'
		];
	}

	public function actionApprove(\XFRM\Entity\ResourceUpdate $update)
	{
		/** @var \XFRM\Service\ResourceUpdate\Approve $approver */
		$approver = \XF::service('XFRM:ResourceUpdate\Approve', $update);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	public function actionDelete(\XFRM\Entity\ResourceUpdate $update)
	{
		$this->quickUpdate($update, 'message_state', 'deleted');
	}

	public function actionSpamClean(\XFRM\Entity\ResourceUpdate $update)
	{
		if (!$update->Resource->User)
		{
			return;
		}

		$this->_spamCleanInternal($update->Resource->User);
	}
}