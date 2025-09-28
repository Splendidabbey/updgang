<?php

namespace XFMG\Service\Media;

use XFMG\Entity\MediaItem;

class Deleter extends \XF\Service\AbstractService
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $user;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);
		$this->setMediaItem($mediaItem);
	}

	public function setMediaItem(MediaItem $mediaItem)
	{
		$this->mediaItem = $mediaItem;
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
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

		$wasVisible = $this->mediaItem->media_state == 'visible';

		if ($type == 'soft')
		{
			$result = $this->mediaItem->softDelete($reason, $user);
		}
		else
		{
			$result = $this->mediaItem->delete();
		}

		if ($result && $wasVisible && $this->alert && $this->mediaItem->user_id != $user->user_id)
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');
			$mediaRepo->sendModeratorActionAlert($this->mediaItem, 'delete', $this->alertReason);
		}

		return $result;
	}
}