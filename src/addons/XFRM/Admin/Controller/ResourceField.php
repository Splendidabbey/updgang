<?php

namespace XFRM\Admin\Controller;

use XF\Admin\Controller\AbstractField;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class ResourceField extends AbstractField
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
	}

	protected function getClassIdentifier()
	{
		return 'XFRM:ResourceField';
	}

	protected function getLinkPrefix()
	{
		return 'resource-manager/fields';
	}

	protected function getTemplatePrefix()
	{
		return 'xfrm_resource_field';
	}

	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field)
	{
		$reply = parent::fieldAddEditResponse($field);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			/** @var \XFRM\Repository\Category $categoryRepo */
			$categoryRepo = $this->repository('XFRM:Category');

			$categories = $categoryRepo->findCategoryList()->fetch();
			$categoryTree = $categoryRepo->createCategoryTree($categories);

			/** @var \XF\Mvc\Entity\ArrayCollection $fieldAssociations */
			$fieldAssociations = $field->getRelationOrDefault('CategoryFields', false);

			$reply->setParams([
				'categoryTree' => $categoryTree,
				'categoryIds' => $fieldAssociations->pluckNamed('resource_category_id')
			]);
		}

		return $reply;
	}

	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractField $field)
	{
		$categoryIds = $this->filter('resource_category_ids', 'array-uint');

		/** @var \XFRM\Entity\ResourceField $field */
		$form->complete(function() use ($field, $categoryIds)
		{
			/** @var \XFRM\Repository\CategoryField $repo */
			$repo = $this->repository('XFRM:CategoryField');
			$repo->updateFieldAssociations($field, $categoryIds);
		});

		return $form;
	}
}