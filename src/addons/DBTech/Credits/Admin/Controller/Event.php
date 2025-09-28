<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Helper;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class Event
 * @package DBTech\Credits\Admin\Controller
 */
class Event extends AbstractController
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
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$currencies = Helper::repository(\DBTech\Credits\Repository\Currency::class)
			->getCurrencyTitlePairs()
		;
		if (!count($currencies))
		{
			throw $this->exception($this->error(\XF::phrase('dbtech_credits_please_create_at_least_one_currency_before_continuing')));
		}

		$currencyId = $this->filter('currency_id', 'uint');
		if ($currencyId)
		{
			$currencyId = isset($currencies[$currencyId]) ? $currencyId : 0;
		}

		if (!$currencyId)
		{
			$currencyIds = array_keys($currencies);
			$currencyId = array_shift($currencyIds);
		}

		$events = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->findEventsForList()
			->where('currency_id', $currencyId)
			->fetch()
		;

		$viewParams = [
			'events' => $events,
			'currency' => $currencyId,
			'currencies' => $currencies
		];
		return $this->view(
			\DBTech\Credits\Admin\View\Event\Listing::class,
			'dbtech_credits_event_list',
			$viewParams
		);
	}

	/**
	 * @param \DBTech\Credits\Entity\Event $event
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	protected function eventAddEdit(\DBTech\Credits\Entity\Event $event): \XF\Mvc\Reply\AbstractReply
	{
		$nodeRepo = Helper::repository(\XF\Repository\Node::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

		$viewParams = [
			'event' => $event,
			'nodeTree' => $nodeTree
		];
		return $this->view(
			\DBTech\Credits\Admin\View\Event\Edit::class,
			'dbtech_credits_event_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\Entity\Event $event */
		$event = $this->assertEventExists($params->event_id);
		return $this->eventAddEdit($event);
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \Exception
	 */
	public function actionAdd(): \XF\Mvc\Reply\AbstractReply
	{
		$eventTriggerId = $this->filter('event_trigger_id', 'str');

		if ($eventTriggerId)
		{
			$eventTrigger = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
				->getHandler($eventTriggerId)
			;
			if ($eventTrigger)
			{
				$event = Helper::createEntity(\DBTech\Credits\Entity\Event::class);
				$event->event_trigger_id = $eventTriggerId;

				return $this->eventAddEdit($event);
			}
		}

		$viewParams = [
			'eventTriggers' => Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
				->getEventTriggerTitlePairs(true)
		];

		return $this->view(
			\DBTech\Credits\Admin\View\Event\AddChooser::class,
			'dbtech_credits_event_add_chooser',
			$viewParams
		);
	}

	/**
	 * @param \DBTech\Credits\Entity\Event $event
	 *
	 * @return FormAction
	 * @throws \Exception
	 */
	protected function eventSaveProcess(\DBTech\Credits\Entity\Event $event): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'active' => 'bool',
			'currency_id' => 'uint',
			'event_trigger_id' => 'str',

			'charge' => 'bool',
			'moderate' => 'bool',
			'main_add' => 'float',
			'main_sub' => 'float',
			'mult_add' => 'float',
			'mult_sub' => 'float',
			'frequency' => 'uint',
			'maxtime' => 'uint',
			'applymax' => 'uint',
			'applymax_peruser' => 'bool',
			'upperrand' => 'float',
			'multmin' => 'float',
			'multmax' => 'float',
			'minaction' => 'uint',
			'owner' => 'uint',
			'curtarget' => 'uint',
			'alert' => 'bool',
			'display' => 'bool',

			'settings' => 'array'
		]);

		$usableUserGroups = $this->filter('usable_user_group', 'str');
		if ($usableUserGroups == 'all')
		{
			$input['user_group_ids'] = [-1];
		}
		else
		{
			$input['user_group_ids'] = $this->filter('usable_user_group_ids', 'array-uint');
		}

		$usableForums = $this->filter('node_ids', 'array-int');
		if (in_array(-1, $usableForums) || empty($usableForums))
		{
			$input['node_ids'] = [-1];
		}
		else
		{
			$input['node_ids'] = $usableForums;
		}

		$eventTrigger = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler($input['event_trigger_id'])
		;
		$input['settings'] = $eventTrigger->filterOptions($input['settings']);

		$form->basicEntitySave($event, $input);

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertPostOnly();

		if ($params->event_id)
		{
			/** @var \DBTech\Credits\Entity\Event $event */
			$event = $this->assertEventExists($params->event_id);
		}
		else
		{
			$event = Helper::createEntity(\DBTech\Credits\Entity\Event::class);
		}

		$this->eventSaveProcess($event)->run();

		return $this->redirect($this->buildLink('dbtech-credits/events') . $this->buildLinkHash($event->event_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionDelete(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$event = $this->assertEventExists($params['event_id']);

		$transactions = Helper::finder(\DBTech\Credits\Finder\Transaction::class)
			->where('event_id', $event->event_id)
		;

		/** @var \XF\ControllerPlugin\Delete $plugin */
		$plugin = $this->plugin('XF:Delete');
		return $plugin->actionDelete(
			$event,
			$this->buildLink('dbtech-credits/events/delete', $event),
			$this->buildLink('dbtech-credits/events/edit', $event),
			$this->buildLink('dbtech-credits/events'),
			$event->title,
			'dbtech_credits_event_delete',
			['numTransactions' => $transactions->total()]
		);
	}

	/**
	 * @return \XF\Mvc\Reply\AbstractReply
	 */
	public function actionToggle(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('DBTech\Credits:Event');
	}

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Credits\Entity\Event
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertEventExists(?int $id, $with = null, ?string $phraseKey = null): \DBTech\Credits\Entity\Event
	{
		return $this->assertRecordExists('DBTech\Credits:Event', $id, $with, $phraseKey);
	}

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Credits\Entity\Currency
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCurrencyExists(?int $id, $with = null, ?string $phraseKey = null): \DBTech\Credits\Entity\Currency
	{
		return $this->assertRecordExists('DBTech\Credits:Currency', $id, $with, $phraseKey);
	}
}