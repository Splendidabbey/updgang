<?php

namespace XFRM\ControllerPlugin;

use XF\ControllerPlugin\AbstractCategoryTree;

class CategoryTree extends AbstractCategoryTree
{
	protected $viewFormatter = 'XFRM:Category\%s';
	protected $templateFormatter = 'xfrm_category_%s';
	protected $routePrefix = 'resource-manager/categories';
	protected $entityIdentifier = 'XFRM:Category';
	protected $primaryKey = 'resource_category_id';
}