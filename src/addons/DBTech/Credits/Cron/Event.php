<?php

namespace DBTech\Credits\Cron;

use DBTech\Credits\Helper;

/**
 * Class Event
 *
 * @package DBTech\Credits\Cron
 */
class Event
{
	/**
	 * @throws \Exception
	 */
	public static function birthday()
	{
		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$eventTriggerRepo->cronBirthday();
	}

	/**
	 * @throws \Exception
	 */
	public static function expiry()
	{
		\XF::app()->jobManager()->enqueueUnique(
			'dbtechCreditsExpiry',
			'DBTech\Credits:Expiry',
			[],
			false
		);
	}

	/**
	 * @throws \Exception
	 */
	public static function dailyCredits()
	{
		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$daily = $eventTriggerRepo->getHandler('daily');
		$interest = $eventTriggerRepo->getHandler('interest');
		$taxation = $eventTriggerRepo->getHandler('taxation');
		$paycheck = $eventTriggerRepo->getHandler('paycheck');

		if ($daily->isActive() || $interest->isActive() || $taxation->isActive() || $paycheck->isActive())
		{
			\XF::app()->jobManager()->enqueueUnique(
				'dbtechCreditsDaily',
				'DBTech\Credits:DailyCredits',
				[],
				false
			);
		}
	}
}