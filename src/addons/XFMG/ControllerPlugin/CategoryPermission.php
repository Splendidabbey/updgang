<?php

namespace XFMG\ControllerPlugin;

use XF\ControllerPlugin\AbstractPermission;

class CategoryPermission extends AbstractPermission
{
	protected $viewFormatter = 'XFMG:Permission\Category%s';
	protected $templateFormatter = 'xfmg_permission_category_%s';
	protected $routePrefix = 'permissions/gallery-categories';
	protected $contentType = 'xfmg_category';
	protected $entityIdentifier = 'XFMG:Category';
	protected $primaryKey = 'category_id';
	protected $privatePermissionGroupId = 'xfmg';
	protected $privatePermissionId = 'view';
}