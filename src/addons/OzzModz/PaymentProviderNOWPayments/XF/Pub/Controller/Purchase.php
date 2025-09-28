<?php

namespace OzzModz\PaymentProviderNOWPayments\XF\Pub\Controller;

class Purchase extends XFCP_Purchase
{
	public function actionNowPaymentsStatus()
	{
		/** @var \XF\Entity\PurchaseRequest $purchaseRequest */
		$purchaseRequest = $this->em()->findOne('XF:PurchaseRequest', $this->filter(['request_key' => 'str']), 'User');
		if (!$purchaseRequest || $purchaseRequest->provider_id != 'ozzmodz_nowpayments')
		{
			throw $this->exception($this->error(\XF::phrase('invalid_purchase_request')));
		}

		/** @var \XF\Entity\PaymentProfile $paymentProfile */
		$paymentProfile = $this->em()->find('XF:PaymentProfile', $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			throw $this->exception($this->error(\XF::phrase('purchase_request_contains_invalid_payment_profile')));
		}

		$this->assertPurchasableExists($purchaseRequest->purchasable_type_id);

		$providerHandler = $paymentProfile->Provider->handler;

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$providerHandler->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		$paymentId = $this->filter('payment_id', 'uint');

		$payment = $apiHelper->getPayment($paymentId,
			$paymentStatusError
		);
		if (!$payment)
		{
			return $this->error($paymentStatusError ?: \XF::phrase('ozzmodz_nowpayments_no_api_response'));
		}

		$status = $payment['payment_status'] ?? null;
		if ($status == 'finished')
		{
			return $this->redirect($purchaseRequest->extra_data['return_url'] ?? $this->buildLink('index'));
		}

		$statusBlock = false;
		$statusPhrase = \XF::phrase('ozzmodz_nowpayments_no_payment_status');

		if ($status == 'failed' || $status == 'refunded')
		{
			$statusPhrase = \XF::phrase('ozzmodz_nowpayments_payment_failed_please_contact_administrator', ['payment_id' => $paymentId]);
			$statusBlock = 'error';
		}
		elseif ($status == 'expired')
		{
			$statusPhrase = \XF::phrase('ozzmodz_nowpayments_payment_expired');
			$statusBlock = 'error';
		}
		elseif ($status == 'confirming' || $status == 'partially_paid' || $status == 'sending')
		{
			$statusPhrase = \XF::phrase('ozzmodz_nowpayments_payment_is_confirming_please_wait');
			$statusBlock = 'success';
		}
		elseif ($status == 'waiting')
		{
			$statusPhrase = \XF::phrase('ozzmodz_nowpayments_waiting_payment');
		}

		if ($this->filter('as_message', 'bool'))
		{
			$view = $this->view();
			$view->setJsonParams([
				'message' => $statusPhrase,
				'statusBlock' => $statusBlock
			]);

			return $view;
		}

		return $this->error($statusPhrase);
	}

	public function actionNowPaymentsExpirationEstimate()
	{
		/** @var \XF\Entity\PurchaseRequest $purchaseRequest */
		$purchaseRequest = $this->em()->findOne('XF:PurchaseRequest', $this->filter(['request_key' => 'str']), 'User');
		if (!$purchaseRequest || $purchaseRequest->provider_id != 'ozzmodz_nowpayments')
		{
			throw $this->exception($this->error(\XF::phrase('invalid_purchase_request')));
		}

		/** @var \XF\Entity\PaymentProfile $paymentProfile */
		$paymentProfile = $this->em()->find('XF:PaymentProfile', $purchaseRequest->payment_profile_id);
		if (!$paymentProfile)
		{
			throw $this->exception($this->error(\XF::phrase('purchase_request_contains_invalid_payment_profile')));
		}

		$this->assertPurchasableExists($purchaseRequest->purchasable_type_id);

		$providerHandler = $paymentProfile->Provider->handler;

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$providerHandler->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		$paymentId = $this->filter('payment_id', 'uint');

		$estimateResponse = $apiHelper->getAndUpdatePaymentEstimate($paymentId,
			$estimateError
		);
		if (!$estimateResponse)
		{
			return $this->error($estimateError ?: \XF::phrase('ozzmodz_nowpayments_no_api_response'));
		}

		$view = $this->view();
		$view->setJsonParams($estimateResponse);

		return $view;
	}
}