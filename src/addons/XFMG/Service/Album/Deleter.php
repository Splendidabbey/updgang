<?php

namespace XFMG\Service\Album;

use XFMG\Entity\Album;

class Deleter extends \XF\Service\AbstractService
{
	/**
	 * @var Album
	 */
	protected $album;

	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, Album $album)
	{
		parent::__construct($app);
		$this->setAlbum($album);
	}

	public function setAlbum(Album $album)
	{
		$this->album = $album;
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function setUser(\XF\Entity\User $user = null)
	{
		$this->user = $user;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user ?: \XF::visitor();

		$result = null;

		$wasVisible = $this->album->album_state == 'visible';

		if ($type == 'soft')
		{
			$result = $this->album->softDelete($reason, $user);
		}
		else
		{
			$result = $this->album->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->album->user_id != $user->user_id)
		{
			/** @var \XFMG\Repository\Album $albumRepo */
			$albumRepo = $this->repository('XFMG:Album');
			$albumRepo->sendModeratorActionAlert($this->album, 'delete', $this->alertReason);
		}

		return $result;
	}
}