<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class Category extends AbstractEmulatedData
{
	use HasWatchTrait;

	public function getImportType()
	{
		return 'xfmg_category';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:Category';
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('title', $oldId);

		if (!$this->allowed_types)
		{
			$this->allowed_types = ['image', 'video', 'audio', 'embed'];
		}
	}

	protected function postSave($oldId, $newId)
	{
		\XF::runOnce('xfmgCategoryImport', function()
		{
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XFMG:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
		});

		$this->insertWatchers($newId);
	}
}