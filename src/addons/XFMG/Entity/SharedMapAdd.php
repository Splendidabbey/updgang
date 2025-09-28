<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int album_id
 * @property int user_id
 *
 * RELATIONS
 * @property \XFMG\Entity\Album Album
 * @property \XF\Entity\User User
 */
class SharedMapAdd extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_shared_map_add';
		$structure->shortName = 'XFMG:SharedMapAdd';
		$structure->primaryKey = ['album_id', 'user_id'];
		$structure->columns = [
			'album_id' => ['type' => self::UINT, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true]
		];
		$structure->getters = [];
		$structure->relations = [
			'Album' => [
				'entity' => 'XFMG:Album',
				'type' => self::TO_ONE,
				'conditions' => 'album_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];

		return $structure;
	}
}