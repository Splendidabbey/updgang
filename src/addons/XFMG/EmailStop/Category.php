<?php

namespace XFMG\EmailStop;

use XF\EmailStop\AbstractHandler;

class Category extends AbstractHandler
{
	public function getStopOneText(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\Category|null $category */
		$category = \XF::em()->find('XFMG:Category', $contentId);
		$canView = \XF::asVisitor(
			$user,
			function() use ($category) { return $category && $category->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $category->title]);
		}
		else
		{
			return null;
		}
	}

	public function getStopAllText(\XF\Entity\User $user)
	{
		return \XF::phrase('stop_notification_emails_from_all_categories');
	}

	public function stopOne(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFMG\Entity\Category $category */
		$category = \XF::em()->find('XFMG:Category', $contentId);
		if ($category)
		{
			/** @var \XFMG\Repository\CategoryWatch $categoryWatchRepo */
			$categoryWatchRepo = \XF::repository('XFMG:CategoryWatch');
			$categoryWatchRepo->setWatchState($category, $user, 'update', ['send_email' => false]);
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