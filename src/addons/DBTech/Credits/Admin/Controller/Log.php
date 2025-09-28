<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Helper;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Log
 * @package DBTech\Credits\Admin\Controller
 */
class Log extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechCredits');
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		return $this->view(
			\DBTech\Credits\Admin\View\Log::class,
			'dbtech_credits_logs'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionTransaction(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if ($params->transaction_id)
		{
			$entry = $this->assertTransactionLogExists($params->transaction_id, [
				'Event',
				'Currency',
				'TargetUser',
				'SourceUser'
			], 'requested_log_entry_not_found');

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$eventTrigger = $eventTriggerRepo->getHandler($entry->event_trigger_id);

			$viewParams = [
				'entry' => $entry,
				'eventTrigger' => $eventTrigger,
			];
			return $this->view(
				\DBTech\Credits\Admin\View\Log\Transaction\View::class,
				'dbtech_credits_log_transaction_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = $this->options()->dbtech_credits_transactions;

		/** @var \DBTech\Credits\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher('DBTech\Credits:TransactionLog', $criteria);

		if (empty($criteria))
		{
			$searcher->setCriteria($searcher->getFormDefaults());
		}

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,

			'criteria' => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order' => $order,
			'direction' => $direction

		];
		return $this->view(
			\DBTech\Credits\Admin\View\Log\Transaction\Listing::class,
			'dbtech_credits_log_transaction_list',
			$viewParams
		);
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionTransactionSearch(): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = $this->getTransactionLogSearcherParams();

		return $this->view(
			\DBTech\Credits\Admin\View\Log\Transaction\Search::class,
			'dbtech_credits_log_transaction_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 * @return array
	 */
	protected function getTransactionLogSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Credits\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher('DBTech\Credits:TransactionLog');

		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions()
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Credits\Entity\Transaction
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertTransactionLogExists(?int $id, $with = null, ?string $phraseKey = null): \DBTech\Credits\Entity\Transaction
	{
		return $this->assertRecordExists('DBTech\Credits:Transaction', $id, $with, $phraseKey);
	}
}