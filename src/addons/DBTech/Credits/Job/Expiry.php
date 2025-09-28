<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Helper;
use XF\Job\AbstractRebuildJob;

/**
 * Class Expiry
 *
 * @package DBTech\Credits\Job
 */
class Expiry extends AbstractRebuildJob
{
	/** @var \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\AbstractCollection */
	protected $currencies;


	/**
	 * @param array $data
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function setupData(array $data): array
	{
		$this->currencies = Helper::finder(\DBTech\Credits\Finder\Currency::class)
			->fetch()
			->filter(function (Currency $currency): ?Currency
			{
				if (!$currency->isActive())
				{
					return null;
				}

				return $currency;
			})
		;

		return parent::setupData($data);
	}

	/**
	 * @param $start
	 * @param $batch
	 *
	 * @return array
	 */
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			'
				SELECT transaction_id
				FROM xf_dbtech_credits_transaction
				WHERE transaction_id > ?
					AND expiry_date <= ?
				  	AND expiry_date > 0
					AND transaction_state = \'visible\'
				ORDER BY transaction_id
			',
			$batch
		), [$start, \XF::$time]);
	}

	/**
	 * @param $id
	 *
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	protected function rebuildById($id)
	{
		$transaction = Helper::find(\DBTech\Credits\Entity\Transaction::class, $id);
		if (!$transaction)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$expiry = $eventTriggerRepo->getHandler('expiry');
		$expiry->apply($id, [
			'multiplier'  => (-1 * $transaction->amount),
			'currency_id' => $transaction->currency_id
		], $transaction->TargetUser);

		// Flag transaction as expired
		$transaction->fastUpdate('expiry_date', 0);
	}

	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_credits');
	}
}