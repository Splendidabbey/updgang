<?php

namespace XFMG\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Media extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \XFMG\Entity\MediaItem */
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		return ['User'];
	}

	public function actionApprove(\XFMG\Entity\MediaItem $media)
	{
		$this->quickUpdate($media, 'media_state', 'visible');
	}

	public function actionDelete(\XFMG\Entity\MediaItem $media)
	{
		$this->quickUpdate($media, 'media_state', 'deleted');
	}

	public function actionSpamClean(\XFMG\Entity\MediaItem $media)
	{
		if (!$media->User)
		{
			return;
		}

		$this->_spamCleanInternal($media->User);
	}
}