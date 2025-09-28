<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class ResourceRating extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'resource_rating';
	}

	public function getEntityShortName()
	{
		return 'XFRM:ResourceRating';
	}

	public function setCustomFields(array $customFields)
	{
		foreach ($customFields AS &$fieldValue)
		{
			if (is_string($fieldValue))
			{
				$fieldValue = $this->convertToUtf8($fieldValue);
			}
		}

		$this->custom_fields = $customFields;
	}

	protected function postSave($oldId, $newId)
	{
		if ($this->custom_fields)
		{
			$this->insertCustomFieldValues(
				'xf_rm_resource_review_field_value',
				'resource_rating_id',
				$newId,
				$this->custom_fields
			);
		}

		/** @var \XFRM\Entity\ResourceItem $resourceItem */
		$resourceItem = $this->em()->find('XFRM:ResourceItem', $this->resource_id);

		if ($resourceItem)
		{
			$resourceItem->rebuildReviewCount();
			$resourceItem->rebuildRating();

			$this->em()->detachEntity($resourceItem);
		}
	}
}