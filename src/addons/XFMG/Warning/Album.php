<?php

namespace XFMG\Warning;

use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Warning\AbstractHandler;

class Album extends AbstractHandler
{
	public function getStoredTitle(Entity $entity)
	{
		return $entity->title;
	}

	public function getDisplayTitle($title)
	{
		return \XF::phrase('xfmg_album_x', ['album' => $title]);
	}

	public function getContentForConversation(Entity $entity)
	{
		return $entity->description;
	}

	public function getContentUser(Entity $entity)
	{
		/** @var \XFMG\Entity\Album $entity */
		return $entity->User;
	}

	public function canViewContent(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\Album $entity */
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
		$entity->warning_message = '';
		$entity->save();
	}

	public function takeContentAction(Entity $entity, $action, array $options)
	{
		if ($action == 'public')
		{
			$message = isset($options['message']) ? $options['message'] : '';
			if (is_string($message) && strlen($message))
			{
				$entity->warning_message = $message;
				$entity->save();
			}
		}
		else if ($action == 'delete')
		{
			$reason = isset($options['reason']) ? $options['reason'] : '';
			if (!is_string($reason))
			{
				$reason = '';
			}

			/** @var \XFMG\Service\Album\Deleter $deleter */
			$deleter = \XF::app()->service('XFMG:Album\Deleter', $entity);
			$deleter->delete('soft', $reason);
		}
	}

	protected function canWarnPublicly(Entity $entity)
	{
		return true;
	}

	protected function canDeleteContent(Entity $entity)
	{
		/** @var \XFMG\Entity\Album $entity */
		return $entity->canDelete('soft');
	}
}