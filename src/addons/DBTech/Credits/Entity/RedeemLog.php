<?php

namespace DBTech\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $redeem_log_id
 * @property int $user_id
 * @property int $redeem_date
 * @property string $redeem_code
 * @property int $event_id
 * @property int $currency_id
 * @property float $amount
 * @property string $message
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \DBTech\Credits\Entity\Event $Event
 * @property \DBTech\Credits\Entity\Currency $Currency
 */
class RedeemLog extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_redeem_log';
		$structure->shortName = 'DBTech\Credits:RedeemLog';
		$structure->primaryKey = 'redeem_log_id';
		$structure->columns = [
			'redeem_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'redeem_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'redeem_code' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
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