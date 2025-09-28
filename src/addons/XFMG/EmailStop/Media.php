<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;

class Media extends AbstractHandler
{
	public function getStopOneText(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\MediaItem|null $mediaItem */
		$mediaItem = \XF::em()->find('XFMG:MediaItem', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function() use ($mediaItem) { return $mediaItem && $mediaItem->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $mediaItem->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(\XF\Entity\User $user)
	{
		return \XF::phrase('xfmg_stop_notification_emails_from_all_media');
	}

	public function stopOne(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\MediaItem $mediaItem */
		$mediaItem = \XF::em()->find('XFMG:MediaItem', $contentId);
		if ($mediaItem)
		{
			/** @var \XFMG\Repository\MediaWatch $mediaWatchRepo */
			$mediaWatchRepo = \XF::repository('XFMG:MediaWatch');
			$mediaWatchRepo->setWatchState($mediaItem, $user, 'update', ['send_email' => false]);
		}
	}

	public function stopAll(\XF\Entity\User $user)
	{
		/** @var \XFMG\Repository\MediaWatch $mediaWatchRepo */
		$mediaWatchRepo = \XF::repository('XFMG:MediaWatch');
		$mediaWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);

		/** @var \XFMG\Repository\AlbumWatch $albumWatchRepo */
		$albumWatchRepo = \XF::repository('XFMG:AlbumWatch');
		$albumWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);

		/** @var \XFMG\Repository\CategoryWatch $categoryWatchRepo */
		$categoryWatchRepo = \XF::repository('XFMG:CategoryWatch');
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}