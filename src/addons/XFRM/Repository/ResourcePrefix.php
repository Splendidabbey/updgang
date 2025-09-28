<?php

namespace XFRM\Repository;

use XF\Repository\AbstractPrefix;

class ResourcePrefix extends AbstractPrefix
{
	protected function getRegistryKey()
	{
		return 'xfrmPrefixes';
	}

	protected function getClassIdentifier()
	{
		return 'XFRM:ResourcePrefix';
	}

	public function getVisiblePrefixListData()
	{
		if (!method_exists($this, '_getVisiblePrefixListData'))
		{
			// in case this version of XFRM is used on an older version of XF.
			return $this->getPrefixListData();
		}

		$categories = $this->finder('XFRM:Category')
			->with('Permissions|' . \XF::visitor()->permission_combination_id)
			->fetch();

		$prefixMap = $this->finder('XFRM:CategoryPrefix')
			->fetch()
			->groupBy('prefix_id', 'resource_category_id');

		$isVisibleClosure = function(\XFRM\Entity\ResourcePrefix $prefix) use ($prefixMap, $categories)
		{
			if (!isset($prefixMap[$prefix->prefix_id]))
			{
				return false;
			}

			$isVisible = false;

			foreach ($prefixMap[$prefix->prefix_id] AS $categoryPrefix)
			{
				/** @var \XFRM\Entity\CategoryPrefix $categoryPrefix */

				if (!isset($categories[$categoryPrefix->resource_category_id]))
				{
					continue;
				}

				$isVisible = $categories[$categoryPrefix->resource_category_id]->canView();

				if ($isVisible)
				{
					break;
				}
			}

			return $isVisible;
		};
		return $this->_getVisiblePrefixListData($isVisibleClosure);
	}
}