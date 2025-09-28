<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;

/**
 * Class Adjust
 *
 * @package DBTech\Credits\EventTrigger
 */
class Adjust extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'canCancel' => true,
			'useUserGroups' => false,
			'canRebuild' => true,

			'multiplier' => self::MULTIPLIER_CURRENCY
		]);
	}

	/**
	 * @param EventEntity $event
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 * @param TransactionEntity $transaction
	 *
	 * @return void
	 */
	protected function performApplyTransactionChecks(
		EventEntity $event,
		\XF\Entity\User $user,
		\ArrayObject $extraParams,
		TransactionEntity $transaction
	) {
		if (!$this->getOption('adminOverride'))
		{
			parent::performApplyTransactionChecks($event, $user, $extraParams, $transaction);
		}
	}

	/**
	 * @param EventEntity $event
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 * @param TransactionEntity $transaction
	 *
	 * @return void
	 */
	protected function performNegateTransactionChecks(
		EventEntity $event,
		\XF\Entity\User $user,
		\ArrayObject $extraParams,
		TransactionEntity $transaction
	) {
		if (!$this->getOption('adminOverride'))
		{
			parent::performNegateTransactionChecks($event, $user, $extraParams, $transaction);
		}
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @throws \XF\PrintableException
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$log = Helper::createEntity(\DBTech\Credits\Entity\AdjustLog::class);
		$log->user_id = $transaction->user_id;
		$log->adjust_date = $transaction->dateline;
		$log->adjust_user_id = $transaction->source_user_id;
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
		$this->app()->db()->delete('xf_dbtech_credits_adjust_log', '
			user_id = ? AND adjust_date = ? AND adjust_user_id = ?
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
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($transaction->source_user_id == $transaction->user_id)
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_removed_x_y_via_adjust', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_added_x_y_via_adjust', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_x_took_y_z_via_adjust', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_x_gave_y_z_via_adjust', $transaction);
			}
		}
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
		/** @var \DBTech\Credits\Entity\AdjustLog $entity */
		$func = $entity->amount < 0 ? 'undo' : 'apply';

		// Then properly add or remove credits
		$this->$func($entity->adjust_user_id, [
			'currency_id' => $entity->currency_id,
			'multiplier' => $entity->amount,
			'message' => $entity->message,
			'source_user_id' => $entity->adjust_user_id,

			'timestamp' => $entity->adjust_date,
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
		return ['User', 'AdjustedBy'];
	}
}