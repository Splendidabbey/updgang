<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractField;

class MediaField extends AbstractField
{
	protected $categoryIds = [];

	public function getImportType()
	{
		return 'xfmg_media_field';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:MediaField';
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
					'category_id' => $categoryId,
					'field_id' => $newId
				];
			}

			$this->db()->insertBulk('xf_mg_category_field', $insert, false, false, 'IGNORE');
		}
	}
}