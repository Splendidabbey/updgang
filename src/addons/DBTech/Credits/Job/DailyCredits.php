<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Helper;
use XF\Job\AbstractRebuildJob;

/**
 * Class DailyCredits
 *
 * @package DBTech\Credits\Job
 */
class DailyCredits extends AbstractRebuildJob
{
	protected $data = [
		'cutOff' => null,
	];

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
		// Get yesterday's timestamp
		$dy = new \DateTime('yesterday', new \DateTimeZone(\XF::options()->guestTimeZone));
		$data['cutOff'] = $dy->getTimestamp();

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
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
					AND last_activity > ?
					AND user_state = \'valid\'
					AND is_banned = 0
				ORDER BY user_id
			',
			$batch
		), [$start, $this->data['cutOff']]);
	}

	/**
	 * @param $id
	 *
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	protected function rebuildById($id)
	{
		/** @var \DBTech\Credits\XF\Entity\User $user */
		$user = Helper::find(\XF\Entity\User::class, $id);
		if (!$user)
		{
			return;
		}

		$options = \XF::options();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$daily = $eventTriggerRepo->getHandler('daily');
		$interest = $eventTriggerRepo->getHandler('interest');
		$taxation = $eventTriggerRepo->getHandler('taxation');
		$paycheck = $eventTriggerRepo->getHandler('paycheck');

		$interestInterval = ($options->dbtech_credits_eventtrigger_interest_interval * 86400);
		$taxationInterval = ($options->dbtech_credits_eventtrigger_taxation_interval * 86400);
		$paycheckInterval = ($options->dbtech_credits_eventtrigger_paycheck_interval * 86400);

		if (
			$daily->isActive()
			&& $this->data['cutOff'] > $user->dbtech_credits_lastdaily
		) {
			foreach ($this->currencies as $currencyId => $currency)
			{
				$daily->apply($id, [
					'timestamp'   => $this->data['cutOff'],
					'currency_id' => $currencyId
				], $user);
			}
		}

		if (
			$interest->isActive()
			&& $user->dbtech_credits_lastinterest < (\XF::$time - $interestInterval)
		) {
			if (!$user->dbtech_credits_lastinterest)
			{
				foreach ($this->currencies as $currencyId => $currency)
				{
					$interest->apply($id, [
						'multiplier'  => $user->{$currency['column']},
						'timestamp'   => $this->data['cutOff'],
						'currency_id' => $currencyId
					], $user);
				}
			}
			else
			{
				$timeStamp = $user->dbtech_credits_lastinterest + $interestInterval;
				while ($timeStamp <= $this->data['cutOff'])
				{
					foreach ($this->currencies as $currencyId => $currency)
					{
						$interest->apply($id, [
							'multiplier'  => $user->{$currency['column']},
							'timestamp'   => $timeStamp,
							'currency_id' => $currencyId
						], $user);
					}

					$timeStamp += $interestInterval;
				}
			}
		}

		if (
			$taxation->isActive()
			&& $user->dbtech_credits_lasttaxation < (\XF::$time - $taxationInterval)
		) {
			if (!$user->dbtech_credits_lasttaxation)
			{
				foreach ($this->currencies as $currencyId => $currency)
				{
					$taxation->apply($id, [
						'multiplier'  => (-1 * $user->{$currency['column']}),
						'timestamp'   => $this->data['cutOff'],
						'currency_id' => $currencyId
					], $user);
				}
			}
			else
			{
				$timeStamp = $user->dbtech_credits_lasttaxation + $taxationInterval;
				while ($timeStamp <= $this->data['cutOff'])
				{
					foreach ($this->currencies as $currencyId => $currency)
					{
						$taxation->apply($id, [
							'multiplier'  => (-1 * $user->{$currency['column']}),
							'timestamp'   => $timeStamp,
							'currency_id' => $currencyId
						], $user);
					}

					$timeStamp += $taxationInterval;
				}
			}
		}

		if (
			$paycheck->isActive()
			&& $user->dbtech_credits_lastpaycheck < (\XF::$time - $paycheckInterval)
		) {
			if (!$user->dbtech_credits_lastpaycheck)
			{
				foreach ($this->currencies as $currencyId => $currency)
				{
					$paycheck->apply($id, [
						'timestamp'   => $this->data['cutOff'],
						'currency_id' => $currencyId
					], $user);
				}
			}
			else
			{
				$timeStamp = $user->dbtech_credits_lastpaycheck + $paycheckInterval;
				while ($timeStamp <= $this->data['cutOff'])
				{
					foreach ($this->currencies as $currencyId => $currency)
					{
						$paycheck->apply($id, [
							'timestamp'   => $timeStamp,
							'currency_id' => $currencyId
						], $user);
					}

					$timeStamp += $paycheckInterval;
				}
			}
		}
	}

	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_credits');
	}
}