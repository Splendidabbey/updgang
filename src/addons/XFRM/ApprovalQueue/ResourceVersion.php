<?php

namespace XFRM\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class ResourceVersion extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \XFRM\Entity\ResourceVersion */
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

	public function actionApprove(\XFRM\Entity\ResourceVersion $version)
	{
		$this->quickUpdate($version, 'version_state', 'visible');
	}

	public function actionDelete(\XFRM\Entity\ResourceVersion $version)
	{
		$this->quickUpdate($version, 'version_state', 'deleted');
	}

	public function actionSpamClean(\XFRM\Entity\ResourceVersion $version)
	{
		if (!$version->Resource->User)
		{
			return;
		}

		$this->_spamCleanInternal($version->Resource->User);
	}
}