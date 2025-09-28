<?php

namespace DBTech\Credits\Service\Currency;

use DBTech\Credits\Helper;

/**
 * Class DeleteCleanUp
 *
 * @package DBTech\Credits\Service\Currency
 */
class DeleteCleanUp extends \XF\Service\AbstractService
{
	use \XF\MultiPartRunnerTrait;

	protected int $currencyId;
	protected string $title;
	protected array $steps = [
		'stepDeleteTransactions',
	];


	/**
	 * DeleteCleanUp constructor.
	 *
	 * @param \XF\App $app
	 * @param int $currencyId
	 * @param string $title
	 */
	public function __construct(\XF\App $app, int $currencyId, string $title)
	{
		parent::__construct($app);

		$this->currencyId = $currencyId;
		$this->title = $title;
	}

	/**
	 * @return array
	 */
	protected function getSteps(): array
	{
		return $this->steps;
	}

	/**
	 * @param int|float $maxRunTime
	 *
	 * @return \XF\ContinuationResult
	 */
	public function cleanUp($maxRunTime = 0): \XF\ContinuationResult
	{
		$this->db()->beginTransaction();
		$result = $this->runLoop($maxRunTime);
		$this->db()->commit();

		return $result;
	}

	/**
	 * @param int|null $lastOffset
	 * @param int|float|null $maxRunTime
	 *
	 * @return int|float|null
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\PrintableException
	 */
	protected function stepDeleteTransactions(?int $lastOffset, $maxRunTime): ?int
	{
		$start = microtime(true);

		/** @var \DBTech\Credits\Entity\Transaction[]|\XF\Mvc\Entity\AbstractCollection $transactions */
		$finder = Helper::finder(\DBTech\Credits\Finder\Transaction::class)
			->where('currency_id', $this->currencyId)
			->order('transaction_id');

		if ($lastOffset !== null)
		{
			$finder->where('transaction_id', '>', $lastOffset);
		}

		$maxFetch = 1000;
		$transactions = $finder->fetch($maxFetch);
		$fetchedTransactions = count($transactions);

		if (!$fetchedTransactions)
		{
			return null; // done or nothing to do
		}

		foreach ($transactions AS $transaction)
		{
			$lastOffset = $transaction->transaction_id;

//			$transaction->setOption('log_moderator', false);
			$transaction->delete();

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				return $lastOffset; // continue at this position
			}
		}

		if ($fetchedTransactions == $maxFetch)
		{
			return $lastOffset; // more to do
		}

		return null;
	}
}