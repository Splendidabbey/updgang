<?php

namespace XFRM\Warning;

use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Warning\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function getStoredTitle(Entity $entity)
	{
		return $entity->Resource ? $entity->Resource->title : '';
	}

	public function getDisplayTitle($title)
	{
		return \XF::phrase('xfrm_resource_review_in_x', ['title' => $title]);
	}

	public function getContentForConversation(Entity $entity)
	{
		return $entity->message;
	}

	public function getContentUser(Entity $entity)
	{
		return $entity->User;
	}

	public function canViewContent(Entity $entity, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */
		return $entity->canView();
	}

	public function onWarning(Entity $entity, Warning $warning)
	{
		$entity->warning_id = $warning->warning_id;
		$entity->save();
	}

	public function onWarningRemoval(Entity $entity, Warning $warning)
	{
		$entity->warning_id = 0;
		$entity->save();
	}

	public function takeContentAction(Entity $entity, $action, array $options)
	{
		if ($action == 'delete')
		{
			$reason = isset($options['reason']) ? $options['reason'] : '';
			if (!is_string($reason))
			{
				$reason = '';
			}

			/** @var \XFRM\Service\ResourceRating\Delete $deleter */
			$deleter = \XF::app()->service('XFRM:ResourceRating\Delete', $entity);
			$deleter->delete('soft', $reason);
		}
	}

	protected function canWarnPublicly(Entity $entity)
	{
		return false;
	}

	protected function canDeleteContent(Entity $entity)
	{
		/** @var \XFRM\Entity\ResourceUpdate $entity */
		return $entity->canDelete('soft');
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();
		return ['User', 'Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}
}