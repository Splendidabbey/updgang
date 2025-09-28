<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;

/**
 * Class Redeem
 *
 * @package DBTech\Credits\EventTrigger
 */
class Redeem extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'useOwner' => true,
			'canRebuild' => true,

			'multiplier' => self::MULTIPLIER_CURRENCY
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @throws \XF\PrintableException
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$log = Helper::createEntity(\DBTech\Credits\Entity\RedeemLog::class);
		$log->user_id = $transaction->user_id;
		$log->redeem_date = $transaction->dateline;
		$log->redeem_code = $transaction->reference_id;
		$log->event_id = $transaction->event_id;
		$log->currency_id = $transaction->currency_id;
		$log->amount = $transaction->amount;
		$log->message = $transaction->message;
		$log->save();
	}

	/**
	 * @param TransactionEntity $transaction
	 */
	public function onReject(TransactionEntity $transaction): void
	{
		$this->app()->db()->delete('xf_dbtech_credits_redeem_log', '
			user_id = ? AND redeem_date = ? AND redeem_code = ?
			AND event_id = ? AND currency_id = ? AND amount = ?
		', [
			$transaction->user_id,
			$transaction->dateline,
			$transaction->reference_id,
			$transaction->event_id,
			$transaction->currency_id,
			$transaction->amount
		]);
	}

	/**
	 * @param EventEntity $event
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return bool
	 */
	protected function assertEvent(EventEntity $event, \XF\Entity\User $user, \ArrayObject $extraParams): bool
	{
		if (!$event->getSetting('redeem_code'))
		{
			// No code configured
			return false;
		}

		if ($event->getSetting('redeem_code') != $extraParams->code)
		{
			// Invalid code passed
			return false;
		}

		if ($event->getSetting('redeem_startdate') > \XF::$time)
		{
			// Hasn't started yet
			return false;
		}

		if (
			$event->getSetting('redeem_enddate')
			&& $event->getSetting('redeem_enddate') <= \XF::$time
		) {
			// Has ended
			return false;
		}

		// Grab our stats
		$stats = \XF::db()
			->fetchRow("
			SELECT
				COUNT(DISTINCT user_id) AS users,
				SUM(user_id = ?) AS total
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'redeem'
				AND reference_id = ?
		", [
				$extraParams['source_user_id'],
				$extraParams['code']
			])
		;


		if (
			$event->getSetting('redeem_maxtimes')
			&& $stats['total'] >= $event->getSetting('redeem_maxtimes')
		) {
			// This user has redeemed it too many times
			return false;
		}

		if (
			$event->getSetting('redeem_maxusers')
			&& $stats['users'] >= $event->getSetting('redeem_maxusers')
		) {
			// This code has been redeemed too many times
			return false;
		}

		// Set the multiplier appropriately
		$extraParams->multiplier = $event->getSetting('redeem_amount');

		return parent::assertEvent($event, $user, $extraParams);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($which == 'spent')
		{
			return $this->getAlertPhrase('dbtech_credits_paid_x_y_via_redeem', $transaction, [
				'code' => $transaction->reference_id
			]);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_earned_x_y_via_redeem', $transaction, [
				'code' => $transaction->reference_id
			]);
		}
	}

	/**
	 * @return array
	 */
	public function getLabels(): array
	{
		$labels = parent::getLabels();

		$labels['owner_explain'] = \XF::phrase('dbtech_credits_event_owner_explain_account');
		$labels['owner_only_others'] = \XF::phrase('dbtech_credits_event_owner_only_others_account');
		$labels['owner_only_own'] = \XF::phrase('dbtech_credits_event_owner_only_own_account');

		return $labels;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFilterOptions(): array
	{
		$filterOptions = parent::getFilterOptions();

		return \array_merge($filterOptions, [
			'redeem_amount' => 'num',
			'redeem_startdate' => 'datetime',
			'redeem_enddate' => 'datetime',
			'redeem_code' => 'str',
			'redeem_maxtimes' => 'uint',
			'redeem_maxusers' => 'uint'
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 *
	 * @throws \XF\PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var \DBTech\Credits\Entity\RedeemLog $entity */

		// Then properly add or remove credits
		$this->apply($entity->redeem_code, [
			'currency_id' => $entity->currency_id,
			'multiplier' => $entity->amount,
			'message' => $entity->message,
			'code' => $entity->redeem_code,

			'timestamp' => $entity->redeem_date,
			'enableAlert' => false,
			'runPostSave' => false
		], $entity->User);
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return ['User'];
	}
}