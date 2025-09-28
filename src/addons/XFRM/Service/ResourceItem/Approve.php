<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Approve extends \XF\Service\AbstractService
{
	/**
	 * @var ResourceItem
	 */
	protected $resource;

	protected $notifyRunTime = 3;

	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->resource->resource_state == 'moderated')
		{
			$this->resource->resource_state = 'visible';
			$this->resource->save();

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
		$description = $this->resource->Description;

		if ($description)
		{
			/** @var \XFRM\Service\ResourceUpdate\Notify $notifier */
			$notifier = $this->service('XFRM:ResourceUpdate\Notify', $description, 'resource');
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}