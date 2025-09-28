<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractField;

class ResourceField extends AbstractField
{
	protected $categoryIds = [];

	public function getImportType()
	{
		return 'resource_field';
	}

	protected function getEntityShortName()
	{
		return 'XFRM:ResourceField';
	}

	public function setCategories(array $categoryIds)
	{
		$this->categoryIds = $categoryIds;
	}

	protected function postSave($oldId, $newId)
	{
		parent::postSave($oldId, $newId);

		if ($this->categoryIds)
		{
			$insert = [];
			foreach ($this->categoryIds AS $categoryId)
			{
				$insert[] = [
					'resource_category_id' => $categoryId,
					'field_id' => $newId
				];
			}

			$this->db()->insertBulk('xf_rm_category_field', $insert, false, false, 'IGNORE');
		}
	}
}