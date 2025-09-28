<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Exception\SkipEventException;
use DBTech\Credits\Exception\StopEventTriggerException;

/**
 * COLUMNS
 * @property int|null $event_id
 * @property string $title_
 * @property int $currency_id
 * @property string $event_trigger_id
 * @property array $user_group_ids
 * @property array $node_ids
 * @property bool $active
 * @property bool $moderate
 * @property bool $charge
 * @property float $main_add
 * @property float $main_sub
 * @property float $mult_add
 * @property float $mult_sub
 * @property int $delay
 * @property int $frequency
 * @property int $maxtime
 * @property int $applymax
 * @property bool $applymax_peruser
 * @property float $upperrand
 * @property float $multmin
 * @property float $multmax
 * @property int $minaction
 * @property int $owner
 * @property int $curtarget
 * @property bool $alert
 * @property bool $display
 * @property array $settings
 *
 * GETTERS
 * @property string|\XF\Phrase $title
 * @property mixed $cost_phrase
 * @property \XF\Phrase $EventTriggerTitle
 *
 * RELATIONS
 * @property \DBTech\Credits\Entity\Currency $Currency
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Event[] $Transfers
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Credits\Entity\Transaction[] $Transactions
 */
class Event extends Entity
{
	/**
	 * @return bool
	 */
	public function canView(): bool
	{
		return ($this->isActive() && $this->isDisplayed());
	}

	/**
	 * @return string|\XF\Phrase
	 */
	public function getTitle()
	{
		if (!$this->exists())
		{
			return $this->getEventTriggerTitle();
		}

		switch ($this->event_trigger_id)
		{
			case 'purchase':
				return \XF::phrase('dbtech_credits_event_title.' . $this->event_trigger_id, [
					'currency' => $this->Currency->title,
					'amount' => $this->getSetting('purchase_amount')
				]);

			default:
				if ($this->title_)
				{
					return $this->title_;
				}

				return $this->getEventTriggerTitle();
		}
	}

	/**
	 * @return \XF\Phrase
	 */
	public function getEventTriggerTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_eventtrigger_title.' . $this->event_trigger_id);
	}

	/**
	 * @return \XF\Phrase
	 */
	public function getDescription(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_eventtrigger_description.' . $this->event_trigger_id);
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @return bool
	 */
	public function isDisplayed(): bool
	{
		return $this->display;
	}

	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return bool
	 */
	public function isValidForUser(?\XF\Entity\User $user = null): bool
	{
		/** @var \DBTech\Credits\XF\Entity\User $user */
		$user = $user ?: \XF::visitor();

		return $user->canTriggerDbtechCreditsEvents();
	}

	/**
	 * @return bool
	 */
	public function canPurchase(): bool
	{
		return (
			$this->isActive()
			&& \XF::visitor()->user_id
			&& $this->event_trigger_id == 'purchase'
			&& $this->settings['purchase_cost']
			&& $this->settings['purchase_amount']
		);
	}

	/**
	 * @return mixed
	 */
	public function getCostPhrase()
	{
		return $this->app()
			->data('XF:Currency')
			->languageFormat(
				$this->getSetting('purchase_cost'),
				\XF::options()->dbtech_credits_eventtrigger_purchase_currency
			)
		;
	}

	/**
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getEventTriggerHandler(bool $throw = false): ?AbstractHandler
	{
		return Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler($this->event_trigger_id, $throw)
		;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getSetting(string $key)
	{
		if (!isset($this->settings[$key]))
		{
			$defaultSettings = $this->getDefaultSettings();
			if (!isset($defaultSettings[$key]))
			{
				throw new \LogicException("Attempting to access setting key '$key' which doesn't exist.");
			}

			return $defaultSettings[$key];
		}

		return $this->settings[$key];
	}

	/**
	 * @return array
	 */
	public function getDefaultSettings(): array
	{
		return [
			// Global
			'expiry' 					=> 0,

			// Trophy
			'trophy' 					=> [],

			// Post Rating
			'rating' 					=> [],

			// React / Reacted
			'reaction_ids' 				=> [],

			// Download/Downloaded
			'extension_include' 		=> '',
			'extension_exclude' 		=> '',

			// Redeem
			'redeem_amount' 			=> 0,
			'redeem_startdate' 			=> \XF::$time,
			'redeem_enddate' 			=> '',
			'redeem_code' 				=> '',
			'redeem_maxtimes' 			=> 1,
			'redeem_maxusers' 			=> 0,

			// Purchase
			'purchase_description' 		=> '',
			'purchase_cost' 			=> '',
			'purchase_amount' 			=> '',
			'payment_profile_ids' 		=> [],

			// Post / Reply
			'threadid' 					=> 0,

			// Various "view" style events
			'apply_guest' 				=> 0,
		];
	}

	/**
	 * @param AbstractHandler $eventTrigger
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return float|int
	 * @throws SkipEventException
	 * @throws StopEventTriggerException
	 * @throws \XF\PrintableException
	 */
	public function getCalculatedAmount(
		AbstractHandler $eventTrigger,
		\XF\Entity\User $user,
		\ArrayObject $extraParams
	) {
		if (
			($this->charge && !$extraParams->negate)
			|| (!$this->charge && $extraParams->negate)
		) {
			// We're charging and not reverting, OR we're not charging and reverting
			$negateMultiplier = -1;
		}
		else
		{
			// We're charging and reverting, OR we're not charging and not reverting
			$negateMultiplier = 1;
		}

		$mainSetting = ($extraParams->negate ? 'main_sub' : 'main_add');
		$multSetting = ($extraParams->negate ? 'mult_sub' : 'mult_add');

		// Set the initial amount
		$amount = ($this->{$mainSetting} * $negateMultiplier);

		// Whether this event is happening right now
		$now = ($extraParams->timestamp == \XF::$time);

		if (!$extraParams->negate && !empty($this->upperrand))
		{
			// vary the amount by the upper bound
			$dec = explode('.', $this->upperrand);
			if (!isset($dec[1]))
			{
				// Set this
				$dec[1] = 0;
			}

			$rand = mt_rand(0, (int)$dec[0]);
			$amount += (($rand ?: 0) + $dec[1]);
		}

		if ($eventTrigger->getOption('multiplier') != AbstractHandler::MULTIPLIER_NONE)
		{
			// this action has multipliers
			$negativeMultiplier = ($extraParams->multiplier < 0);
			$addedMultiplier = ($negativeMultiplier ? -1 : 1) * $extraParams->multiplier;

			if (!$this->multmin || $addedMultiplier >= $this->multmin)
			{
				// within the bounds - check if multiplier was negative
				$extraParams->multiplier = ((!$this->multmax || $addedMultiplier <= $this->multmax) ? $extraParams->multiplier : ($negativeMultiplier ? -1 : 1) * $this->multmax);

				if ($eventTrigger->getOption('multiplier') == AbstractHandler::MULTIPLIER_CURRENCY)
				{
					switch ($this->curtarget)
					{
						case 0:
							if (!$negativeMultiplier)
							{
								// Sending user has negative multiplier
								$amount += $extraParams->multiplier;
								break;
							}

							// Sending user should have more credits removed
							$amount += $extraParams->multiplier * (1 + ($this->{$multSetting} * $negateMultiplier));
							break;

						case 1:
							if ($negativeMultiplier)
							{
								// Sending user has negative multiplier
								$amount += $extraParams->multiplier;
								break;
							}

							// Receiving user should have X removed from their recipient amount
							$amount += $extraParams->multiplier * (1 - ($this->{$multSetting} * $negateMultiplier));
							break;

						case 2:
							if ($negativeMultiplier)
							{
								// Sending user should have more credits removed
								$amount += $extraParams->multiplier * (1 + ($this->{$multSetting} * $negateMultiplier));
							}
							else
							{
								// Receiving user should have X removed from their recipient amount
								$amount += $extraParams->multiplier * (1 - ($this->{$multSetting} * $negateMultiplier));
							}
							break;
					}
				}
				else
				{
					// otherwise now
					$amount += $extraParams->multiplier * ($this->{$multSetting} * $negateMultiplier);
				}
			}
			elseif ($this->minaction == 1)
			{
				// skip the event
				throw new SkipEventException("Event ID '$this->event_id' for event trigger '$this->event_trigger_id' skipped because '$addedMultiplier' was less than '$this->multmin'");
			}
			elseif (!$extraParams->negate && $this->minaction == 2)
			{
				// stop the action
				if (!$now || !$eventTrigger->getOption('canCancel'))
				{
					throw new StopEventTriggerException("Event trigger '$this->event_trigger_id' stopped because '$addedMultiplier' was less than '$this->multmin'");
				}

				switch ($eventTrigger->getOption('multiplier'))
				{
					case AbstractHandler::MULTIPLIER_LABEL:
						throw new \XF\PrintableException(\XF::phrase("dbtech_credits_cancel_mult_$this->event_trigger_id", [
							'amount' => \XF::language()->numberFormat($this->multmin, $this->Currency->decimals)
						]));

					case AbstractHandler::MULTIPLIER_SIZE:
						if (\XF::options()->dbtech_credits_size_words)
						{
							throw new \XF\PrintableException(\XF::phrase("dbtech_credits_cancel_mult_{$this->event_trigger_id}_words", [
								'amount' => \XF::language()->numberFormat($this->multmin, $this->Currency->decimals)
							]));
						}
						else
						{
							throw new \XF\PrintableException(\XF::phrase("dbtech_credits_cancel_mult_{$this->event_trigger_id}_characters", [
								'amount' => \XF::language()->numberFormat($this->multmin, $this->Currency->decimals)
							]));
						}

						// no break
					case AbstractHandler::MULTIPLIER_CURRENCY:
						throw new \XF\PrintableException(\XF::phrase("dbtech_credits_cancel_mult_$this->event_trigger_id", [
							'amount' => \XF::language()->numberFormat($this->multmin, $this->Currency->decimals),
							'currency' => $this->Currency->title
						]));
				}
			}
		}

		if ($amount < 0.00)
		{
			// paying credits, apply now
			$positiveAmount = abs($amount);

			if (
				$now
				&& (
					!$extraParams->negate
					|| $extraParams->alwaysCheck
				)
				&& $eventTrigger->getOption('canCancel')
			) {
				if (!$user->user_id)
				{
					/*
					throw $this->exception(
						$this->plugin('XF:Error')->actionRegistrationRequired()
					);
					*/
					// not enough credits or cant spend this and action is cancelable - show error
					throw new \XF\PrintableException(\XF::phrase('dbtech_credits_cancel_blocked_guest'));
				}
				elseif ($positiveAmount > $user->{$this->Currency->column} && !$this->Currency->negative)
				{
					// not enough credits or cant spend this and action is cancelable - show error
					throw new \XF\PrintableException(\XF::phrase("dbtech_credits_cancel_price_$this->event_trigger_id", [
						'amount' => \XF::language()->numberFormat($positiveAmount, $this->Currency->decimals),
						'currency' => $this->Currency->title
					]));
				}
			}
		}

		$this->setOption('calculated_amount', $amount);

		return $amount;
	}

	/**
	 * @param int $currencyId
	 *
	 * @return bool
	 */
	protected function verifyCurrencyId(int &$currencyId): bool
	{
		return Helper::find(Currency::class, $currencyId) !== null;
	}

	/**
	 *
	 */
	protected function _postDelete()
	{
		$title = ($this->title instanceof \XF\Phrase) ? $this->title->render() : $this->title;

		$this->app()->jobManager()->enqueue('DBTech\Credits:EventDeleteCleanUp', [
			'eventId' => $this->event_id,
			'title' => $title
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_event';
		$structure->shortName = 'DBTech\Credits:Event';
		$structure->primaryKey = 'event_id';
		$structure->columns = [
			'event_id'         => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title'            => ['type' => self::STR, 'default' => ''],
			'currency_id'      => ['type' => self::UINT, 'required' => true],
			'event_trigger_id' => ['type' => self::STR],
			'user_group_ids'   => ['type' => self::JSON_ARRAY, 'default' => []],
			'node_ids'         => ['type' => self::JSON_ARRAY, 'default' => []],
			'active'           => ['type' => self::BOOL, 'default' => true],
			'moderate'         => ['type' => self::BOOL, 'default' => false],
			'charge'           => ['type' => self::BOOL, 'default' => false],
			'main_add'         => ['type' => self::FLOAT, 'default' => 0],
			'main_sub'         => ['type' => self::FLOAT, 'default' => 0],
			'mult_add'         => ['type' => self::FLOAT, 'default' => 0],
			'mult_sub'         => ['type' => self::FLOAT, 'default' => 0],
			'delay'            => ['type' => self::UINT, 'default' => 0],
			'frequency'        => ['type' => self::UINT, 'default' => 1],
			'maxtime'          => ['type' => self::UINT, 'default' => 0],
			'applymax'         => ['type' => self::UINT, 'default' => 0],
			'applymax_peruser' => ['type' => self::BOOL, 'default' => false],
			'upperrand'        => ['type' => self::FLOAT, 'default' => 0],
			'multmin'          => ['type' => self::FLOAT, 'default' => 0],
			'multmax'          => ['type' => self::FLOAT, 'default' => 0],
			'minaction'        => ['type' => self::UINT, 'default' => 0, 'max' => 2],
			'owner'            => ['type' => self::UINT, 'default' => 0, 'max' => 2],
			'curtarget'        => ['type' => self::UINT, 'default' => 0],
			'alert'            => ['type' => self::BOOL, 'default' => true],
			'display'          => ['type' => self::BOOL, 'default' => true],
			'settings'         => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'title'             => true,
			'cost_phrase'       => true,
			'EventTriggerTitle' => true
		];
		$structure->relations = [
			'Currency'     => [
				'entity'     => 'DBTech\Credits:Currency',
				'type'       => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary'    => true
			],
			'Transfers'    => [
				'entity'     => 'DBTech\Credits:Event',
				'type'       => self::TO_MANY,
				'conditions' => [
					['currency_id', '!=', '$currency_id'],
					['event_trigger_id', '=', 'transfer'],
					['Currency.inbound', '=', true],
				],
				'with'       => 'Currency'
			],
			'Transactions' => [
				'entity'     => 'DBTech\Credits:Transaction',
				'type'       => self::TO_MANY,
				'conditions' => 'event_id'
			]
		];
		$structure->options = [
			'calculated_amount'  => 0,
			'purchase_recipient' => \XF::visitor()->user_id
		];

		return $structure;
	}

	/**
	 *
	 */
	protected function _setupDefaults()
	{
		$this->active = true;
		$this->moderate = false;
		$this->charge = false;
		$this->main_add = 0;
		$this->main_sub = 0;
		$this->mult_add = 0;
		$this->mult_sub = 0;
		$this->delay = 0;
		$this->frequency = 1;
		$this->maxtime = 0;
		$this->applymax = 0;
		$this->upperrand = 0;
		$this->multmin = 0;
		$this->multmax = 0;
		$this->minaction = 0;
		$this->owner = 0;
		$this->curtarget = 0;
		$this->alert = true;
		$this->display = true;
		$this->settings = $this->getDefaultSettings();
	}
}