<?php

namespace XFRM\Service\ResourceUpdate;

use XFRM\Entity\ResourceUpdate;

class Approve extends \XF\Service\AbstractService
{
	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	protected $notifyRunTime = 3;

	public function __construct(\XF\App $app, ResourceUpdate $update)
	{
		parent::__construct($app);
		$this->update = $update;
	}

	public function getUpdate()
	{
		return $this->update;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->update->message_state == 'moderated')
		{
			$this->update->message_state = 'visible';
			$this->update->save();

			$this->onApprove();
			return true;
		}
		else
		{
			return false;
		}
	}

	protected function onApprove()
	{
		// if this is not the last update, then another notification would have been triggered already
		if ($this->update->isLastUpdate())
		{
			/** @var \XFRM\Service\ResourceUpdate\Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $this->update, 'update');
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}