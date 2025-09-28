<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int resource_id
 * @property int feature_date
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 */
class ResourceFeature extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_feature';
		$structure->shortName = 'XFRM:ResourceFeature';
		$structure->primaryKey = 'resource_id';
		$structure->columns = [
			'resource_id' => ['type' => self::UINT, 'required' => true],
			'feature_date' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		$structure->getters = [];
		$structure->relations = [
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			]
		];

		return $structure;
	}
}