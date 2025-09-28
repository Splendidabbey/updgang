<?php

namespace XFMG\Notifier\Media;

use XF\Notifier\AbstractNotifier;

class AlbumWatch extends AbstractNotifier
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

		if ($user->user_id == $mediaItem->user_id || $user->isIgnoring($mediaItem->user_id) || !$mediaItem->album_id)
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
			'album' => $mediaItem->Album,
			'receiver' => $user
		];

		$this->app()->mailer()->newMail()
			->setToUser($user)
			->setTemplate('xfmg_watched_album_media', $params)
			->queue();

		return true;
	}

	public function getDefaultNotifyData()
	{
		$mediaItem = $this->mediaItem;
		$album = $mediaItem->Album;

		if (!$album)
		{
			return [];
		}

		$finder = $this->app()->finder('XFMG:AlbumWatch');
		$finder->where('album_id', $album->album_id)
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0)
			->whereOr(
				['notify_on', 'media_comment'],
				['notify_on', 'media']
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