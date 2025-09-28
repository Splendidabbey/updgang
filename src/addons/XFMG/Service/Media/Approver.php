<?php

namespace XFMG\Service\Media;

use XFMG\Entity\MediaItem;

class Approver extends \XF\Service\AbstractService
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $notifyRunTime = 3;

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);
		$this->mediaItem = $mediaItem;
	}

	public function getMediaItem()
	{
		return $this->mediaItem;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->mediaItem->media_state == 'moderated')
		{
			$this->mediaItem->media_state = 'visible';
			$this->mediaItem->save();

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
		$mediaItem = $this->mediaItem;

		if ($mediaItem)
		{
			/** @var \XFMG\Service\Media\Notifier $notifier */
			$notifier = $this->service('XFMG:Media\Notifier', $mediaItem);
			$notifier->notifyAndEnqueue($this->notifyRunTime);
		}
	}
}