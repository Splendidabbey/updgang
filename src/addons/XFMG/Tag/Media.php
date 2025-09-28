<?php

namespace XFMG\Tag;

use XF\Mvc\Entity\Entity;
use XF\Tag\AbstractHandler;

class Media extends AbstractHandler
{
	public function getPermissionsFromContext(Entity $entity)
	{
		$mediaItem = null;
		$container = null;

		if ($entity instanceof \XFMG\Entity\MediaItem)
		{
			$mediaItem = $entity;
		}
		else if ($entity instanceof \XFMG\Entity\Category || $entity instanceof \XFMG\Entity\Album)
		{
			/** @var \XFMG\Entity\Category $category */
			$container = $entity;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be a media item");
		}

		$visitor = \XF::visitor();

		if ($mediaItem)
		{
			if ($mediaItem->user_id == $visitor->user_id
				&& $mediaItem->hasPermission('manageOthersTagsOwnMedia')
			)
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = $mediaItem->hasPermission('manageAnyTag');
			}

			$edit = $mediaItem->canEditTags();
			$minTags = $mediaItem->min_tags;
		}
		else
		{
			$removeOthers = false;
			$edit = $container ? $container->canEditTags() : false;
			$minTags = $container ? $container->min_tags : 0;
		}

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => $minTags
		];
	}

	public function getContentDate(Entity $entity)
	{
		return $entity->media_date;
	}

	public function getContentVisibility(Entity $entity)
	{
		return $entity->media_state == 'visible';
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'mediaItem' => $entity,
			'options' => $options
		];
	}

	public function getEntityWith($forView = false)
	{
		return [];
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFMG\Entity\MediaItem $entity */
		return $entity->canUseInlineModeration($error);
	}
}