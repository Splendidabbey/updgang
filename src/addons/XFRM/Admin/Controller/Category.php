<?php

namespace XFRM\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Category extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
	}

	/**
	 * @return \XFRM\ControllerPlugin\CategoryTree
	 */
	protected function getCategoryTreePlugin()
	{
		return $this->plugin('XFRM:CategoryTree');
	}

	public function actionIndex()
	{
		return $this->getCategoryTreePlugin()->actionList([
			'permissionContentType' => 'resource_category'
		]);
	}

	public function categoryAddEdit(\XFRM\Entity\Category $category)
	{
		$categoryRepo = $this->getCategoryRepo();
		$categories = $categoryRepo->findCategoryList()->fetch();
		$categoryTree = $categoryRepo->createCategoryTree($categories);

		if ($category->thread_prefix_id && $category->ThreadForum)
		{
			$threadPrefixes = $category->ThreadForum->getPrefixesGrouped();
		}
		else
		{
			$threadPrefixes = [];
		}

		/** @var \XF\Repository\ThreadPrefix $prefixRepo */
		$prefixRepo = $this->repository('XFRM:ResourcePrefix');
		$availablePrefixes = $prefixRepo->findPrefixesForList()->fetch();
		$availablePrefixes = $availablePrefixes->pluckNamed('title', 'prefix_id');

		/** @var \XF\Repository\ThreadField $fieldRepo */
		$fieldRepo = $this->repository('XFRM:ResourceField');
		$availableFields = $fieldRepo->findFieldsForList()->fetch();
		$availableFields = $availableFields->pluckNamed('title', 'field_id');

		$viewParams = [
			'forumOptions' => $this->repository('XF:Forum')->getForumOptionsData(false, 'discussion'),
			'threadPrefixes' => $threadPrefixes,
			'category' => $category,
			'categoryTree' => $categoryTree,

			'availableFields' => $availableFields,
			'availablePrefixes' => $availablePrefixes,
		];
		return $this->view('XFRM:Category\Edit', 'xfrm_category_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$category = $this->assertCategoryExists($params->resource_category_id);
		return $this->categoryAddEdit($category);
	}

	public function actionAdd()
	{
		$category = $this->em()->create('XFRM:Category');
		$category->parent_category_id = $this->filter('parent_category_id', 'uint');

		return $this->categoryAddEdit($category);
	}

	protected function categorySaveProcess(\XFRM\Entity\Category $category)
	{
		$entityInput = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'parent_category_id' => 'uint',
			'display_order' => 'uint',
			'allow_local' => 'bool',
			'allow_external' => 'bool',
			'allow_commercial_external' => 'bool',
			'allow_fileless' => 'bool',
			'enable_versioning' => 'bool',
			'enable_support_url' => 'bool',
			'always_moderate_create' => 'bool',
			'always_moderate_update' => 'bool',
			'min_tags' => 'uint',
			'thread_node_id' => 'uint',
			'thread_prefix_id' => 'uint',
			'require_prefix' => 'bool',
		]);

		$form = $this->formAction();
		$form->basicEntitySave($category, $entityInput);

		$prefixIds = $this->filter('available_prefixes', 'array-uint');
		$form->complete(function() use ($category, $prefixIds)
		{
			/** @var \XFRM\Repository\CategoryPrefix $repo */
			$repo = $this->repository('XFRM:CategoryPrefix');
			$repo->updateContentAssociations($category->resource_category_id, $prefixIds);
		});

		$fieldIds = $this->filter('available_fields', 'array-str');
		$form->complete(function() use ($category, $fieldIds)
		{
			/** @var \XFRM\Repository\CategoryField $repo */
			$repo = $this->repository('XFRM:CategoryField');
			$repo->updateContentAssociations($category->resource_category_id, $fieldIds);
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->resource_category_id)
		{
			$category = $this->assertCategoryExists($params->resource_category_id);
		}
		else
		{
			$category = $this->em()->create('XFRM:Category');
		}

		$this->categorySaveProcess($category)->run();

		return $this->redirect(
			$this->buildLink('resource-manager/categories') . $this->buildLinkHash($category->getEntityId())
		);
	}

	public function actionDelete(ParameterBag $params)
	{
		return $this->getCategoryTreePlugin()->actionDelete($params);
	}

	public function actionSort()
	{
		return $this->getCategoryTreePlugin()->actionSort();
	}

	/**
	 * @return \XFRM\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin()
	{
		/** @var \XFRM\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('XFRM:CategoryPermission');
		$plugin->setFormatters('XFRM:Category\Permission%s', 'xfrm_category_permission_%s');
		$plugin->setRoutePrefix('resource-manager/categories/permissions');

		return $plugin;
	}

	public function actionPermissions(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionList($params);
	}

	public function actionPermissionsEdit(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}

	public function actionPermissionsSave(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \XFRM\Entity\Category
	 */
	protected function assertCategoryExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('XFRM:Category', $id, $with, $phraseKey);
	}

	/**
	 * @return \XFRM\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFRM:Category');
	}
}