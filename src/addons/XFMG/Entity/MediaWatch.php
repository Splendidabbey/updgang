<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int media_id
 * @property string notify_on
 * @property bool send_alert
 * @property bool send_email
 *
 * RELATIONS
 * @property \XFMG\Entity\MediaItem MediaItem
 * @property \XF\Entity\User User
 */
class MediaWatch extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_media_watch';
		$structure->shortName = 'XFMG:MediaWatch';
		$structure->primaryKey = ['user_id', 'media_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'media_id' => ['type' => self::UINT, 'required' => true],
			'notify_on' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'comment']
			],
			'send_alert' => ['type' => self::BOOL, 'default' => false],
			'send_email' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [];
		$structure->relations = [
			'MediaItem' => [
				'entity' => 'XFMG:MediaItem',
				'type' => self::TO_ONE,
				'conditions' => 'media_id',
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