<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int resource_id
 * @property bool email_subscribe
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 * @property \XF\Entity\User User
 */
class ResourceWatch extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_resource_watch';
		$structure->shortName = 'XFRM:ResourceWatch';
		$structure->primaryKey = ['user_id', 'resource_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'resource_id' => ['type' => self::UINT, 'required' => true],
			'email_subscribe' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [];
		$structure->relations = [
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
		];

		return $structure;
	}
}