<?php

namespace XFMG\ControllerPlugin;

use XF\ControllerPlugin\AbstractCategoryTree;

class CategoryTree extends AbstractCategoryTree
{
	protected $viewFormatter = 'XFMG:Category\%s';
	protected $templateFormatter = 'xfmg_category_%s';
	protected $routePrefix = 'media-gallery/categories';
	protected $entityIdentifier = 'XFMG:Category';
	protected $primaryKey = 'category_id';
}