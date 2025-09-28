<?php

namespace XFRM\Notifier\ResourceUpdate;

class ResourceWatch extends AbstractWatch
{
	protected function getApplicableActionTypes()
	{
		return ['update'];
	}

	public function getDefaultWatchNotifyData()
	{
		$update = $this->update;

		if ($update->isDescription())
		{
			return [];
		}

		$finder = $this->app()->finder('XFRM:ResourceWatch');

		$finder->where('resource_id', $update->resource_id)
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0);

		$activeLimit = $this->app()->options()->watchAlertActiveOnly;
		if (!empty($activeLimit['enabled']))
		{
			$finder->where('User.last_activity', '>=', \XF::$time - 86400 * $activeLimit['days']);
		}

		$notifyData = [];
		foreach ($finder->fetchColumns(['user_id', 'email_subscribe']) AS $watch)
		{
			$notifyData[$watch['user_id']] = [
				'alert' => true,
				'email' => (bool)$watch['email_subscribe']
			];
		}

		return $notifyData;
	}

	protected function getWatchEmailTemplateName()
	{
		return 'xfrm_watched_resource_update';
	}
}