<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Category extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource_category', ['delete' => 'delete']);
	}

	public function actionGet(ParameterBag $params)
	{
		$category = $this->assertViewableCategory($params->resource_category_id);

		if ($this->filter('with_resources', 'bool'))
		{
			$this->assertApiScope('resource:read');
			$resourceData = $this->getResourcesInCategoryPaginated($category, $this->filterPage());
		}
		else
		{
			$resourceData = [];
		}

		$result = [
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE)
		];
		$result += $resourceData;

		return $this->apiResult($result);
	}

	/**
	 * @api-desc Gets a page of resources from the specified category.
	 *
	 * @api-see self::getResourcesInCategoryPaginated()
	 */
	public function actionGetResources(ParameterBag $params)
	{
		$this->assertApiScope('resource:read');

		$category = $this->assertViewableCategory($params->resource_category_id);

		$resourceData = $this->getResourcesInCategoryPaginated($category, $this->filterPage());

		return $this->apiResult($resourceData);
	}

	/**
	 * @api-out ResourceItem[] $resources Resources on this page
	 * @api-out pagination $pagination Pagination information
	 */
	protected function getResourcesInCategoryPaginated(\XFRM\Entity\Category $category, $page = 1, $perPage = null)
	{
		$perPage = intval($perPage);
		if ($perPage <= 0)
		{
			$perPage = $this->options()->xfrmResourcesPerPage;
		}

		$finder = $this->setupResourceFinder($category, $filters, $sort);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		/** @var \XFRM\Entity\ResourceItem[]|\XF\Mvc\Entity\AbstractCollection $resources */
		$resources = $finder->fetch();
		if (\XF::isApiCheckingPermissions())
		{
			$resources = $resources->filterViewable();
		}

		$resourceResults = $resources->toApiResults();
		$this->adjustResourceListApiResults($category, $resourceResults);

		return [
			'resources' => $resourceResults,
			'pagination' => $this->getPaginationData($resourceResults, $page, $perPage, $total)
		];
	}

	/**
	 * @param \XFRM\Entity\Category $category
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return \XFRM\Finder\ResourceItem
	 */
	protected function setupResourceFinder(\XFRM\Entity\Category $category, &$filters = [], &$sort = null)
	{
		$finder = $this->repository('XFRM:ResourceItem')->findResourcesForApi($category);

		/** @var \XFRM\Api\ControllerPlugin\ResourceItem $plugin */
		$plugin = $this->plugin('XFRM:Api:ResourceItem');

		$filters = $plugin->applyResourceListFilters($finder);
		$sort = $plugin->applyResourceListSort($finder);

		return $finder;
	}

	protected function adjustResourceListApiResults(\XFRM\Entity\Category $category, \XF\Api\Result\EntityResultInterface $result)
	{
		$result->skipRelation('Category');
	}

	public function actionPost(ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');

		$category = $this->assertViewableCategory($params->resource_category_id);

		/** @var \XFRM\Api\ControllerPlugin\Category $plugin */
		$plugin = $this->plugin('XFRM:Api:Category');

		$form = $plugin->setupCategorySave($category);
		$form->run();

		return $this->apiSuccess([
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE)
		]);
	}

	/**
	 * @api-desc Deletes the specified category
	 *
	 * @api-see XF\Api\ControllerPlugin\CategoryTree::actionDelete
	 */
	public function actionDelete(ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');

		$category = $this->assertViewableCategory($params->resource_category_id);

		/** @var \XF\Api\ControllerPlugin\CategoryTree $plugin */
		$plugin = $this->plugin('XF:Api:CategoryTree');

		return $plugin->actionDelete($category);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFRM\Entity\Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFRM:Category', $id, $with);
	}
}