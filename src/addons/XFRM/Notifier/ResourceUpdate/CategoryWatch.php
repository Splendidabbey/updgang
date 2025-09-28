<?php

namespace XFRM\Notifier\ResourceUpdate;

class CategoryWatch extends AbstractWatch
{
	protected function getApplicableActionTypes()
	{
		return ['update', 'resource'];
	}

	public function getDefaultWatchNotifyData()
	{
		$update = $this->update;
		$category = $update->Resource->Category;

		$checkCategories = array_keys($category->breadcrumb_data);
		$checkCategories[] = $category->resource_category_id;

		// Look at any records watching this category or any parent. This will match if the user is watching
		// a parent category with include_children > 0 or if they're watching this category (first whereOr condition).
		$finder = $this->app()->finder('XFRM:CategoryWatch');
		$finder->where('resource_category_id', $checkCategories)
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0)
			->whereOr(
				['include_children', '>', 0],
				['resource_category_id', $category->resource_category_id]
			)
			->whereOr(
				['send_alert', '>', 0],
				['send_email', '>', 0]
			);

		if ($this->actionType == 'update')
		{
			$finder->where('notify_on', 'update');
		}
		else
		{
			$finder->where('notify_on', ['resource', 'update']);
		}

		$activeLimit = $this->app()->options()->watchAlertActiveOnly;
		if (!empty($activeLimit['enabled']))
		{
			$finder->where('User.last_activity', '>=', \XF::$time - 86400 * $activeLimit['days']);
		}

		$notifyData = [];
		foreach ($finder->fetchColumns(['user_id', 'send_alert', 'send_email']) AS $watch)
		{
			$notifyData[$watch['user_id']] = [
				'alert' => (bool)$watch['send_alert'],
				'email' => (bool)$watch['send_email']
			];
		}

		return $notifyData;
	}

	protected function getWatchEmailTemplateName()
	{
		return 'xfrm_watched_category_' . ($this->actionType == 'resource' ? 'resource' : 'update');
	}
}