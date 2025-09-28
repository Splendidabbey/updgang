<?php

namespace XFMG\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Categories extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('media_category', ['delete' => 'delete']);
	}

	public function actionGet()
	{
		$repo = $this->getCategoryRepo();
		return $this->getCategoryTreePlugin()->actionGet($repo);
	}

	public function actionGetFlattened()
	{
		$repo = $this->getCategoryRepo();
		return $this->getCategoryTreePlugin()->actionGetFlattened($repo);
	}

	public function actionPost(ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
		$this->assertRequiredApiInput(['title', 'parent_category_id']);

		/** @var \XFMG\Entity\Category $category */
		$category = $this->em()->create('XFMG:Category');

		/** @var \XFMG\Api\ControllerPlugin\Category $plugin */
		$plugin = $this->plugin('XFMG:Api:Category');

		$form = $plugin->setupCategorySave($category);
		$form->run();

		return $this->apiSuccess([
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @return \XFMG\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFMG:Category');
	}

	/**
	 * @return \XF\Api\ControllerPlugin\CategoryTree
	 */
	protected function getCategoryTreePlugin()
	{
		return $this->plugin('XF:Api:CategoryTree');
	}
}