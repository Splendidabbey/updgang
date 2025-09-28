<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int album_id
 * @property string notify_on
 * @property bool send_alert
 * @property bool send_email
 *
 * RELATIONS
 * @property \XFMG\Entity\Album Album
 * @property \XF\Entity\User User
 */
class AlbumWatch extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_album_watch';
		$structure->shortName = 'XFMG:AlbumWatch';
		$structure->primaryKey = ['user_id', 'album_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'album_id' => ['type' => self::UINT, 'required' => true],
			'notify_on' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'media', 'comment', 'media_comment']
			],
			'send_alert' => ['type' => self::BOOL, 'default' => false],
			'send_email' => ['type' => self::BOOL, 'default' => false],
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
			],
		];

		return $structure;
	}
}