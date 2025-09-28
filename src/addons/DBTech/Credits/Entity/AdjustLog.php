<?php

namespace DBTech\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $adjust_log_id
 * @property int $user_id
 * @property int $adjust_date
 * @property int $adjust_user_id
 * @property int $event_id
 * @property int $currency_id
 * @property float $amount
 * @property string $message
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $AdjustedBy
 * @property \DBTech\Credits\Entity\Event $Event
 * @property \DBTech\Credits\Entity\Currency $Currency
 */
class AdjustLog extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_adjust_log';
		$structure->shortName = 'DBTech\Credits:AdjustLog';
		$structure->primaryKey = 'adjust_log_id';
		$structure->columns = [
			'adjust_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'adjust_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'adjust_user_id' => ['type' => self::UINT, 'required' => true],
			'event_id' => ['type' => self::UINT, 'required' => true],
			'currency_id' => ['type' => self::UINT, 'required' => true],
			'amount' => ['type' => self::FLOAT, 'required' => true],
			'message' => ['type' => self::STR, 'default' => ''],
		];
		$structure->getters = [];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'AdjustedBy' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$adjust_user_id']],
				'primary' => true
			],
			'Event' => [
				'entity' => 'DBTech\Credits:Event',
				'type' => self::TO_ONE,
				'conditions' => 'event_id',
				'primary' => true,
			],
			'Currency' => [
				'entity' => 'DBTech\Credits:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			]
		];

		return $structure;
	}
}