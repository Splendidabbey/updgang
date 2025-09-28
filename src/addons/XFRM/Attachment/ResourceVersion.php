<?php

namespace XFRM\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class ResourceVersion extends AbstractHandler
{
	public function getContainerWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceVersion $container */
		if (!$container->canView($error))
		{
			return false;
		}

		return $container->canDownload($error);
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$em = \XF::em();

		if (!empty($context['resource_id']))
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			$resource = $em->find('XFRM:ResourceItem', intval($context['resource_id']), ['Category']);
			if (!$resource || !$resource->canView() || !$resource->canReleaseUpdate())
			{
				return false;
			}

			return true;
		}
		else if (!empty($context['resource_category_id']))
		{
			/** @var \XFRM\Entity\Category $category */
			$category = $em->find('XFRM:Category', intval($context['resource_category_id']));
			if (!$category || !$category->canView() || !$category->canAddResource())
			{
				return false;
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		/** @var \XFRM\Entity\ResourceVersion $container */
		$container->file_count--;
		$container->save();

		\XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
	}

	public function getConstraints(array $context)
	{
		return \XF::repository('XFRM:ResourceVersion')->getVersionAttachmentConstraints();
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['resource_version_id']) ? intval($context['resource_version_id']) : null;
	}

	public function getContext(Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof \XFRM\Entity\ResourceItem)
		{
			$extraContext['resource_id'] = $entity->resource_id;
		}
		else if ($entity instanceof \XFRM\Entity\Category)
		{
			$extraContext['resource_category_id'] = $entity->resource_category_id;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be resource or category");
		}

		return $extraContext;
	}
}