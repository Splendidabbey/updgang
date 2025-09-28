<?php

namespace XFRM\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\AbstractPrefixMap;

class CategoryPrefix extends AbstractPrefixMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFRM:CategoryPrefix';
	}

	protected function getAssociationsForPrefix(\XF\Entity\AbstractPrefix $prefix)
	{
		return $prefix->getRelation('CategoryPrefixes');
	}

	protected function updateAssociationCache(array $cache)
	{
		$ids = array_keys($cache);
		$categories = $this->em->findByIds('XFRM:Category', $ids);

		foreach ($categories AS $category)
		{
			/** @var \XFRM\Entity\Category $category */
			$category->prefix_cache = $cache[$category->resource_category_id];
			$category->saveIfChanged();
		}
	}
}