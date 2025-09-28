<?php

namespace DBTech\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $transaction_id
 * @property int $event_id
 * @property int $user_id
 * @property int $from_user_id
 * @property int $transaction_date
 * @property float $amount
 * @property float $cost
 * @property string $currency_id
 * @property string $message
 * @property int $ip_id
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Event[] $Event
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $FromUser
 * @property \XF\Entity\Ip $Ip
 */
class PurchaseTransaction extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_purchase_transaction';
		$structure->shortName = 'DBTech\Credits:PurchaseTransaction';
		$structure->primaryKey = 'transaction_id';
		$structure->columns = [
			'transaction_id'   => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'event_id'         => ['type' => self::UINT, 'required' => true],
			'user_id'          => ['type' => self::UINT, 'required' => true],
			'from_user_id'     => ['type' => self::UINT, 'required' => true],
			'transaction_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'amount'           => ['type' => self::FLOAT, 'required' => true, 'min' => 0.01],
			'cost'             => ['type' => self::FLOAT, 'required' => true, 'min' => 0.01],
			'currency_id'      => ['type' => self::STR, 'required' => true],
			'message'          => ['type' => self::STR, 'default' => ''],
			'ip_id'            => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'Event' => [
				'entity' => 'DBTech\Credits:Event',
				'type' => self::TO_MANY,
				'conditions' => 'event_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'FromUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$from_user_id']
				],
				'primary' => true
			],
			'Ip' => [
				'entity' => 'XF:Ip',
				'type' => self::TO_ONE,
				'conditions' => 'ip_id',
				'primary' => true
			]
		];
		return $structure;
	}
}