<?php

namespace XFMG\Admin\Controller;

use XF\Admin\Controller\AbstractField;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class MediaField extends AbstractField
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
	}

	protected function getClassIdentifier()
	{
		return 'XFMG:MediaField';
	}

	protected function getLinkPrefix()
	{
		return 'media-gallery/fields';
	}

	protected function getTemplatePrefix()
	{
		return 'xfmg_media_field';
	}

	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field)
	{
		$reply = parent::fieldAddEditResponse($field);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			/** @var \XFMG\Repository\Category $categoryRepo */
			$categoryRepo = $this->repository('XFMG:Category');

			$categories = $categoryRepo->findCategoryList()->fetch();
			$categoryTree = $categoryRepo->createCategoryTree($categories);

			/** @var \XF\Mvc\Entity\ArrayCollection $fieldAssociations */
			$fieldAssociations = $field->getRelationOrDefault('CategoryFields', false);

			$reply->setParams([
				'categoryTree' => $categoryTree,
				'categoryIds' => $fieldAssociations->pluckNamed('category_id')
			]);
		}

		return $reply;
	}

	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractField $field)
	{
		$additionalOptions = $this->filter([
			'album_use' => 'bool',
			'display_add_media' => 'bool'
		]);
		$form->setup(function() use ($field, $additionalOptions)
		{
			$field->bulkSet($additionalOptions);
		});

		$categoryIds = $this->filter('category_ids', 'array-uint');

		/** @var \XFMG\Entity\MediaField $field */
		$form->complete(function() use ($field, $categoryIds)
		{
			/** @var \XFMG\Repository\CategoryField $repo */
			$repo = $this->repository('XFMG:CategoryField');
			$repo->updateFieldAssociations($field, $categoryIds);
		});

		return $form;
	}
}