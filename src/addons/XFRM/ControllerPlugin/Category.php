<?php

namespace XFRM\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;

class Category extends AbstractPlugin
{
	public function applyCategoryContext(\XFRM\Entity\Category $category)
	{
		$this->controller->setContainerKey('xfrmCategory-' . $category->resource_category_id);
	}
}