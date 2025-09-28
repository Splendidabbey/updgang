<?php

namespace XFRM\Admin\Controller;

use XF\Admin\Controller\AbstractPrefix;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;

class ResourcePrefix extends AbstractPrefix
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
	}

	protected function getClassIdentifier()
	{
		return 'XFRM:ResourcePrefix';
	}

	protected function getLinkPrefix()
	{
		return 'resource-manager/prefixes';
	}

	protected function getTemplatePrefix()
	{
		return 'xfrm_resource_prefix';
	}

	protected function getCategoryParams(\XFRM\Entity\ResourcePrefix $prefix)
	{
		/** @var \XFRM\Repository\Category $categoryRepo */
		$categoryRepo = \XF::repository('XFRM:Category');
		$categoryTree = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());

		return [
			'categoryTree' => $categoryTree,
		];
	}

	protected function prefixAddEditResponse(\XF\Entity\AbstractPrefix $prefix)
	{
		$reply = parent::prefixAddEditResponse($prefix);

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			$reply->setParams($this->getCategoryParams($prefix));
		}

		return $reply;
	}

	protected function quickSetAdditionalData(FormAction $form, ArrayCollection $prefixes)
	{
		$input = $this->filter([
			'apply_resource_category_ids' => 'bool',
			'resource_category_ids' => 'array-uint'
		]);

		if ($input['apply_resource_category_ids'])
		{
			$form->complete(function() use($prefixes, $input)
			{
				$mapRepo = $this->getCategoryPrefixRepo();

				foreach ($prefixes AS $prefix)
				{
					$mapRepo->updatePrefixAssociations($prefix, $input['resource_category_ids']);
				}
			});
		}

		return $form;
	}

	public function actionQuickSet()
	{
		$reply = parent::actionQuickSet();

		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			if ($reply->getTemplateName() == $this->getTemplatePrefix() . '_quickset_editor')
			{
				$reply->setParams($this->getCategoryParams($reply->getParam('prefix')));
			}
		}

		return $reply;
	}

	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractPrefix $prefix)
	{
		$categoryIds = $this->filter('resource_category_ids', 'array-uint');

		$form->complete(function() use($prefix, $categoryIds)
		{
			$this->getCategoryPrefixRepo()->updatePrefixAssociations($prefix, $categoryIds);
		});

		return $form;
	}

	/**
	 * @return \XFRM\Repository\CategoryPrefix
	 */
	protected function getCategoryPrefixRepo()
	{
		return $this->repository('XFRM:CategoryPrefix');
	}
}