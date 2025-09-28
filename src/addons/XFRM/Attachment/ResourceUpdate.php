<?php

namespace XFRM\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class ResourceUpdate extends AbstractHandler
{
	public function getContainerWith()
	{
		$visitor = \XF::visitor();

		return ['Resource', 'Resource.Category', 'Resource.Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		/** @var \XFRM\Entity\ResourceUpdate $container */
		if (!$container->canView())
		{
			return false;
		}

		return $container->Resource->canViewUpdateImages();
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$category = $this->getCategoryFromContext($context);

		return $category && $category->canUploadAndManageUpdateImages();
	}

	public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		/** @var \XFRM\Entity\ResourceUpdate $container */
		$container->attach_count--;
		$container->save();

		\XF::app()->logger()->logModeratorAction($this->contentType, $container, 'attachment_deleted', [], false);
	}

	public function getConstraints(array $context)
	{
		/** @var \XF\Repository\Attachment $attachRepo */
		$attachRepo = \XF::repository('XF:Attachment');

		$constraints = $attachRepo->getDefaultAttachmentConstraints();
		$constraints['extensions'] = ['jpg', 'jpeg', 'jpe', 'png', 'gif'];

		$category = $this->getCategoryFromContext($context);
		if ($category && $category->canUploadVideos())
		{
			$constraints = $attachRepo->applyVideoAttachmentConstraints($constraints);
		}

		return $constraints;
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['resource_update_id']) ? intval($context['resource_update_id']) : null;
	}

	public function getContext(Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof \XFRM\Entity\ResourceUpdate)
		{
			$extraContext['resource_update_id'] = $entity->resource_update_id;
		}
		else if ($entity instanceof \XFRM\Entity\ResourceItem)
		{
			$extraContext['resource_id'] = $entity->resource_id;
		}
		else if ($entity instanceof \XFRM\Entity\Category)
		{
			$extraContext['resource_category_id'] = $entity->resource_category_id;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be resource update, resource or category");
		}

		return $extraContext;
	}

	protected function getCategoryFromContext(array $context)
	{
		$em = \XF::em();

		if (!empty($context['resource_update_id']))
		{
			/** @var \XFRM\Entity\ResourceUpdate $update */
			$update = $em->find('XFRM:ResourceUpdate', intval($context['resource_update_id']), ['Resource', 'Resource.Category']);
			if (!$update || !$update->canView() || !$update->canEdit())
			{
				return null;
			}

			$category = $update->Resource->Category;
		}
		else if (!empty($context['resource_id']))
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			$resource = $em->find('XFRM:ResourceItem', intval($context['resource_id']), ['Category']);
			if (!$resource || !$resource->canView() || !$resource->canReleaseUpdate())
			{
				return null;
			}

			$category = $resource->Category;
		}
		else if (!empty($context['resource_category_id']))
		{
			/** @var \XFRM\Entity\Category $category */
			$category = $em->find('XFRM:Category', intval($context['resource_category_id']));
			if (!$category || !$category->canView() || !$category->canAddResource())
			{
				return null;
			}
		}
		else
		{
			return null;
		}

		return $category;
	}
}