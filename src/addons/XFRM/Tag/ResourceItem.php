<?php

namespace XFRM\Tag;

use XF\Mvc\Entity\Entity;
use XF\Tag\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getPermissionsFromContext(Entity $entity)
	{
		if ($entity instanceof \XFRM\Entity\ResourceItem)
		{
			$resource = $entity;
			$category = $resource->Category;
		}
		else if ($entity instanceof \XFRM\Entity\Category)
		{
			$resource = null;
			$category = $entity;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be a resource or category");
		}

		if ($resource)
		{
			if (
				$resource->isTeamMember() &&
				$resource->hasPermission('manageOthersTagsOwnRes')
			)
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = $resource->hasPermission('manageAnyTag');
			}

			$edit = $resource->canEditTags();
		}
		else
		{
			$removeOthers = false;
			$edit = $category->canEditTags();
		}

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => $category->min_tags
		];
	}

	public function getContentDate(Entity $entity)
	{
		return $entity->resource_date;
	}

	public function getContentVisibility(Entity $entity)
	{
		return $entity->resource_state == 'visible';
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'resource' => $entity,
			'options' => $options
		];
	}

	public function getEntityWith($forView = false)
	{
		$get = ['Category'];
		if ($forView)
		{
			$get[] = 'User';
			$get[] = 'Description';

			$visitor = \XF::visitor();
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceItem $entity */
		return $entity->canUseInlineModeration($error);
	}
}