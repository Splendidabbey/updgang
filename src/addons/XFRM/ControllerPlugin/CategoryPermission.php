<?php

namespace XFRM\ControllerPlugin;

use XF\ControllerPlugin\AbstractPermission;

class CategoryPermission extends AbstractPermission
{
	protected $viewFormatter = 'XFRM:Permission\Category%s';
	protected $templateFormatter = 'xfrm_permission_category_%s';
	protected $routePrefix = 'permissions/resource-categories';
	protected $contentType = 'resource_category';
	protected $entityIdentifier = 'XFRM:Category';
	protected $primaryKey = 'resource_category_id';
	protected $privatePermissionGroupId = 'resource';
	protected $privatePermissionId = 'view';
}