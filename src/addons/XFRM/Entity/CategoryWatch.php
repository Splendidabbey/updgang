<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int resource_category_id
 * @property string notify_on
 * @property bool send_alert
 * @property bool send_email
 * @property bool include_children
 *
 * RELATIONS
 * @property \XFRM\Entity\Category Category
 * @property \XF\Entity\User User
 */
class CategoryWatch extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_rm_category_watch';
		$structure->shortName = 'XFRM:CategoryWatch';
		$structure->primaryKey = ['user_id', 'resource_category_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'resource_category_id' => ['type' => self::UINT, 'required' => true],
			'notify_on' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'resource', 'update']
			],
			'send_alert' => ['type' => self::BOOL, 'default' => false],
			'send_email' => ['type' => self::BOOL, 'default' => false],
			'include_children' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [];
		$structure->relations = [
			'Category' => [
				'entity' => 'XFRM:Category',
				'type' => self::TO_ONE,
				'conditions' => 'resource_category_id',
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