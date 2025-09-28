<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Helper;
use XF\Job\AbstractRebuildJob;

/**
 * Class BalanceRebuild
 *
 * @package DBTech\Credits\Job
 */
class BalanceRebuild extends AbstractRebuildJob
{
	/** @var \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\AbstractCollection */
	protected $currencies;


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	protected function setupData(array $data): array
	{
		$this->currencies = Helper::finder(\DBTech\Credits\Finder\Currency::class)->fetch();

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
				ORDER BY user_id
			',
			$batch
		), $start);
	}

	/**
	 * @param $id
	 *
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	protected function rebuildById($id)
	{
		$user = Helper::find(\XF\Entity\User::class, $id);

		$visitor = \XF::visitor();

		$repo = Helper::repository(\DBTech\Credits\Repository\Currency::class);

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$adjustHandler = $eventTriggerRepo->getHandler('adjust');

		foreach ($this->currencies as $currency)
		{
			$balance = $repo->getUserBalanceFromTransactionLog($id, $currency->currency_id);

			// Make sure there's an adjust event
			$currency->verifyAdjustEvent();

			if ($user->{$currency->column} < $balance)
			{
				// Adjust event (up)
				$adjustHandler
					->apply($user->user_id, [
						'currency_id'    => $currency->currency_id,
						'multiplier'     => abs($balance - $user->{$currency->column}),
						'message'        => \XF::language()->renderPhrase('dbtech_credits_balance_correction'),
						'source_user_id' => $visitor->user_id,
						'forceVisible'   => true
					], $user)
				;
			}
			elseif ($user->{$currency->column} > $balance)
			{
				// Adjust event (down)
				$adjustHandler
					->apply($user->user_id, [
						'currency_id'    => $currency->currency_id,
						'multiplier'     => (-1 * abs($user->{$currency->column} - $balance)),
						'message'        => \XF::language()->renderPhrase('dbtech_credits_balance_correction'),
						'source_user_id' => $visitor->user_id,
						'forceVisible'   => true
					], $user)
				;
			}
		}
	}

	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('users');
	}
}