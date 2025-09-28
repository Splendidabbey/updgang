<?php

namespace XFMG\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;

class Category extends AbstractPlugin
{
	public function setupCategorySave(\XFMG\Entity\Category $category)
	{
		$entityInput = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'parent_category_id' => '?uint',
			'display_order' => '?uint',
			'min_tags' => '?uint',
			'category_type' => '?str',
			'allowed_types' => '?array-str'
		]);
		$entityInput = \XF\Util\Arr::filterNull($entityInput);

		$form = $this->formAction();
		$form->basicEntitySave($category, $entityInput);

		return $form;
	}
}