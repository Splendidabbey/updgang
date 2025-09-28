<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;

class Album extends AbstractHandler
{
	public function getStopOneText(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\Album|null $album */
		$album = \XF::em()->find('XFMG:Album', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function() use ($album) { return $album && $album->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $album->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(\XF\Entity\User $user)
	{
		return \XF::phrase('xfmg_stop_notification_emails_from_all_albums');
	}

	public function stopOne(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\Album $album */
		$album = \XF::em()->find('XFMG:Album', $contentId);
		if ($album)
		{
			/** @var \XFMG\Repository\AlbumWatch $albumWatchRepo */
			$albumWatchRepo = \XF::repository('XFMG:AlbumWatch');
			$albumWatchRepo->setWatchState($album, $user, 'update', ['send_email' => false]);
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