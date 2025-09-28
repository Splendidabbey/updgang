<?php

namespace XFRM\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

class ResourceUpdate extends AbstractHandler
{
	public function isPublishable(Entity $entity, $action)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */
		if ($action == 'insert')
		{
			// description inserts are handled by the resource
			return $entity->isDescription() ? false : true;
		}

		return true;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.User', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}

	protected function addAttachmentsToContent($content)
	{
		return $this->addAttachments($content);
	}
}