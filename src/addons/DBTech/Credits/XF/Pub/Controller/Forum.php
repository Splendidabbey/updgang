<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Helper;

class Forum extends XFCP_Forum
{
	/**
	 * @param \XF\Entity\Forum $forum
	 *
	 * @return \XF\Service\Thread\Creator
	 * @throws \Exception
	 */
	protected function setupThreadCreate(\XF\Entity\Forum $forum)
	{
		/** @var \DBTech\Credits\XF\Service\Thread\Creator $creator */
		$creator = parent::setupThreadCreate($forum);

		$cost = $this->filter('dbtech_credits_access_cost', 'float');
		$currencyId = $this->filter('dbtech_credits_access_currency_id', 'uint');
		if ($cost && $currencyId)
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$currencyRepo = Helper::repository(\DBTech\Credits\Repository\Currency::class);

			$events = $eventTriggerRepo->getEventsForEventTrigger(
				'content_access',
				['node_id' => $forum->node_id]
			);
			$currencies = $currencyRepo->getCurrenciesFromEvents($events);

			if ($currencies->count() && $currencies->offsetExists($currencyId))
			{
				$creator->setDbtechCreditsAccessCost(
					$cost,
					$currencyId
				);
			}
		}
		else
		{
			$creator->setDbtechCreditsAccessCost(
				0.00,
				0
			);
		}

		return $creator;
	}
}