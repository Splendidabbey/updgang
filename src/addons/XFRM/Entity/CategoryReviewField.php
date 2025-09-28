<?php

namespace XFRM\Entity;

use XF\Entity\AbstractFieldMap;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int resource_category_id
 * @property string field_id
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceReviewField Field
 * @property \XFRM\Entity\Category Category
 */
class CategoryReviewField extends AbstractFieldMap
{
	public static function getContainerKey()
	{
		return 'resource_category_id';
	}

	public static function getStructure(Structure $structure)
	{
		self::setupDefaultStructure($structure, 'xf_rm_category_review_field', 'XFRM:CategoryReviewField', 'XFRM:ResourceReviewField');

		$structure->relations['Category'] = [
			'entity' => 'XFRM:Category',
			'type' => self::TO_ONE,
			'conditions' => 'resource_category_id',
			'primary' => true
		];

		return $structure;
	}
}