<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Helper;

class Thread extends XFCP_Thread
{
	/**
	 * @param \DBTech\Credits\XF\Entity\Thread $thread
	 *
	 * @return \XF\Service\Thread\Editor
	 * @throws \Exception
	 */
	protected function setupThreadEdit(\XF\Entity\Thread $thread)
	{
		/** @var \DBTech\Credits\XF\Service\Thread\Editor $editor */
		$editor = parent::setupThreadEdit($thread);

		$cost = $this->filter('dbtech_credits_access_cost', 'float');
		$currencyId = $this->filter('dbtech_credits_access_currency_id', 'uint');
		if ($cost && $currencyId)
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$currencyRepo = Helper::repository(\DBTech\Credits\Repository\Currency::class);

			$events = $eventTriggerRepo->getEventsForEventTrigger(
				'content_access',
				['node_id' => $thread->node_id]
			);
			$currencies = $currencyRepo->getCurrenciesFromEvents($events);

			if ($currencies->count() && $currencies->offsetExists($currencyId))
			{
				$editor->setDbtechCreditsAccessCost(
					$cost,
					$currencyId
				);
			}
		}
		else
		{
			$editor->setDbtechCreditsAccessCost(
				0.00,
				0
			);
		}

		return $editor;
	}

	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'ContentAccessCurrency';
		if ($visitor->user_id)
		{
			$extraWith[] = 'ContentAccessPurchases|' . $visitor->user_id;
		}

		/** @var \DBTech\Credits\XF\Entity\Thread $thread */
		$thread = parent::assertViewableThread($threadId, $extraWith);

		if ($thread->dbtech_credits_access_cost > 0.00
			&& $thread->dbtech_credits_access_currency_id
			&& $thread->user_id !== $visitor->user_id
			&& !$visitor->is_staff
		) {
			// This thread requires payment to access, thread owner and staff can always access it
			if (!$visitor->user_id)
			{
				$this->assertRegistrationRequired();
			}
			elseif (!$thread->ContentAccessPurchases[$visitor->user_id])
			{
				$viewParams = [
					'thread' => $thread,
					'forum' => $thread->Forum,
					'currency' => $thread->ContentAccessCurrency
				];
				$view = $this->view(
					\DBTech\Credits\Pub\View\ContentAccess\Thread::class,
					'dbtech_credits_content_access_thread',
					$viewParams
				);
				throw $this->exception($view);
			}
		}
		return $thread;
	}
}