<?php

namespace DBTech\Credits\ControllerPlugin;

use DBTech\Credits\Helper;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\ArrayCollection;

/**
 * Class Overview
 *
 * @package DBTech\Credits\ControllerPlugin
 */
class Overview extends AbstractPlugin
{
	/**
	 * @return array
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function getCoreListData(): array
	{
		$transactionRepo = Helper::repository(\DBTech\Credits\Repository\Transaction::class);

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		$transactionFinder = $transactionRepo->findTransactionsForOverviewList([
			'allowOwnPending' => $allowOwnPending
		]);

		$filters = $this->getTransactionFilterInput();
		$this->applyTransactionFilters($transactionFinder, $filters);

		$totalTransactions = $transactionFinder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->dbtech_credits_transactions;

		$transactionFinder->limitByPage($page, $perPage);
		$transactions = $transactionFinder->fetch()->filterViewable();

		if (!empty($filters['source_id']))
		{
			$sourceFilter = Helper::find(\XF\Entity\User::class, $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['target_id']))
		{
			$targetFilter = Helper::find(\XF\Entity\User::class, $filters['target_id']);
		}
		else
		{
			$targetFilter = null;
		}

		if (!empty($filters['currency_id']))
		{
			$currencyFilter = Helper::find(\DBTech\Credits\Entity\Currency::class, $filters['currency_id']);
		}
		else
		{
			$currencyFilter = null;
		}

		if (!empty($filters['event_id']))
		{
			$eventFilter = Helper::find(\DBTech\Credits\Entity\Event::class, $filters['event_id']);
		}
		else
		{
			$eventFilter = null;
		}

		if (!empty($filters['event_trigger_id']))
		{
			$eventTriggerFilter = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
				->getHandler($filters['event_trigger_id'])
			;
		}
		else
		{
			$eventTriggerFilter = null;
		}

		$this->addContentToResults($transactions);

		return [
			'transactions' => $transactions,
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'targetFilter' => $targetFilter,
			'currencyFilter' => $currencyFilter,
			'eventFilter' => $eventFilter,
			'eventTriggerFilter' => $eventTriggerFilter,

			'total' => $totalTransactions,
			'page' => $page,
			'perPage' => $perPage
		];
	}

	/**
	 * @param \XF\Mvc\Entity\ArrayCollection $transactions
	 */
	public function addContentToResults(ArrayCollection $transactions)
	{
		/** @var \DBTech\Credits\Entity\Transaction[]|ArrayCollection $transactions */

		$byType = [];
		foreach ($transactions AS $result)
		{
			if (empty($result->content_type) || empty($result->content_id))
			{
				continue;
			}

			$byType[$result->content_type][$result->transaction_id] = $result->content_id;
		}

		foreach ($byType AS $type => $ids)
		{
			try
			{
				$entities = $this->app->findByContentType($type, $ids);

				foreach ($ids as $transactionId => $contentId)
				{
					if (!$transactions->offsetExists($transactionId)
						|| !$entities->offsetExists($contentId)
					) {
						continue;
					}

					/** @var \DBTech\Credits\Entity\Transaction $transaction */
					$transaction = $transactions->offsetGet($transactionId);

					$transaction->setContent($entities->offsetGet($contentId));
				}
			}
			catch (\LogicException $e)
			{
			}
		}
	}

	/**
	 * @param \DBTech\Credits\Finder\Transaction $transactionFinder
	 * @param array $filters
	 */
	public function applyTransactionFilters(\DBTech\Credits\Finder\Transaction $transactionFinder, array $filters)
	{
		if (!empty($filters['source_id']))
		{
			$transactionFinder->where('source_user_id', (int)$filters['source_id']);
		}

		if (!empty($filters['target_id']))
		{
			$transactionFinder->where('user_id', (int)$filters['target_id']);
		}

		if (!empty($filters['currency_id']))
		{
			$transactionFinder->where('currency_id', (int)$filters['currency_id']);
		}

		if (!empty($filters['event_id']))
		{
			$transactionFinder->where('event_id', (int)$filters['event_id']);
		}

		if (!empty($filters['event_trigger_id']))
		{
			$transactionFinder->where('event_trigger_id', $filters['event_trigger_id']);
		}

		$sorts = $this->getAvailableTransactionSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$transactionFinder->order($sorts[$filters['order']], $filters['direction']);
		}
	}

	/**
	 * @return array
	 */
	public function getTransactionFilterInput(): array
	{
		$filters = [];

		$input = $this->filter([
			'prefix_id' => 'uint',
			'type' => 'str',
			'source' => 'str',
			'source_id' => 'uint',
			'target' => 'str',
			'target_id' => 'uint',
			'currency_id' => 'uint',
			'event_id' => 'uint',
			'event_trigger_id' => 'str',
			'order' => 'str',
			'direction' => 'str'
		]);

		if ($input['prefix_id'])
		{
			$filters['prefix_id'] = $input['prefix_id'];
		}

		if ($input['type'] && ($input['type'] == 'free' || $input['type'] == 'paid'))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['source_id'])
		{
			$filters['source_id'] = $input['source_id'];
		}
		elseif ($input['source'])
		{
			$user = Helper::findOne(\XF\Entity\User::class, ['username' => $input['source']]);
			if ($user)
			{
				$filters['source_id'] = $user->user_id;
			}
		}

		if ($input['target_id'])
		{
			$filters['target_id'] = $input['target_id'];
		}
		elseif ($input['target'])
		{
			$user = Helper::findOne(\XF\Entity\User::class, ['username' => $input['target']]);
			if ($user)
			{
				$filters['target_id'] = $user->user_id;
			}
		}

		if ($input['currency_id'])
		{
			$filters['currency_id'] = $input['currency_id'];
		}

		if ($input['event_id'])
		{
			$filters['event_id'] = $input['event_id'];
		}

		if ($input['event_trigger_id'])
		{
			$filters['event_trigger_id'] = $input['event_trigger_id'];
		}

		$sorts = $this->getAvailableTransactionSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

//			$defaultOrder = $this->options()->dbtechCreditsListDefaultOrder ?: 'dateline';
			$defaultOrder = 'dateline';
			/** @noinspection PhpConditionAlreadyCheckedInspection */
			$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

			if ($input['order'] != $defaultOrder || $input['direction'] != $defaultDir)
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}

	/**
	 * @return array
	 */
	public function getAvailableTransactionSorts(): array
	{
		// maps [name of sort] => field in/relative to Transaction entity
		return [
			'dateline' => 'dateline',
			'amount' => 'amount',
		];
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): \XF\Mvc\Reply\AbstractReply
	{
		$filters = $this->getTransactionFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink(
				'dbtech-credits',
				null,
				$filters
			));
		}

		if (!empty($filters['source_id']))
		{
			$sourceFilter = Helper::find(\XF\Entity\User::class, $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['target_id']))
		{
			$targetFilter = Helper::find(\XF\Entity\User::class, $filters['target_id']);
		}
		else
		{
			$targetFilter = null;
		}

//		$defaultOrder = $this->options()->dbtechCreditsListDefaultOrder ?: 'dateline';
		$defaultOrder = 'dateline';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}

		$viewParams = [
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'targetFilter' => $targetFilter,
			'currencyFilter' => Helper::repository(\DBTech\Credits\Repository\Currency::class)
				->getCurrencyTitlePairs(true)
			,
			'eventFilter' => Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
				->getEventTitlePairs(true, true)
			,
			'eventTriggerFilter' => Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
				->getEventTriggerTitlePairs(true, true)
			,
		];
		return $this->view(
			\DBTech\Credits\Pub\View\Filters::class,
			'dbtech_credits_filters',
			$viewParams
		);
	}
}