<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;

/**
 * Class Donate
 *
 * @package DBTech\Credits\EventTrigger
 */
class Donate extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canCancel' => true,
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
		$log = Helper::createEntity(\DBTech\Credits\Entity\DonationLog::class);
		$log->user_id = $transaction->user_id;
		$log->donation_date = $transaction->dateline;
		$log->donation_user_id = $transaction->source_user_id;
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
		$this->app()->db()->delete('xf_dbtech_credits_donation_log', '
			user_id = ? AND donation_date = ? AND donation_user_id = ?
			AND event_id = ? AND currency_id = ? AND amount = ?
		', [
			$transaction->user_id,
			$transaction->dateline,
			$transaction->source_user_id,
			$transaction->event_id,
			$transaction->currency_id,
			$transaction->amount
		]);
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var \DBTech\Credits\Entity\DonationLog $entity */
		$func = $entity->amount < 0 ? 'undo' : 'apply';

		// Then properly add or remove credits
		$this->$func($entity->donation_user_id, [
			'currency_id' => $entity->currency_id,
			'multiplier' => $entity->amount,
			'message' => $entity->message,
			'source_user_id' => $entity->donation_user_id,

			'timestamp' => $entity->donation_date,
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
		return ['User', 'DonatedBy'];
	}
}