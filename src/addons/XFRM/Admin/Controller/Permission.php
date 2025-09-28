<?php

namespace XFRM\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Permission extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
	}

	/**
	 * @return \XFRM\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin()
	{
		/** @var \XFRM\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('XFRM:CategoryPermission');
		$plugin->setFormatters('XFRM\Permission\Category%s', 'xfrm_permission_category_%s');
		$plugin->setRoutePrefix('permissions/resource-categories');

		return $plugin;
	}

	public function actionCategory(ParameterBag $params)
	{
		if ($params->resource_category_id)
		{
			return $this->getCategoryPermissionPlugin()->actionList($params);
		}
		else
		{
			$categoryRepo = $this->repository('XFRM:Category');
			$categories = $categoryRepo->findCategoryList()->fetch();
			$categoryTree = $categoryRepo->createCategoryTree($categories);

			$customPermissions = $this->repository('XF:PermissionEntry')->getContentWithCustomPermissions('resource_category');

			$viewParams = [
				'categoryTree' => $categoryTree,
				'customPermissions' => $customPermissions
			];
			return $this->view('XFRM:Permission\CategoryOverview', 'xfrm_permission_category_overview', $viewParams);
		}
	}

	public function actionCategoryEdit(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}

	public function actionCategorySave(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}
}