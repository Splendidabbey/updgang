<?php

namespace XFRM\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;

class Overview extends AbstractPlugin
{
	public function getCategoryListData(\XFRM\Entity\Category $category = null)
	{
		$categoryRepo = $this->getCategoryRepo();
		$categories = $categoryRepo->getViewableCategories();
		$categoryTree = $categoryRepo->createCategoryTree($categories);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

		return [
			'categories' => $categories,
			'categoryTree' => $categoryTree,
			'categoryExtras' => $categoryExtras
		];
	}

	public function getCoreListData(array $sourceCategoryIds, \XFRM\Entity\Category $category = null)
	{
		$resourceRepo = $this->getResourceRepo();

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		$resourceFinder = $resourceRepo->findResourcesForOverviewList($sourceCategoryIds, [
			'allowOwnPending' => $allowOwnPending
		]);

		$filters = $this->getResourceFilterInput();
		$this->applyResourceFilters($resourceFinder, $filters);

		// TODO: if no filters and can't view deleted/moderated, can total from category info
		$totalResources = $resourceFinder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->xfrmResourcesPerPage;

		$resourceFinder->limitByPage($page, $perPage);
		$resources = $resourceFinder->fetch()->filterViewable();

		if (!empty($filters['creator_id']))
		{
			$creatorFilter = $this->em()->find('XF:User', $filters['creator_id']);
		}
		else
		{
			$creatorFilter = null;
		}

		$canInlineMod = false;
		foreach ($resources AS $resource)
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			if ($resource->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		return [
			'resources' => $resources,
			'filters' => $filters,
			'creatorFilter' => $creatorFilter,
			'canInlineMod' => $canInlineMod,

			'total' => $totalResources,
			'page' => $page,
			'perPage' => $perPage
		];
	}

	public function applyResourceFilters(\XFRM\Finder\ResourceItem $resourceFinder, array $filters)
	{
		if (!empty($filters['prefix_id']))
		{
			$resourceFinder->where('prefix_id', intval($filters['prefix_id']));
		}

		if (!empty($filters['type']))
		{
			switch ($filters['type'])
			{
				case 'free':
					$resourceFinder->where('price', 0);
					break;

				case 'paid':
					$resourceFinder->where('price', '>', 0);
					break;
			}
		}

		if (!empty($filters['creator_id']))
		{
			$resourceFinder->where('user_id', intval($filters['creator_id']));
		}

		$sorts = $this->getAvailableResourceSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$resourceFinder->order($sorts[$filters['order']], $filters['direction']);
		}
		// else the default order has already been applied
	}

	public function getResourceFilterInput()
	{
		$filters = [];

		$input = $this->filter([
			'prefix_id' => 'uint',
			'type' => 'str',
			'creator' => 'str',
			'creator_id' => 'uint',
			'order' => 'str',
			'direction' => 'str'
		]);

		if ($input['prefix_id'])
		{
			$filters['prefix_id'] = $input['prefix_id'];
		}

		if ($input['type'] && ($input['type'] == 'free' || $input['type'] == 'paid'))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['creator_id'])
		{
			$filters['creator_id'] = $input['creator_id'];
		}
		else if ($input['creator'])
		{
			$user = $this->em()->findOne('XF:User', ['username' => $input['creator']]);
			if ($user)
			{
				$filters['creator_id'] = $user->user_id;
			}
		}

		$sorts = $this->getAvailableResourceSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			$defaultOrder = $this->options()->xfrmListDefaultOrder ?: 'last_update';
			$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

			if ($input['order'] != $defaultOrder || $input['direction'] != $defaultDir)
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}

	public function getAvailableResourceSorts()
	{
		// maps [name of sort] => field in/relative to ResourceItem entity
		return [
			'last_update' => 'last_update',
			'resource_date' => 'resource_date',
			'rating_weighted' => 'rating_weighted',
			'download_count' => 'download_count',
			'title' => 'title'
		];
	}

	public function actionFilters(\XFRM\Entity\Category $category = null)
	{
		$filters = $this->getResourceFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink(
				$category ? 'resources/categories' : 'resources',
				$category,
				$filters
			));
		}

		if (!empty($filters['creator_id']))
		{
			$creatorFilter = $this->em()->find('XF:User', $filters['creator_id']);
		}
		else
		{
			$creatorFilter = null;
		}

		$applicableCategories = $this->getCategoryRepo()->getViewableCategories($category);
		$applicableCategoryIds = $applicableCategories->keys();
		if ($category)
		{
			$applicableCategoryIds[] = $category->resource_category_id;
		}

		$availablePrefixIds = $this->repository('XFRM:CategoryPrefix')->getPrefixIdsInContent($applicableCategoryIds);
		$prefixes = $this->repository('XFRM:ResourcePrefix')->findPrefixesForList()
			->where('prefix_id', $availablePrefixIds)
			->fetch();

		$showTypeFilters = false;

		foreach ($applicableCategories AS $searchCategory)
		{
			if ($searchCategory->allow_commercial_external)
			{
				$showTypeFilters = true;
				break;
			}
		}

		if ($category && $category->allow_commercial_external)
		{
			$showTypeFilters = true;
		}

		$defaultOrder = $this->options()->xfrmListDefaultOrder ?: 'last_update';
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}

		$viewParams = [
			'category' => $category,
			'prefixesGrouped' => $prefixes->groupBy('prefix_group_id'),
			'filters' => $filters,
			'creatorFilter' => $creatorFilter,
			'showTypeFilters' => $showTypeFilters
		];
		return $this->view('XFRM:Filters', 'xfrm_filters', $viewParams);
	}

	public function actionFeatured(\XFRM\Entity\Category $category = null)
	{
		$viewableCategoryIds = $this->getCategoryRepo()->getViewableCategoryIds($category);

		$finder = $this->getResourceRepo()->findFeaturedResources($viewableCategoryIds);
		$finder->with('fullCategory');
		$finder->order('Featured.feature_date', 'desc');

		$resources = $finder->fetch()->filterViewable();

		$canInlineMod = false;
		foreach ($resources AS $resource)
		{
			/** @var \XFRM\Entity\ResourceItem $resource */
			if ($resource->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'category' => $category,
			'resources' => $resources,
			'canInlineMod' => $canInlineMod
		];
		return $this->view('XFRM:Featured', 'xfrm_featured', $viewParams);
	}

	/**
	 * @return \XFRM\Repository\ResourceItem
	 */
	protected function getResourceRepo()
	{
		return $this->repository('XFRM:ResourceItem');
	}

	/**
	 * @return \XFRM\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFRM:Category');
	}
}