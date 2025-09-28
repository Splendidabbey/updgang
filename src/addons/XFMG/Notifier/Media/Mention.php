<?php

namespace XFMG\Notifier\Media;

use XF\Notifier\AbstractNotifier;
use XFMG\Entity\MediaItem;

class Mention extends AbstractNotifier
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);

		$this->mediaItem = $mediaItem;
	}

	public function canNotify(\XF\Entity\User $user)
	{
		return ($this->mediaItem->isVisible() && $user->user_id != $this->mediaItem->user_id);
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
			'mention'
		);
	}
}