<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\AbstractFieldMap;

class CategoryReviewField extends AbstractFieldMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFRM:CategoryReviewField';
	}

	protected function getAssociationsForField(\XF\Entity\AbstractField $field)
	{
		return $field->getRelation('CategoryReviewFields');
	}

	protected function updateAssociationCache(array $cache)
	{
		$categoryIds = array_keys($cache);
		$categories = $this->em->findByIds('XFRM:Category', $categoryIds);

		foreach ($categories AS $category)
		{
			/** @var \XFRM\Entity\Category $category */
			$category->review_field_cache = $cache[$category->resource_category_id];
			$category->saveIfChanged();
		}
	}
}