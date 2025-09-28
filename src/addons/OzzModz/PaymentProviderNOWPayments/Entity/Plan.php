<?php

namespace OzzModz\PaymentProviderNOWPayments\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $plan_id
 * @property string $purchasable_type_id
 * @property string $purchasable_id
 * @property string $title
 * @property int $interval_day
 * @property int $amount
 * @property string $currency
 *
 * RELATIONS
 * @property \XF\Entity\Purchasable $Purchasable
 */
class Plan extends Entity
{
	/**
	 * @param Structure $structure
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ozzmodz_nowpayments_plan';
		$structure->shortName = 'OzzModz\PaymentProviderNOWPayments:Plan';
		$structure->primaryKey = 'plan_id';
		$structure->columns = [
			'plan_id' => ['type' => static::UINT, 'required' => true],
			'purchasable_type_id' => ['type' => static::BINARY, 'maxLength' => 50, 'required' => true],
			'purchasable_id' => ['type' => static::BINARY, 'maxLength' => 50, 'required' => true],
			'title' => ['type' => static::STR, 'maxLength' => 255, 'required' => true],
			'interval_day' => ['type' => static::UINT, 'required' => true],
			'amount' => ['type' => static::UINT, 'required' => true],
			'currency' => ['type' => static::STR, 'maxLength' => 3, 'required' => true],
		];

		$structure->relations = [
			'Purchasable' => [
				'entity' => 'XF:Purchasable',
				'type' => self::TO_ONE,
				'conditions' => 'purchasable_type_id'
			]
		];

		return $structure;
	}
}