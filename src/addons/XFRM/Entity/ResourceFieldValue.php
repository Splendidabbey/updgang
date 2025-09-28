<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int resource_id
 * @property string field_id
 * @property string field_value
 */
class ResourceFieldValue extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_field_value';
		$structure->shortName = 'XFRM:ResourceFieldValue';
		$structure->primaryKey = ['resource_id', 'field_id'];
		$structure->columns = [
			'resource_id' => ['type' => self::UINT, 'required' => true],
			'field_id' => ['type' => self::STR, 'maxLength' => 25,
				'match' => 'alphanumeric'
			],
			'field_value' => ['type' => self::STR, 'default' => '']
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}