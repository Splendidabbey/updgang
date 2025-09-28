<?php

namespace XFMG\Notifier\Album;

use XF\Notifier\AbstractNotifier;
use XFMG\Entity\Album;

class Mention extends AbstractNotifier
{
	/**
	 * @var Album
	 */
	protected $album;

	public function __construct(\XF\App $app, Album $album)
	{
		parent::__construct($app);

		$this->album = $album;
	}

	public function canNotify(\XF\Entity\User $user)
	{
		return ($this->album->isVisible() && $user->user_id != $this->album->user_id);
	}

	public function sendAlert(\XF\Entity\User $user)
	{
		$album = $this->album;

		return $this->basicAlert(
			$user,
			$album->user_id,
			$album->username,
			'xfmg_album',
			$album->album_id,
			'mention'
		);
	}
}