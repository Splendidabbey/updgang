<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int category_id
 * @property string notify_on
 * @property bool send_alert
 * @property bool send_email
 * @property bool include_children
 *
 * RELATIONS
 * @property \XFMG\Entity\Category Category
 * @property \XF\Entity\User User
 */
class CategoryWatch extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_category_watch';
		$structure->shortName = 'XFMG:CategoryWatch';
		$structure->primaryKey = ['user_id', 'category_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'category_id' => ['type' => self::UINT, 'required' => true],
			'notify_on' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'media']
			],
			'send_alert' => ['type' => self::BOOL, 'default' => false],
			'send_email' => ['type' => self::BOOL, 'default' => false],
			'include_children' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [];
		$structure->relations = [
			'Category' => [
				'entity' => 'XFMG:Category',
				'type' => self::TO_ONE,
				'conditions' => 'category_id',
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