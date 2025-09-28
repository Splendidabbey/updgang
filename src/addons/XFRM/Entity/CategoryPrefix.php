<?php

namespace XFRM\Entity;

use XF\Entity\AbstractPrefixMap;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int resource_category_id
 * @property int prefix_id
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourcePrefix Prefix
 * @property \XFRM\Entity\Category Category
 */
class CategoryPrefix extends AbstractPrefixMap
{
	public static function getContainerKey()
	{
		return 'resource_category_id';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_rm_category_prefix', 'XFRM:CategoryPrefix', 'XFRM:ResourcePrefix');

		$structure->relations['Category'] = [
			'entity' => 'XFRM:Category',
			'type' => self::TO_ONE,
			'conditions' => 'resource_category_id',
			'primary' => true
		];

		return $structure;
	}
}