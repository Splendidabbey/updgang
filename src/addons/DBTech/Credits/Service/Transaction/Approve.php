<?php

namespace DBTech\Credits\Service\Transaction;

use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Helper;

/**
 * Class Approve
 *
 * @package DBTech\Credits\Service\Transaction
 */
class Approve extends \XF\Service\AbstractService
{
	protected Transaction $transaction;
	protected bool $notify = true;
	protected int $notifyRunTime = 3;
	protected string $reason = '';

	/**
	 * Approve constructor.
	 *
	 * @param \XF\App $app
	 * @param Transaction $transaction
	 */
	public function __construct(\XF\App $app, Transaction $transaction)
	{
		parent::__construct($app);
		$this->transaction = $transaction;
	}

	/**
	 * @return Transaction
	 */
	public function getUpdate(): Transaction
	{
		return $this->transaction;
	}

	/**
	 * @param bool $notify
	 *
	 * @return $this
	 */
	public function setNotify(bool $notify): Approve
	{
		$this->notify = $notify;

		return $this;
	}

	/**
	 * @param int $time
	 *
	 * @return $this
	 */
	public function setNotifyRunTime(int $time): Approve
	{
		$this->notifyRunTime = $time;

		return $this;
	}

	/**
	 * @param string $reason
	 *
	 * @return $this
	 */
	public function setReason(string $reason): Approve
	{
		$this->reason = $reason;

		return $this;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function approve(): bool
	{
		if ($this->transaction->transaction_state == 'moderated')
		{
			$this->transaction->transaction_state = 'visible';
			$this->transaction->save();

			$this->onApprove();
			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function reject(): bool
	{
		if ($this->transaction->transaction_state == 'moderated')
		{
			$this->transaction->delete();

			$this->onReject();
			return true;
		}

		return false;
	}

	/**
	 *
	 */
	protected function onApprove(): void
	{
		if ($this->notify)
		{
			$transactionRepo = Helper::repository(\DBTech\Credits\Repository\Transaction::class);
			$transactionRepo->sendModeratorActionAlert($this->transaction, 'approve', $this->reason);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function onReject(): void
	{
		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$handler = $eventTriggerRepo->getHandler($this->transaction->event_trigger_id);

		$handler->onReject($this->transaction);

		if ($this->notify)
		{
			$transactionRepo = Helper::repository(\DBTech\Credits\Repository\Transaction::class);
			$transactionRepo->sendModeratorActionAlert($this->transaction, 'reject', $this->reason);
		}
	}
}