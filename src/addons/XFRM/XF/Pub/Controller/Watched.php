<?php

namespace XFRM\XF\Pub\Controller;

class Watched extends XFCP_Watched
{
	public function actionResources()
	{
		$this->setSectionContext('xfrm');

		$page = $this->filterPage();
		$perPage = $this->options()->xfrmResourcesPerPage;

		/** @var \XFRM\Repository\ResourceItem $resourceRepo */
		$resourceRepo = $this->repository('XFRM:ResourceItem');
		$finder = $resourceRepo->findResourcesForWatchedList();

		$total = $finder->total();
		$resources = $finder->limitByPage($page, $perPage)->fetch();

		$viewParams = [
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'resources' => $resources->filterViewable()
		];
		return $this->view('XFRM:Watched\Resources', 'xfrm_watched_resources', $viewParams);
	}

	public function actionResourcesManage()
	{
		$this->setSectionContext('xfrm');

		if (!$state = $this->filter('state', 'str'))
		{
			return $this->redirect($this->buildLink('watched/resources'));
		}

		if ($this->isPost())
		{
			/** @var \XFRM\Repository\ResourceWatch $resourceWatchRepo */
			$resourceWatchRepo = $this->repository('XFRM:ResourceWatch');

			if ($action = $this->getResourceWatchActionConfig($state, $updates))
			{
				$resourceWatchRepo->setWatchStateForAll(\XF::visitor(), $action, $updates);
			}

			return $this->redirect($this->buildLink('watched/resources'));
		}
		else
		{
			$viewParams = [
				'state' => $state
			];
			return $this->view('XFRM:Watched\ResourcesManage', 'watched_resources_manage', $viewParams);
		}
	}

	public function actionResourcesUpdate()
	{
		$this->assertPostOnly();
		$this->setSectionContext('xfrm');

		/** @var \XFRM\Repository\ResourceWatch $watchRepo */
		$watchRepo = $this->repository('XFRM:ResourceWatch');

		$inputAction = $this->filter('watch_action', 'str');
		$action = $this->getResourceWatchActionConfig($inputAction, $config);

		if ($action)
		{
			$ids = $this->filter('ids', 'array-uint');
			$resources = $this->em()->findByIds('XFRM:ResourceItem', $ids);
			$visitor = \XF::visitor();

			/** @var \XFRM\Entity\ResourceItem $resource */
			foreach ($resources AS $resource)
			{
				$watchRepo->setWatchState($resource, $visitor, $action, $config);
			}
		}

		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('watched/resources'))
		);
	}

	protected function getResourceWatchActionConfig($inputAction, array &$config = null)
	{
		$config = [];

		switch ($inputAction)
		{
			case 'email_subscribe:on':
				$config = ['email_subscribe' => 1];
				return 'update';

			case 'email_subscribe:off':
				$config = ['email_subscribe' => 0];
				return 'update';

			case 'delete':
				return 'delete';

			default:
				return null;
		}
	}

	public function actionResourceCategories()
	{
		$this->setSectionContext('xfrm');

		$watchedFinder = $this->finder('XFRM:CategoryWatch');
		$watchedCategories = $watchedFinder->where('user_id', \XF::visitor()->user_id)
			->keyedBy('resource_category_id')
			->fetch();

		/** @var \XFRM\Repository\Category $categoryRepo */
		$categoryRepo = $this->repository('XFRM:Category');
		$categories = $categoryRepo->getViewableCategories();
		$categoryTree = $categoryRepo->createCategoryTree($categories);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

		$viewParams = [
			'watchedCategories' => $watchedCategories,

			'categoryTree' => $categoryTree,
			'categoryExtras' => $categoryExtras
		];
		return $this->view('XFRM:Watched\Categories', 'xfrm_watched_categories', $viewParams);
	}

	public function actionResourceCategoriesUpdate()
	{
		$this->assertPostOnly();
		$this->setSectionContext('xfrm');

		/** @var \XFRM\Repository\CategoryWatch $watchRepo */
		$watchRepo = $this->repository('XFRM:CategoryWatch');

		$inputAction = $this->filter('watch_action', 'str');
		$action = $this->getCategoryWatchActionConfig($inputAction, $config);

		if ($action)
		{
			$visitor = \XF::visitor();

			$ids = $this->filter('ids', 'array-uint');
			$categories = $this->em()->findByIds('XFRM:Category', $ids);

			/** @var \XFRM\Entity\Category $category */
			foreach ($categories AS $category)
			{
				$watchRepo->setWatchState($category, $visitor, $action, $config);
			}
		}

		return $this->redirect(
			$this->getDynamicRedirect($this->buildLink('watched/resource-categories'))
		);
	}

	protected function getCategoryWatchActionConfig($inputAction, array &$config = null)
	{
		$config = [];

		$parts = explode(':', $inputAction, 2);

		$inputAction = $parts[0];
		$boolSwitch = isset($parts[1]) ? ($parts[1] == 'on') : false;

		switch ($inputAction)
		{
			case 'send_email':
			case 'send_alert':
			case 'include_children':
				$config = [$inputAction => $boolSwitch];
				return 'update';

			case 'delete':
				return 'delete';

			default:
				return null;
		}
	}
}