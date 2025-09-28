<?php

namespace DBTech\Credits\Repository;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Transaction
 * @package DBTech\Credits\Repository
 */
class Transaction extends Repository
{
	/**
	 * @param array $limits
	 *
	 * @return \DBTech\Credits\Finder\Transaction
	 * @throws \InvalidArgumentException
	 */
	public function findTransactionsForOverviewList(array $limits = []): \DBTech\Credits\Finder\Transaction
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false
		], $limits);

		/** @var \DBTech\Credits\Finder\Transaction $transactionFinder */
		$transactionFinder = Helper::finder(\DBTech\Credits\Finder\Transaction::class);

		$transactionFinder
			->with('full')
			->with('Event', true)
			->useDefaultOrder()
			->indexHint('FORCE', 'transaction_date');

		if ($limits['visibility'])
		{
			$transactionFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}

		return $transactionFinder;
	}

	/**
	 * @return Finder
	 */
	public function findTransactionsForList(): Finder
	{
		return Helper::finder(\DBTech\Credits\Finder\Transaction::class)->order('transaction_id', 'DESC');
	}

	/**
	 * @param \XF\Entity\User $receiver
	 * @param \XF\Entity\User $sender
	 * @param \DBTech\Credits\Entity\Transaction $transaction
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendTransactionAlert(
		\XF\Entity\User $receiver,
		\XF\Entity\User $sender,
		\DBTech\Credits\Entity\Transaction $transaction,
		array $extra = []
	): bool {
		$extra = array_merge($extra, [
			'depends_on_addon_id' => 'DBTech/Credits',
		]);

		$alertRepo = Helper::repository(\XF\Repository\UserAlert::class);

		if ($receiver->user_id == $sender->user_id || !$sender->user_id)
		{
			$alertRepo->alert(
				$receiver,
				0,
				'',
				'dbtech_credits_txn',
				$transaction->transaction_id,
				$transaction->event_trigger_id,
				$extra
			);
		}
		else
		{
			// Sent from another user
			$alertRepo->alertFromUser(
				$receiver,
				$sender,
				'dbtech_credits_txn',
				$transaction->transaction_id,
				$transaction->event_trigger_id,
				$extra
			);
		}

		return true;
	}

	/**
	 * @param \DBTech\Credits\Entity\Transaction $transaction
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 * @param \XF\Entity\User|null $forceUser
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		\DBTech\Credits\Entity\Transaction $transaction,
		string $action,
		string $reason = '',
		array $extra = [],
		?\XF\Entity\User $forceUser = null
	): bool {
		if (!$forceUser)
		{
			if (!$transaction->user_id || !$transaction->TargetUser)
			{
				return false;
			}

			$forceUser = $transaction->TargetUser;
		}

		$extra = array_merge([
			'title' => $transaction->Event->getTitle(),
//			'link' => $this->app()->router('public')->buildLink('nopath:dbtech-credits', $transaction),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Credits',
		], $extra);

		$alertRepo = Helper::repository(\XF\Repository\UserAlert::class);
		$alertRepo->alert(
			$forceUser,
			0,
			'',
			'user',
			$transaction->user_id,
			"dbt_credits_txn_$action",
			$extra
		);

		return true;
	}
}