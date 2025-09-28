<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\PurchaseTransaction as PurchaseTransactionEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;

/**
 * Class Purchase
 *
 * @package DBTech\Credits\EventTrigger
 */
class Purchase extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'useOwner' => true,
			'canRebuild' => true,

			'multiplier' => self::MULTIPLIER_CURRENCY
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @throws \Exception
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$log = Helper::createEntity(PurchaseTransactionEntity::class);
		$log->user_id = $transaction->user_id;
		$log->transaction_date = $transaction->dateline;
		$log->from_user_id = $transaction->source_user_id;
		$log->event_id = $transaction->event_id;
		$log->currency_id = $transaction->currency_id;
		$log->amount = $transaction->amount;
		$log->cost = $transaction->Event->getSetting('purchase_cost');
		$log->message = $transaction->message;
		$log->save();
	}

	/**
	 * @param TransactionEntity $transaction
	 */
	public function onReject(TransactionEntity $transaction): void
	{
		$this->app()->db()->delete('xf_dbtech_credits_purchase_transaction', '
			user_id = ? AND transaction_date = ? AND from_user_id = ?
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
	 * @param EventEntity $event
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return bool
	 */
	protected function assertEvent(EventEntity $event, \XF\Entity\User $user, \ArrayObject $extraParams): bool
	{
		if (
			!$event->getSetting('purchase_amount')
			|| $event->getSetting('purchase_amount') != abs($extraParams->multiplier)
		) {
			return false;
		}

		return parent::assertEvent($event, $user, $extraParams);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		if ($transaction->source_user_id == $transaction->user_id)
		{
			if ($transaction->negate)
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_purchase_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_purchased_x_y_via_purchase', $transaction);
			}
		}
		else
		{
			if ($transaction->negate)
			{
				return $this->getAlertPhrase('dbtech_credits_x_took_y_z_via_purchase_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_x_gifted_y_z_via_purchase', $transaction, [
					'user' => new \XF\PreEscaped('<a href="' .
						\XF::app()->router()->buildLink('canonical:members', $transaction->SourceUser) .
						'" class="fauxBlockLink-blockLink" data-xf-click="overlay">' . $transaction->SourceUser->username .
						'</a>')
				]);
			}
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
			'purchase_description' => 'str',
			'purchase_cost' => 'unum',
			'purchase_amount' => 'unum',
			'payment_profile_ids' => 'array-uint',
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 *
	 * @throws \XF\PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var PurchaseTransactionEntity $entity */

		$this->apply($entity->transaction_id, [
			'currency_id' => $entity->currency_id,
			'multiplier' => $entity->amount,
			'message' => $entity->message,

			'timestamp' => $entity->transaction_date,
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