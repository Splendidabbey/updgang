<?php

namespace XFRM\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;

class Category extends AbstractPlugin
{
	public function setupCategorySave(\XFRM\Entity\Category $category)
	{
		$entityInput = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'parent_category_id' => '?uint',
			'display_order' => '?uint',
			'allow_local' => '?bool',
			'allow_external' => '?bool',
			'allow_commercial_external' => '?bool',
			'allow_fileless' => '?bool',
			'enable_versioning' => '?bool',
			'enable_support_url' => '?bool',
			'always_moderate_create' => '?bool',
			'always_moderate_update' => '?bool',
			'min_tags' => '?uint',
			'thread_node_id' => '?uint',
			'thread_prefix_id' => '?uint',
			'require_prefix' => '?bool',
		]);
		$entityInput = \XF\Util\Arr::filterNull($entityInput);

		$form = $this->formAction();
		$form->basicEntitySave($category, $entityInput);

		return $form;
	}
}