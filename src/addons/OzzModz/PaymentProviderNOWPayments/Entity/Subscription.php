<?php

namespace OzzModz\PaymentProviderNOWPayments\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $purchase_request_key
 * @property string $email
 * @property int $subscription_id
 * @property int $subscription_plan_id
 * @property int $create_date
 *
 * RELATIONS
 * @property \XF\Entity\PurchaseRequest $PurchaseRequest
 */
class Subscription extends Entity
{
	protected function _setupDefaults()
	{
		$this->create_date = \XF::$time;
	}

	/**
	 * @param Structure $structure
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ozzmodz_nowpayments_subscription';
		$structure->shortName = 'OzzModz\PaymentProviderNOWPayments:Subscription';
		$structure->primaryKey = 'purchase_request_key';
		$structure->columns = [
			'purchase_request_key' => ['type' => static::BINARY, 'maxLength' => 32, 'required' => true],
			'email' => ['type' => static::STR, 'maxLength' => 120, 'required' => true],
			'subscription_id' => ['type' => static::UINT, 'required' => true],
			'subscription_plan_id' => ['type' => static::UINT, 'required' => true],
			'create_date' => ['type' => static::UINT, 'required' => true],
		];

		$structure->relations = [
			'PurchaseRequest' => [
				'entity' => 'XF:PurchaseRequest',
				'type' => self::TO_ONE,
				'conditions' => [
					['request_key', '=', '$purchase_request_key']
				]
			]
		];

		return $structure;
	}
}