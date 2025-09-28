<?php

namespace XFMG\Notifier\Media;

use XF\Notifier\AbstractNotifier;

class CategoryWatch extends AbstractNotifier
{
	/**
	 * @var \XFMG\Entity\MediaItem
	 */
	protected $mediaItem;

	public function __construct(\XF\App $app, \XFMG\Entity\MediaItem $mediaItem)
	{
		parent::__construct($app);

		$this->mediaItem = $mediaItem;
	}

	public function canNotify(\XF\Entity\User $user)
	{
		$mediaItem = $this->mediaItem;

		if ($user->user_id == $mediaItem->user_id || $user->isIgnoring($mediaItem->user_id) || !$mediaItem->category_id)
		{
			return false;
		}

		return true;
	}

	public function sendAlert(\XF\Entity\User $user)
	{
		$mediaItem = $this->mediaItem;

		return $this->basicAlert(
			$user,
			$mediaItem->user_id,
			$mediaItem->username,
			'xfmg_media',
			$mediaItem->media_id,
			'insert'
		);
	}

	public function sendEmail(\XF\Entity\User $user)
	{
		if (!$user->email || $user->user_state != 'valid')
		{
			return false;
		}

		$mediaItem = $this->mediaItem;

		$params = [
			'mediaItem' => $mediaItem,
			'category' => $mediaItem->Category,
			'receiver' => $user
		];

		$this->app()->mailer()->newMail()
			->setToUser($user)
			->setTemplate('xfmg_watched_category_media', $params)
			->queue();

		return true;
	}

	public function getDefaultNotifyData()
	{
		$mediaItem = $this->mediaItem;
		$category = $mediaItem->Category;

		if (!$category)
		{
			return [];
		}

		$checkCategories = array_keys($category->breadcrumb_data);
		$checkCategories[] = $category->category_id;

		// Look at any records watching this category or any parent. This will match if the user is watching
		// a parent category with include_children > 0 or if they're watching this category (first whereOr condition).
		$finder = $this->app()->finder('XFMG:CategoryWatch');
		$finder->where('category_id', $checkCategories)
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0)
			->where('notify_on', 'media')
			->whereOr(
				['include_children', '>', 0],
				['category_id', $category->category_id]
			)
			->whereOr(
				['send_alert', '>', 0],
				['send_email', '>', 0]
			);

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
}