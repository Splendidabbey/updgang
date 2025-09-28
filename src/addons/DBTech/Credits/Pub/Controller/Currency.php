<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\Entity\Event;
use DBTech\Credits\Helper;
use XF\Entity\LinkableInterface;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Currency
 *
 * @package DBTech\Credits\Pub\Controller
 */
class Currency extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechCredits())
		{
			throw $this->exception($this->noPermission());
		}

		if ($action == 'BuyContent')
		{
			$this->assertRegistrationRequired();
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$events = null;
		$transferCurrencies = [];

		if ($visitor->user_id)
		{
			/** @var \DBTech\Credits\Finder\Event $eventFinder */
			$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
				->where('currency_id', $currency->currency_id)
				->where('event_trigger_id', [
					'donate', 'adjust', 'purchase', 'redeem', 'transfer'
				])
			;

			/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
			$events = $eventFinder
				->fetch()
				->filter(function (Event $event) use ($currency, $visitor): bool
				{
					if (!$event->isActive())
					{
						return false;
					}

					if (!$event->getEventTriggerHandler()->isActive())
					{
						return false;
					}

					switch ($event->event_trigger_id)
					{
						case 'adjust':
							if (!$visitor->canAdjustDbtechCreditsCurrencies())
							{
								return false;
							}
							break;

						case 'transfer':
							if (!$currency->outbound)
							{
								return false;
							}

							if (!$event->Transfers->count())
							{
								return false;
							}
							break;
					}

					return true;
				})
			;

			foreach ($events as $event)
			{
				if ($event->event_trigger_id == 'transfer')
				{
					foreach ($event->Transfers as $inboundTransfer)
					{
						$transferCurrencies[$inboundTransfer->currency_id] = $inboundTransfer->Currency;
					}
				}
			}
		}

		$user = null;

		$userId = $this->filter('user_id', 'uint');
		if ($userId)
		{
			$user = $this->assertUserExists($userId);
		}

		$paymentRepo = Helper::repository(\XF\Repository\Payment::class);
		/** @var \XF\Mvc\Entity\AbstractCollection|\XF\Entity\PaymentProfile[] $profiles */
		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$profileThirdParties = [];
		foreach ($profiles as $profileId => $profile)
		{
			$profileId = (int)$profileId;

			if (!$profile->active)
			{
				unset($profiles[$profileId]);
				continue;
			}

			if (\XF::$versionId >= 2021270)
			{
				$profileThirdParties = array_merge(
					$profileThirdParties,
					$profile->Provider->getCookieThirdParties()
				);
			}
		}

		if (\XF::$versionId >= 2021270)
		{
			$this->assertCookieConsent([], array_unique($profileThirdParties));
		}

		$viewParams = [
			'currency' => $currency,
			'eventTriggers' => $events ? $events->pluckNamed('event_trigger_id', 'event_trigger_id') : [],
			'events' => $events,
			'transferCurrencies' => $transferCurrencies,
			'user' => $user,
			'profiles' => $profiles,
			'tab' => $this->filter('tab', 'str')
		];
		return $this->view(
			\DBTech\Credits\Pub\View\Currency\Index::class,
			'dbtech_credits_currency',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPurchaseCompleted(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\Entity\Currency $currency */
		$this->assertCurrencyExists($params->currency_id);

		return $this->message(\XF::phrase('dbtech_credits_thanks_for_your_purchase'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionDonate(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'username' => 'str',
			'amount' => 'unum',
			'message' => 'str',
		]);

		if ($visitor->getDbtechCreditsCurrency($currency) < $input['amount'])
		{
			return $this->error(\XF::phrase('dbtech_credits_currency_donate_x_max_y', [
				'attempted' => $currency->prefix . $currency->getFormattedValue($input['amount']) . $currency->suffix,
				'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix
			]));
		}

		/** @var \DBTech\Credits\EventTrigger\Donate $donateEvent */
		$donateEvent = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('donate')
		;
		if (!$donateEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		$userRepo = Helper::repository(\XF\Repository\User::class);
		$recipient = $userRepo->getUserByNameOrEmail($input['username']);

		if (!$recipient || $recipient->user_id == $visitor->user_id)
		{
			// Bad amount
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var \DBTech\Credits\Finder\Event $eventFinder */
		$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'donate')
		;

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		// No naughty words in the message, thanks
		$message = \XF::app()->stringFormatter()->censorText($input['message']);

		// First test remove credits from source
		$sourceEvents = $donateEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => (-1 * $input['amount']),
			'message' => $message,
			'source_user_id' => $recipient->user_id,
		], $visitor);

		// Then test apply credits to recipient
		$targetEvents = $donateEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => $input['amount'],
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		if (!\count($sourceEvents) || !\count($targetEvents))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_cannot_donate_to_x', [
				'name' => $recipient->username
			]));
		}

		// Then properly remove credits from source
		$donateEvent->apply($recipient->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => (-1 * $input['amount']),
			'message' => $message,
			'source_user_id' => $recipient->user_id,
		], $visitor);

		// Then properly apply credits to recipient
		$donateEvent->apply($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => $input['amount'],
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_donation_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionAdjust(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'username' => 'str',
			'amount' => 'unum',
			'message' => 'str',
			'negate' => 'bool'
		]);

		/** @var \DBTech\Credits\EventTrigger\Adjust $adjustEvent */
		$adjustEvent = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('adjust')
		;
		if (!$adjustEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		// Make sure this is set
		$currency->verifyAdjustEvent();

		$userRepo = Helper::repository(\XF\Repository\User::class);
		$recipient = $userRepo->getUserByNameOrEmail($input['username']);

		if (!$recipient)
		{
			// Bad amount
			return $this->notFound(\XF::phrase('requested_user_not_found'));
		}

		/** @var \DBTech\Credits\Finder\Event $eventFinder */
		$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'adjust')
		;

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		// No naughty words in the message, thanks
		$message = \XF::app()->stringFormatter()->censorText($input['message']);

		// Shorthand
		$multiplier = $input['negate'] ? (-1 * $input['amount']) : $input['amount'];

		// First test add or remove credits
		$adjustEvent->testApply([
			'currency_id' => $currency->currency_id,
			'multiplier' => $multiplier,
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// Then properly add or remove credits
		$adjustEvent->apply($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' => $multiplier,
			'message' => $message,
			'source_user_id' => $visitor->user_id,
		], $recipient);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_adjust_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionRedeem(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'code' => 'str',
		]);

		/** @var \DBTech\Credits\EventTrigger\Adjust $redeemEvent */
		$redeemEvent = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('redeem')
		;
		if (!$redeemEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		// Make sure this is set
		$currency->verifyAdjustEvent();

		/** @var \DBTech\Credits\Finder\Event $eventFinder */
		$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'redeem')
		;

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$message = \XF::phrase('dbtech_credits_currency_x_used_code_y', [
			'user' => $visitor->username,
			'code' => $input['code']
		]);

		/** @var \DBTech\Credits\Entity\Transaction[] $pendingTransactions */
		$pendingTransactions = $redeemEvent->testApply([
			'currency_id' => $currency->currency_id,
			'owner_id' => $visitor->user_id,
			'message' => $message,
			'code' => $input['code']
		], $visitor);

		if (!count($pendingTransactions))
		{
			// Bad amount
			return $this->notFound(\XF::phrase('dbtech_credits_redemption_code_invalid'));
		}

		// Then properly add or remove credits
		$redeemEvent->apply($input['code'], [
			'currency_id' => $currency->currency_id,
			'owner_id' => $visitor->user_id,
			'message' => $message,
			'code' => $input['code']
		], $visitor);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_redeem_successful'));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionTransfer(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertPostOnly();

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'to_currency_id' => 'uint',
			'amount' => 'unum'
		]);

		if ($visitor->getDbtechCreditsCurrency($currency) < $input['amount'])
		{
			return $this->error(\XF::phrase('dbtech_credits_cancel_price_transfer', [
				'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix,
				'currency' => $currency->title
			]));
		}

		/** @var \DBTech\Credits\Entity\Currency $toCurrency */
		$toCurrency = $this->assertCurrencyExists($input['to_currency_id'], [], 'dbtech_credits_invalid_currency');

		/** @var \DBTech\Credits\EventTrigger\Transfer $transferEvent */
		$transferEvent = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('transfer')
		;
		if (!$transferEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		/** @var \DBTech\Credits\Finder\Event $eventFinder */
		$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('currency_id', $currency->currency_id)
			->where('event_trigger_id', 'transfer')
		;

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $events */
		$events = $eventFinder
			->fetch()
			->filter(function (Event $event) use ($currency, $visitor): bool
			{
				if (!$event->isActive())
				{
					return false;
				}

				return true;
			})
		;

		if (!$events->count())
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$transferCurrencies = [];
		foreach ($events as $event)
		{
			if ($event->event_trigger_id == 'transfer')
			{
				foreach ($event->Transfers as $inboundTransfer)
				{
					$transferCurrencies[$inboundTransfer->currency_id] = $inboundTransfer->Currency;
				}
			}
		}

		if (!isset($transferCurrencies[$toCurrency->currency_id]))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$message = \XF::phrase('dbtech_credits_transfer_x_from_y_to_z', [
			'amount' => $currency->prefix . $currency->getValueFromUser($visitor) . $currency->suffix,
			'currency' => $currency->title,
			'new_currency' => $toCurrency->title
		]);

		// Test if we have enough to remove
		$transferEvent->testUndo([
			'currency_id' => $currency->currency_id,
			'multiplier' 	=> (-1 * $input['amount']),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// Then remove from our old currency
		$transferEvent->undo($visitor->user_id, [
			'currency_id' => $currency->currency_id,
			'multiplier' 	=> (-1 * $input['amount']),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// Then apply the amount to the new currency
		$transferEvent->apply($visitor->user_id, [
			'currency_id' => $toCurrency->currency_id,
			'multiplier' 	=> ($input['amount'] * ($currency->value / $toCurrency->value)),
			'message'  		=> $message,
			'sourceuserid' 	=> $visitor->user_id,
		], $visitor);

		// And we're done
		return $this->redirect($this->buildLink('dbtech-credits/currency', $currency), \XF::phrase('dbtech_credits_transfer_successful'));
	}


	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionGiftPurchase(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Credits\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);

		$input = $this->filter([
			'event_id' => 'uint'
		]);

		/** @var \DBTech\Credits\EventTrigger\Purchase $purchaseEvent */
		$purchaseEvent = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('purchase')
		;
		if (!$purchaseEvent->isActive())
		{
			return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
		}

		/** @var \DBTech\Credits\Finder\Event $eventFinder */
		$eventFinder = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->where('event_id', $input['event_id'])
		;

		/** @var \DBTech\Credits\Entity\Event $event */
		$event = $eventFinder->fetchOne();

		if (!$event)
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_invalid_event'));
		}

		$profileIds = $event->getSetting('payment_profile_ids');
		$paymentRepo = Helper::repository(\XF\Repository\Payment::class);

		/** @var \XF\Mvc\Entity\AbstractCollection|\XF\Entity\PaymentProfile[] $profiles */
		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();

		$profileThirdParties = [];
		foreach ($profiles as $profileId => $profile)
		{
			$profileId = (int)$profileId;

			$profileUsed = \in_array($profileId, $profileIds);
			if (!$profile->active || !$profileUsed)
			{
				unset($profiles[$profileId]);
				continue;
			}

			if (\XF::$versionId >= 2021270)
			{
				$profileThirdParties = array_merge(
					$profileThirdParties,
					$profile->Provider->getCookieThirdParties()
				);
			}
		}

		if (\XF::$versionId >= 2021270)
		{
			$this->assertCookieConsent([], array_unique($profileThirdParties));
		}

		$viewParams = [
			'currency' => $currency,
			'event' => $event,
			'profiles' => $profiles
		];

		return $this->view(
			\DBTech\Credits\Pub\View\Currency\GiftPurchase::class,
			'dbtech_credits_currency_gift',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\PrintableException
	 */
	public function actionBuyContent(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$input = $this->filter([
			'content_type' => 'str',
			'content_id' => 'uint',
			'content_hash' => 'str',
		]);

		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var \DBTech\Credits\Entity\Charge $charge */
		$charge = Helper::finder(\DBTech\Credits\Finder\Charge::class)
			->where('content_type', $input['content_type'])
			->where('content_id', $input['content_id'])
			->where('content_hash', $input['content_hash'])
			->fetchOne()
		;
		if (!$charge)
		{
			// Bad hash
			return $this->error(\XF::phrase('dbtech_credits_invalid_hash'));
		}

		if ($charge->Purchases->offsetExists($visitor->user_id))
		{
			// Bad currency
			return $this->error(\XF::phrase('dbtech_credits_already_owned'));
		}

		if ($this->isPost())
		{
			$contentEvent = $charge->getHandler();
			$content = $charge->Content;

			$extraParams = [
				'node_id' => $charge->content_type == 'post' ? $content->Thread->node_id : 0,
				'multiplier' => (-1 * $charge->cost),
				'owner_id' => $content->offsetExists('user_id') ? $content->user_id : 0,
				'currency_id' => $charge->Currency->currency_id,
				'content_type' => $charge->content_type,
				'content_id' => $charge->content_id,
				'alwaysCheck' => true
			];

			/** @var \DBTech\Credits\Entity\Transaction[] $pendingTransactions */
			$pendingTransactions = $contentEvent->testUndo($extraParams, $visitor);

			if (!count($pendingTransactions))
			{
				return $this->error(\XF::phrase('dbtech_credits_content_purchase_events_invalid'));
			}

			// Charge the current user
			$contentEvent->undo($charge->content_id, $extraParams, $visitor);

			if (!empty($content->User))
			{
				// Add this
				$extraParams['multiplier'] = $charge->cost;
				$extraParams['source_user_id'] = $visitor->user_id;

				// Apply the event to the post owner, in case ownership settings are configured
				$contentEvent->apply($charge->content_id, $extraParams, $content->User);
			}

			try
			{
				$chargePurchase = Helper::createEntity(\DBTech\Credits\Entity\ChargePurchase::class);
				$chargePurchase->content_type = $charge->content_type;
				$chargePurchase->content_id = $charge->content_id;
				$chargePurchase->content_hash = $charge->content_hash;
				$chargePurchase->user_id = $visitor->user_id;
				$chargePurchase->save();
			}
			/** @noinspection PhpRedundantCatchClauseInspection */
			catch (\XF\Db\DuplicateKeyException $e)
			{
			}

			$redirect = $this->getDynamicRedirect();
			if ($content instanceof LinkableInterface)
			{
				// Redirect back to the content if we can
				$redirect = $content->getContentUrl();
			}

			// And we're done
			return $this->redirect($redirect, \XF::phrase('dbtech_credits_unlock_successful'));
		}

		$viewParams = [
			'currency' => $charge->Currency,
			'charge' => $charge,
		];

		// Output
		return $this->view(
			\DBTech\Credits\Pub\View\Currency\BuyContent::class,
			'dbtech_credits_currency_unlock',
			$viewParams
		);
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

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \XF\Entity\User
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertUserExists(?int $id, $with = null, ?string $phraseKey = null): \XF\Entity\User
	{
		return $this->assertRecordExists('XF:User', $id, $with, $phraseKey ?: 'requested_user_not_found');
	}
}