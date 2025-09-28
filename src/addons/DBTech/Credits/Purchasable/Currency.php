<?php

namespace DBTech\Credits\Purchasable;

use DBTech\Credits\Helper;
use XF\Purchasable\Purchase;
use XF\Purchasable\AbstractPurchasable;
use XF\Payment\CallbackState;

/**
 * Class Currency
 *
 * @package DBTech\Credits\Purchasable
 */
class Currency extends AbstractPurchasable
{
	/**
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_credits_currency');
	}

	/**
	 * @param \XF\Http\Request $request
	 * @param \XF\Entity\User $purchaser
	 * @param null $error
	 *
	 * @return bool|Purchase
	 */
	public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
	{
		$profileId = $request->filter('payment_profile_id', 'uint');

		$paymentProfile = Helper::find(\XF\Entity\PaymentProfile::class, $profileId);
		if (!$paymentProfile || !$paymentProfile->active)
		{
			$error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');
			return false;
		}

		$eventId = $request->filter('event_id', 'uint');

		$event = Helper::find(\DBTech\Credits\Entity\Event::class, $eventId);
		if (!$event || !$event->canPurchase())
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return false;
		}

		if (!in_array($profileId, $event->getSetting('payment_profile_ids')))
		{
			$error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
			return false;
		}

		$isGift = $request->filter('is_gift', 'bool');
		if (!$isGift)
		{
			$recipientId = $purchaser->user_id;
		}
		else
		{
			$recipientName = $request->filter('recipient', 'str');
			if (!$recipientName)
			{
				$error = \XF::phrase('requested_user_not_found');
				return false;
			}

			/** @var \XF\Repository\User $userRepo */
			$userRepo = Helper::repository(\XF\Repository\User::class);
			$recipient = $userRepo->getUserByNameOrEmail($recipientName);

			if (!$recipient)
			{
				$error = \XF::phrase('requested_user_not_found');
				return false;
			}

			$recipientId = $recipient->user_id;
		}

		// Back this up
		$event->setOption('purchase_recipient', $recipientId);

		return $this->getPurchaseObject($paymentProfile, $event, $purchaser);
	}

	/**
	 * @param array $extraData
	 *
	 * @return array
	 */
	public function getPurchasableFromExtraData(array $extraData): array
	{
		$output = [
			'link' => '',
			'title' => '',
			'purchasable' => null
		];

		$event = Helper::find(\DBTech\Credits\Entity\Event::class, $extraData['event_id']);
		if ($event)
		{
			// Back this up
			$event->setOption('purchase_recipient', $extraData['recipient']);

			$output['link'] = \XF::app()->router('admin')->buildLink('dbtech-credits/event/edit', $event);
			$output['title'] = $event->title;
			$output['purchasable'] = $event;
		}
		return $output;
	}

	/**
	 * @param array $extraData
	 * @param \XF\Entity\PaymentProfile $paymentProfile
	 * @param \XF\Entity\User $purchaser
	 * @param null $error
	 *
	 * @return bool|Purchase
	 */
	public function getPurchaseFromExtraData(array $extraData, \XF\Entity\PaymentProfile $paymentProfile, \XF\Entity\User $purchaser, &$error = null)
	{
		$purchasable = $this->getPurchasableFromExtraData($extraData);
		if (!$purchasable['purchasable'] || !$purchasable['purchasable']->canPurchase())
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return false;
		}

		/** @var \DBTech\Credits\Entity\Event $event */
		$event = $purchasable['purchasable'];

		if (!in_array($paymentProfile->payment_profile_id, $event->getSetting('payment_profile_ids')))
		{
			$error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
			return false;
		}

		return $this->getPurchaseObject($paymentProfile, $event, $purchaser);
	}

	/**
	 * @param \XF\Entity\PaymentProfile $paymentProfile
	 * @param \DBTech\Credits\Entity\Event $purchasable
	 * @param \XF\Entity\User $purchaser
	 *
	 * @return Purchase
	 */
	public function getPurchaseObject(
		\XF\Entity\PaymentProfile $paymentProfile,
		$purchasable,
		\XF\Entity\User $purchaser
	): Purchase {
		$options = \XF::options();

		$purchase = new Purchase();

		$purchase->title = $purchasable->getTitle() . ' (' . $purchaser->username . ')';
		$purchase->description = $purchasable->getSetting('purchase_description');
		$purchase->cost = $purchasable->getSetting('purchase_cost');
		$purchase->currency = $options->dbtech_credits_eventtrigger_purchase_currency;
		$purchase->recurring = false;
		$purchase->purchaser = $purchaser;
		$purchase->paymentProfile = $paymentProfile;
		$purchase->purchasableTypeId = $this->purchasableTypeId;
		$purchase->purchasableId = $purchasable->event_id;
		$purchase->purchasableTitle = $purchasable->getTitle();
		$purchase->extraData = [
			'event_id' => $purchasable->event_id,
			'recipient' => $purchasable->getOption('purchase_recipient')
		];

		$router = \XF::app()->router('public');

		$purchase->returnUrl = $router->buildLink('canonical:dbtech-credits/currency/purchase-completed', $purchasable->Currency);
		$purchase->cancelUrl = $router->buildLink('canonical:dbtech-credits');

		return $purchase;
	}

	/**
	 * @param CallbackState $state
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function completePurchase(CallbackState $state)
	{
		$purchaseRequest = $state->getPurchaseRequest();
		$eventId = $purchaseRequest->extra_data['event_id'];

		$paymentResult = $state->paymentResult;
		$purchaser = $state->getPurchaser();

		$event = Helper::find(\DBTech\Credits\Entity\Event::class, $eventId);

		if ($event === null)
		{
			// Couldn't find event
			$state->logType = 'error';
			$state->logMessage = 'Invalid event: ' . $eventId;

			return;
		}

		/** @var \DBTech\Credits\XF\Entity\User $recipient */
		$recipient = Helper::find(\XF\Entity\User::class, $purchaseRequest->extra_data['recipient']);

		if ($recipient === null)
		{
			// Couldn't find event
			$state->logType = 'error';
			$state->logMessage = 'Invalid recipient user ID: ' . $purchaseRequest->extra_data['recipient'];

			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		switch ($paymentResult)
		{
			case CallbackState::PAYMENT_RECEIVED:
				$eventTriggerRepo->getHandler('purchase')
					->apply($purchaseRequest->purchase_request_id, [
						'currency_id' => $event->currency_id,
						'multiplier' => $event->getSetting('purchase_amount'),
						'source_user_id' => $purchaser->user_id,
					], $recipient)
				;

				$state->logType = 'payment';
				$state->logMessage = 'Payment received, credits awarded.';
				break;

			case CallbackState::PAYMENT_REINSTATED:
				$eventTriggerRepo->getHandler('purchase')
					->apply($purchaseRequest->purchase_request_id, [
						'currency_id' => $event->currency_id,
						'multiplier' => (-1 * $event->getSetting('purchase_amount')),
						'source_user_id' => $purchaser->user_id,
					], $recipient)
				;

				$state->logType = 'payment';
				$state->logMessage = 'Reversal cancelled, credits re-added.';
				break;
		}
	}

	/**
	 * @param CallbackState $state
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function reversePurchase(CallbackState $state)
	{
		$purchaseRequest = $state->getPurchaseRequest();
		$purchaser = $state->getPurchaser();

		$event = Helper::find(\DBTech\Credits\Entity\Event::class, $purchaseRequest->extra_data['event_id']);

		if ($event === null)
		{
			// Couldn't find event
			$state->logType = 'error';
			$state->logMessage = 'Invalid event: ' . $purchaseRequest->extra_data['event_id'];

			return;
		}

		/** @var \DBTech\Credits\XF\Entity\User $recipient */
		$recipient = Helper::find(\XF\Entity\User::class, $purchaseRequest->extra_data['recipient']);

		if ($recipient === null)
		{
			// Couldn't find event
			$state->logType = 'error';
			$state->logMessage = 'Invalid recipient user ID: ' . $purchaseRequest->extra_data['recipient'];

			return;
		}

		Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('purchase')
			->apply($purchaseRequest->purchase_request_id, [
				'currency_id'    => $event->currency_id,
				'multiplier'     => (-1 * $event->getSetting('purchase_amount')),
				'source_user_id' => $purchaser->user_id,
			], $recipient)
		;

		$state->logType = 'cancel';
		$state->logMessage = 'Payment refunded/reversed, credits removed.';
	}

	/**
	 * @param $profileId
	 *
	 * @return array
	 */
	public function getPurchasablesByProfileId($profileId): array
	{
		$purchasables = [];

		/** @var \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\AbstractCollection $events */
		$events = Helper::finder(\DBTech\Credits\Finder\Event::class)
			->fetch()
		;

		foreach ($events as $event)
		{
			if (!in_array($profileId, $event->getSetting('payment_profile_ids')))
			{
				// Skip this
				continue;
			}

			$purchasables['dbtech_credits_currency' . $event->event_id] = [
				'title' => $event->title . ' #' . $event->event_id,
				'link' => \XF::app()->router('admin')->buildLink('dbtech-credits/event/edit', $event)
			];
		}

		return $purchasables;
	}
}