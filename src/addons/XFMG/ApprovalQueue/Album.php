<?php

namespace XFMG\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Album extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \XFMG\Entity\Album */
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		return ['User'];
	}

	public function actionApprove(\XFMG\Entity\Album $album)
	{
		$this->quickUpdate($album, 'album_state', 'visible');
	}

	public function actionDelete(\XFMG\Entity\Album $album)
	{
		$this->quickUpdate($album, 'album_state', 'deleted');
	}

	public function actionSpamClean(\XFMG\Entity\Album $album)
	{
		if (!$album->User)
		{
			return;
		}

		$this->_spamCleanInternal($album->User);
	}
}