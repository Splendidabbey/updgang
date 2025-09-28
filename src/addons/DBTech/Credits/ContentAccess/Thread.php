<?php

namespace DBTech\Credits\ContentAccess;

use XF\Mvc\Entity\Entity;

/**
 * @package DBTech\Credits\EventTrigger
 */
class Thread extends AbstractHandler
{
	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var \DBTech\Credits\XF\Entity\Thread $entity */

		if ($entity->isVisible() && $entity->dbtech_credits_access_currency_id)
		{
			foreach ($entity->UserPosts as $userPost)
			{
				$this->app()->db()->insert('xf_dbtech_credits_content_access_purchase', [
					'content_type' => 'thread',
					'content_id' => $entity->thread_id,
					'user_id' => $userPost->user_id
				], false, false, 'IGNORE');
			}
		}
	}
}