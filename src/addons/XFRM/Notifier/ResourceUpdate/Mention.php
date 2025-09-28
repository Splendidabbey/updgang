<?php

namespace XFRM\Notifier\ResourceUpdate;

use XF\Notifier\AbstractNotifier;

class Mention extends AbstractNotifier
{
	/**
	 * @var \XFRM\Entity\ResourceUpdate
	 */
	protected $update;

	public function __construct(\XF\App $app, \XFRM\Entity\ResourceUpdate $update)
	{
		parent::__construct($app);

		$this->update = $update;
	}

	public function canNotify(\XF\Entity\User $user)
	{
		return ($this->update->isVisible() && $user->user_id != $this->update->Resource->user_id);
	}

	public function sendAlert(\XF\Entity\User $user)
	{
		$update = $this->update;
		$resource = $update->Resource;

		return $this->basicAlert(
			$user,
			$resource->user_id,
			$resource->username,
			'resource_update',
			$update->resource_update_id,
			'mention'
		);
	}
}