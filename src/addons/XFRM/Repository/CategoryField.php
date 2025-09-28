<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\AbstractFieldMap;

class CategoryField extends AbstractFieldMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFRM:CategoryField';
	}

	protected function getAssociationsForField(\XF\Entity\AbstractField $field)
	{
		return $field->getRelation('CategoryFields');
	}

	protected function updateAssociationCache(array $cache)
	{
		$categoryIds = array_keys($cache);
		$categories = $this->em->findByIds('XFRM:Category', $categoryIds);

		foreach ($categories AS $category)
		{
			/** @var \XFRM\Entity\Category $category */
			$category->field_cache = $cache[$category->resource_category_id];
			$category->saveIfChanged();
		}
	}
}