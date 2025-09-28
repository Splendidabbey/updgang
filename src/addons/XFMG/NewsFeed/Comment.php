<?php

namespace XFMG\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class Comment extends AbstractHandler
{
	public function isPublishable(Entity $entity, $action)
	{
		if ($action == 'insert')
		{
			if ($entity->rating_id)
			{
				// if we're inserting a rating with a comment, we'll publish that via the rating type
				return false;
			}
		}

		return true;
	}

	public function getEntityWith()
	{
		return ['User', 'Rating'];
	}
}