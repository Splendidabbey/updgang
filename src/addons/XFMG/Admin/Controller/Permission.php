<?php

namespace XFMG\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Permission extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mediaGallery');
	}

	/**
	 * @return \XFMG\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin()
	{
		/** @var \XFMG\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('XFMG:CategoryPermission');
		$plugin->setFormatters('XFMG\Permission\Category%s', 'xfmg_permission_category_%s');
		$plugin->setRoutePrefix('permissions/media-categories');

		return $plugin;
	}

	public function actionCategory(ParameterBag $params)
	{
		if ($params->category_id)
		{
			return $this->getCategoryPermissionPlugin()->actionList($params);
		}
		else
		{
			$categoryRepo = $this->repository('XFMG:Category');
			$categoryTree = $categoryRepo->createCategoryTree();

			$customPermissions = $this->repository('XF:PermissionEntry')->getContentWithCustomPermissions('xfmg_category');

			$viewParams = [
				'categoryTree' => $categoryTree,
				'customPermissions' => $customPermissions
			];
			return $this->view('XFMG:Permission\CategoryOverview', 'xfmg_permission_category_overview', $viewParams);
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