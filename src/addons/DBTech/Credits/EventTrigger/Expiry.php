<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;

/**
 * Class Expiry
 *
 * @package DBTech\Credits\EventTrigger
 */
class Expiry extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => false,
			'canCharge' => true,
			'canCancel' => true,
			'useUserGroups' => false,

			'multiplier' => self::MULTIPLIER_CURRENCY
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		// This can only go one way
		return $this->getAlertPhrase('dbtech_credits_spent_x_y_via_expiry', $transaction);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}

	/**
	 * @param int $currencyId
	 *
	 * @throws \XF\PrintableException
	 */
	protected function assertEventExists(int $currencyId = 0)
	{
		if (!$currencyId)
		{
			return;
		}

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('currency_id', $currencyId)
			->where('event_trigger_id', $this->getContentType())
			->fetch()
		;
		if ($events->count() > 1)
		{
			throw new \LogicException(
				"Multiple event definitions exist for DragonByte Credits event: " .
				\XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType())
			);
		}

		/** @var EventEntity $event */
		$event = $events->first();
		if ($event)
		{
			// Making sure the event is set up correctly
			$event->bulkSet([
				'active'           => true,
				'charge'           => true,
				'main_add'         => 0,
				'mult_add'         => 0,
				'mult_sub'         => 0,
				'frequency'        => 1,
				'applymax'         => 0,
				'applymax_peruser' => 0,
				'maxtime'          => 0,
				'settings'         => ['expiry' => 0],
				'alert'            => 1,
			]);
			$event->saveIfChanged($saved);

			if ($saved)
			{
				$this->setEvents();
			}
		}
		else
		{
			$event = Helper::createEntity(EventEntity::class);
			$event->bulkSet([
				'title'            => \XF::phrase('dbtech_credits_eventtrigger_title.' . $this->getContentType()),
				'active'           => true,
				'currency_id'      => $currencyId,
				'event_trigger_id' => $this->getContentType(),
				'charge'           => true,
				'main_add'         => 0,
				'mult_add'         => 1,
				'mult_sub'         => 1,
				'frequency'        => 1,
				'applymax'         => 0,
				'applymax_peruser' => 0,
				'maxtime'          => 0,
				'settings'         => ['expiry' => 0],
				'alert'            => 1,
			]);
			$event->save();

			$this->setEvents();
		}
	}
}