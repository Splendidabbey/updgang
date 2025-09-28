<?php

namespace XFRM\EmailStop;

use XF\EmailStop\AbstractHandler;

class Category extends AbstractHandler
{
	public function getStopOneText(\XF\Entity\User $user, $contentId)
	{
		/** @var \XFRM\Entity\Category|null $category */
		$category = \XF::em()->find('XFRM:Category', $contentId);
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
		/** @var \XFRM\Entity\Category $category */
		$category = \XF::em()->find('XFRM:Category', $contentId);
		if ($category)
		{
			/** @var \XFRM\Repository\CategoryWatch $categoryWatchRepo */
			$categoryWatchRepo = \XF::repository('XFRM:CategoryWatch');
			$categoryWatchRepo->setWatchState($category, $user, 'update', ['send_email' => 0]);
		}
	}

	public function stopAll(\XF\Entity\User $user)
	{
		/** @var \XFRM\Repository\ResourceWatch $resourceWatchRepo */
		$resourceWatchRepo = \XF::repository('XFRM:ResourceWatch');
		$resourceWatchRepo->setWatchStateForAll($user, 'update', ['email_subscribe' => 0]);

		/** @var \XFRM\Repository\CategoryWatch $categoryWatchRepo */
		$categoryWatchRepo = \XF::repository('XFRM:CategoryWatch');
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}