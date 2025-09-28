<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property string $content_hash
 * @property int $user_id
 *
 * GETTERS
 * @property Currency $Currency
 *
 * RELATIONS
 * @property \DBTech\Credits\Entity\Charge $Charge
 * @property \XF\Entity\Post $Post
 * @property \XF\Entity\User $User
 */
class ChargePurchase extends Entity
{
	/**
	 * @return \DBTech\Credits\EventTrigger\AbstractHandler|null
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function getHandler(): ?\DBTech\Credits\EventTrigger\AbstractHandler
	{
		$currency = $this->Currency;
		$currency->verifyChargeEvent();

		return Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('charge')
		;
	}

	/**
	 * @return Currency
	 */
	public function getCurrency(): Currency
	{
		return Helper::repository(\DBTech\Credits\Repository\Currency::class)
			->getChargeCurrency()
		;
	}

	/**
	 * @param Currency|null $currency
	 */
	public function setCurrency(?Currency $currency = null)
	{
		$this->_getterCache['Currency'] = $currency;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_charge_purchase';
		$structure->shortName = 'DBTech\Credits:ChargePurchase';
		$structure->primaryKey = ['content_type', 'content_id', 'content_hash', 'user_id'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'content_hash' => ['type' => self::STR, 'required' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
		];
		$structure->getters = [
			'Currency' => true,
		];
		$structure->relations = [
			'Charge' => [
				'entity'     => 'DBTech\Credits:Charge',
				'type'       => self::TO_ONE,
				'conditions' => [
					['content_type', '=', '$content_type'],
					['content_id', '=', '$content_id'],
					['content_hash', '=', '$content_hash']
				],
			],
			'Post'   => [
				'entity'     => 'XF:Post',
				'type'       => self::TO_ONE,
				'primary'    => true,
				'conditions' => [
					['post_id', '=', '$content_id'],
				],
				'with'       => ['Thread']
			],
			'User'   => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true
			],
		];
		return $structure;
	}
}