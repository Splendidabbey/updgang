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
 * @property float $cost
 *
 * GETTERS
 * @property Currency $Currency
 * @property null|\XF\Mvc\Entity\Entity $Content
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\ChargePurchase[] $Purchases
 */
class Charge extends Entity
{
	/**
	 * @return null|\XF\Mvc\Entity\Entity
	 */
	public function getContent(): ?Entity
	{
		return \XF::app()->findByContentType($this->content_type, $this->content_id);
	}

	/**
	 * @param Entity|null $content
	 */
	public function setContent(?Entity $content = null)
	{
		$this->_getterCache['Content'] = $content;
	}

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
			->getHandler('content')
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
		$structure->table = 'xf_dbtech_credits_charge';
		$structure->shortName = 'DBTech\Credits:Charge';
		$structure->primaryKey = ['content_type', 'content_id', 'content_hash'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'content_hash' => ['type' => self::STR, 'required' => true],
			'cost'         => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
		];
		$structure->getters = [
			'Currency' => true,
			'Content' => true,
		];
		$structure->relations = [
			'Purchases' => [
				'entity'     => 'DBTech\Credits:ChargePurchase',
				'type'       => self::TO_MANY,
				'conditions' => [
					['content_type', '=', '$content_type'],
					['content_id', '=', '$content_id'],
					['content_hash', '=', '$content_hash']
				],
				'with'       => ['User'],
				'key'        => 'user_id'
			]
		];

		return $structure;
	}
}