<?php


namespace OzzModz\PaymentProviderNOWPayments\Payment;


use XF;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Controller;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class NOWPayments extends \XF\Payment\AbstractProvider
{
	protected $profileId;

	public function getTitle()
	{
		return 'NOWPayments';
	}

	public function getApiEndpoint()
	{
		if (\XF::config('enableLivePayments'))
		{
			return 'https://api.nowpayments.io';
		}

		return 'https://api-sandbox.nowpayments.io';
	}

	public function verifyConfig(array &$options, &$errors = [])
	{
		return true;
	}

	public function renderCancellationTemplate(PurchaseRequest $purchaseRequest)
	{
		$data = [
			'purchaseRequest' => $purchaseRequest,
		];
		return \XF::app()->templater()->renderTemplate('public:payment_cancel_recurring', $data);
	}

	public function processCancellation(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile)
	{
		$this->profileId = $paymentProfile->payment_profile_id;

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$this->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		$subscriberId = $purchaseRequest->provider_metadata;
		if (!$subscriberId)
		{
			return $controller->error(\XF::phrase('could_not_find_subscriber_id_for_this_purchase_request'));
		}

		$apiHelper->authenticate($paymentProfile->options['email'], $paymentProfile->options['password']);
		$response = $apiHelper->deleteSubscription($subscriberId, $error);
		if (!$response || $error == 'Subscription not found')
		{
			throw $controller->exception($controller->error(\XF::phrase('this_subscription_cannot_be_cancelled_maybe_already_cancelled')));
		}

		return $controller->redirect(
			$controller->getDynamicRedirect(),
			\XF::phrase('ozzmodz_nowpayments_subscription_cancelled_successfully')
		);
	}

	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;
		$this->profileId = $paymentProfile->payment_profile_id;

		/** @see \OzzModz\PaymentProviderNOWPayments\XF\Pub\Controller\Purchase::actionProcess() */
		$extraData = $purchaseRequest->extra_data;
		$extraData['return_url'] = $purchase->returnUrl;

		$purchaseRequest->fastUpdate('extra_data', $extraData);

		if ($purchase->recurring)
		{
			return $this->initiateRecurringPayment($controller, $purchaseRequest, $purchase);
		}
		elseif (!empty($paymentProfile->options['embedded']))
		{
			return $this->initiateEmbeddedPayment($controller, $purchaseRequest, $purchase);
		}
		else
		{
			return $this->initiateStandardPayment($controller, $purchaseRequest, $purchase);
		}
	}

	protected function initiateStandardPayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$viewParams = $this->getInitiatePaymentParams($controller, $purchaseRequest, $purchase);
		return $controller->view('XF:Purchase\NOWPaymentsInitiate', 'ozzmodz_nowpayments_payment_initiate', $viewParams);
	}

	/**
	 * @param Purchase $purchase
	 * @return array
	 */
	protected function getNewPlanParams(Purchase $purchase): array
	{
		return [
			'title' => substr($purchase->title, 0, 50),
			'interval_day' => $this->getIntervalDaysFromPurchaseLength($purchase),
			'amount' => $purchase->cost,
			'currency' => $purchase->currency,
			'ipn_callback_url' => $this->getCallbackUrl(),
			'success_url' => $purchase->returnUrl,
			'cancel_url' => $purchase->cancelUrl,
		];
	}

	/**
	 * @param Purchase $purchase
	 * @return array
	 */
	protected function getOldPlanUpdateParams(Purchase $purchase): array
	{
		return [
			'title' => substr($purchase->title, 0, 50),
			'interval_day' => $this->getIntervalDaysFromPurchaseLength($purchase),
			'amount' => $purchase->cost,
			'currency' => $purchase->currency
		];
	}

	protected function hasOldPlanChanges($plan, Purchase $purchase)
	{
		return $plan['title'] != $purchase->title
			|| $plan['interval_day'] != $this->getIntervalDaysFromPurchaseLength($purchase)
			|| $plan['amount'] != $purchase->cost
			|| $plan['currency'] != $purchase->currency;
	}

	protected function getIntervalDaysFromPurchaseLength(Purchase $purchase)
	{
		switch ($purchase->lengthUnit)
		{
			case 'month':
				return $purchase->lengthAmount * 31;
			case 'year':
				return $purchase->lengthAmount * 365;
		}

		return $purchase->lengthAmount;
	}

	public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
	{
		$this->profileId = $paymentProfile->payment_profile_id;

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$this->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		if ($purchase->recurring)
		{
			$email = $purchase->purchaser->email ?: $controller->filter('email', 'str');

			$emailValidator = $controller->app()->validator('Email');
			$emailValidator->setOption('allow_empty', true);
			$emailValidator->setOption('check_typos', true);
			$email = $emailValidator->coerceValue($email);
			if (!$emailValidator->isValid($email, $errorKey))
			{
				if ($errorKey == 'typo')
				{
					return $controller->error(\XF::phrase('email_address_you_entered_appears_have_typo'));
				}
				else
				{
					return $controller->error(\XF::phrase('please_enter_valid_email'));
				}
			}

			$response = $apiHelper->authenticate($paymentProfile->options['email'], $paymentProfile->options['password']);
			if (!$response)
			{
				throw $controller->exception($controller->error(XF::phrase('ozzmodz_nowpayments_no_api_response')));
			}

			/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Plan $plan */
			$plan = \XF::em()->findOne('OzzModz\PaymentProviderNOWPayments:Plan', [
				'purchasable_type_id' => $purchase->purchasableTypeId,
				'purchasable_id' => $purchase->purchasableId,
			]);
			if (!$plan)
			{
				$response = $apiHelper->createPlan($this->getNewPlanParams($purchase), $newPlanError);
				if (!empty($response['result']['id']))
				{
					$planId = $response['result']['id'];
				}
				else
				{
					throw $controller->exception($controller->error($newPlanError ?: XF::phrase('ozzmodz_nowpayments_no_api_response')));
				}

				/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Plan $plan */
				$plan = \XF::em()->create('OzzModz\PaymentProviderNOWPayments:Plan');
				$plan->plan_id = $planId;
				$plan->purchasable_type_id = $purchase->purchasableTypeId;
				$plan->purchasable_id = $purchase->purchasableId;
			}
			else
			{
				$response = $apiHelper->getPlan($plan->plan_id, $planError);
				if (!$response || $planError)
				{
					throw $controller->exception($controller->error(
						$planError ?? XF::phrase('ozzmodz_nowpayments_no_api_response')
					));
				}

				if (!empty($response['result']['id']))
				{
					$planId = $response['result']['id'];
					if ($this->hasOldPlanChanges($plan, $purchase))
					{
						$response = $apiHelper->updatePlan($planId, $this->getOldPlanUpdateParams($purchase), $updatePlanError);
						if (!$response || $updatePlanError)
						{
							throw $controller->exception($controller->error(
								$updatePlanError ?? XF::phrase('ozzmodz_nowpayments_no_api_response')
							));
						}
					}
				}
			}

			$plan->bulkSet([
				'title' => $purchase->title,
				'interval_day' => $this->getIntervalDaysFromPurchaseLength($purchase),
				'amount' => $purchase->cost,
				'currency' => $purchase->currency
			]);

			try
			{
				$plan->saveIfChanged();
			}
			catch (\XF\Db\DuplicateKeyException $exception)
			{
			}

			if (!$response)
			{
				throw $controller->exception($controller->error(XF::phrase('ozzmodz_nowpayments_no_api_response')));
			}

			$planId = $response['result']['id'] ?? null;
			if (!$planId)
			{
				throw $controller->exception($controller->error(XF::phrase('ozzmodz_nowpayments_no_api_response')));
			}

			/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Subscription $existingSubscription */
			$existingSubscription = \XF::em()->findOne('OzzModz\PaymentProviderNOWPayments:Subscription', [
				'subscription_plan_id' => $planId,
				'email' => $email
			]);
			if ($existingSubscription)
			{
				$apiHelper->deleteSubscription($existingSubscription->subscription_id,  $deleteSubscriptionError);
			}

			$subscriptionResponse = $apiHelper->createSubscription($planId, $email, $subscriptionError);
			if (!$subscriptionResponse || empty($subscriptionResponse['result']) || $subscriptionError)
			{
				throw $controller->exception($controller->error(
					$subscriptionError ?? XF::phrase('ozzmodz_nowpayments_no_subscription_response')
				));
			}

			$subscribe = reset($subscriptionResponse['result']);

			$planId = $subscribe['id'];

			/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Subscription $subscription */
			$subscription = \XF::em()->findOne('OzzModz\PaymentProviderNOWPayments:Subscription', [
				'subscription_plan_id' => $planId,
				'email' => $email
			]);
			if (!$subscription)
			{
				/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Subscription $subscription */
				$subscription = \XF::em()->create('OzzModz\PaymentProviderNOWPayments:Subscription');
				$subscription->email = $email;
				$subscription->subscription_plan_id = $subscribe['subscription_plan_id'];
			}

			$subscription->purchase_request_key = $purchaseRequest->request_key;
			$subscription->subscription_id = $subscribe['id'];

			try
			{
				$subscription->save();
			}
			catch (\XF\Db\DuplicateKeyException $exception)
			{
			}

			$purchaseRequest->fastUpdate('provider_metadata', $subscribe['id']);

			return $controller->message(\XF::phrase('ozzmodz_nowpayments_payment_link_sent_to_your_email_address'));
		}
		else
		{
			$currencies = $controller->app()->simpleCache()->getValue('OzzModz\PaymentProviderNOWPayments', 'currencies');

			$payCurrency = $controller->filter('pay_currency', 'str');
			$paymentId = $controller->filter('payment_id', 'uint');

			if (!$paymentId)
			{
				$params = [
					'pay_currency' => $payCurrency,
				];

				$payment = $apiHelper->createPayment(
					$params + $this->getPaymentParams($purchaseRequest, $purchase, true),
					$controller->request()->getIp(),
					$invoiceError
				);
			}
			else
			{
				$payment = $apiHelper->getPayment($paymentId, $invoiceError);

				$status = $paymentStatusResponse['payment_status'] ?? null;
				if ($status == 'finished')
				{
					return $controller->redirect($purchase->returnUrl ?: $controller->buildLink('index'));
				}
			}

			if (!$payment)
			{
				$error = @json_decode($invoiceError, true);
				if (isset($error['message']))
				{
					if ($error['message'] == 'amountTo is too small')
					{
						return $controller->error(XF::phrase('ozzmodz_nowpayments_amount_is_too_small_please_select_another_currency'));
					}
					else
					{
						return $controller->error($error['message']);
					}
				}
				return $controller->error($invoiceError ?: XF::phrase('ozzmodz_nowpayments_no_api_response'));
			}

			if (isset($payment['redirect_url']))
			{
				return $controller->redirect($payment['redirect_url']);
			}

			$currencyId = array_search($payCurrency, array_column($currencies, 'code'));

			$viewParams = [
				'payment' => $payment,
				'currency' => $currencies[$currencyId] ?? null,
				'purchase' => $purchase,
				'purchaseRequest' => $purchaseRequest,
				'paymentProfile' => $paymentProfile,
				'expirationDate' => isset($payment['expiration_estimate_date']) ? strtotime($payment['expiration_estimate_date']) : null,
				'apiPublicKey' => $paymentProfile->options['public_key'] ?? null,
				'apiUrl' => $this->getApiEndpoint(),
				'returnUrl' => $purchase->returnUrl ?: $controller->buildLink('canonical:index'),
				'sandboxMode' => !\XF::config('enableLivePayments')
			];

			return $controller->view('XF:Purchase\NOWPaymentsInitiate\Payment', 'ozzmodz_nowpayments_payment_initiate_embed_payment', $viewParams);
		}
	}

	public function initiateEmbeddedPayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$this->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		$simpleCache = $controller->app()->simpleCache();
		$currencies = $simpleCache->getValue('OzzModz\PaymentProviderNOWPayments', 'currencies');

		if (!$currencies || (\XF::$time - $controller->options()->ozzmodz_nowpayments_lastCurrenciesUpdate) > 60)
		{
			$currencies = $apiHelper->getFullCurrencies($error)['currencies'] ?? null;
			if (!$currencies)
			{
				return $controller->error($error ?: XF::phrase('ozzmodz_nowpayments_no_api_response'));
			}

			$checkedCurrencies = $apiHelper->getAvailableCheckedCurrencies();

			if (isset($checkedCurrencies['selectedCurrencies']))
			{
				foreach ($currencies as $key => $currency)
				{
					if (!in_array($currency['code'], $checkedCurrencies['selectedCurrencies']))
					{
						unset($currencies[$key]);
					}
				}
			}

			$priority = array_column($currencies, 'priority');
			array_multisort($priority, SORT_ASC, $currencies);
			$simpleCache->setValue('OzzModz\PaymentProviderNOWPayments', 'currencies', $currencies);

			/** @var \XF\Repository\Option $optionRepo */
			$optionRepo = $controller->repository('XF:Option');
			$optionRepo->updateOption('ozzmodz_nowpayments_lastCurrenciesUpdate', \XF::$time);
		}

		$viewParams = [
			'purchase' => $purchase,
			'purchaseRequest' => $purchaseRequest,
			'paymentProfile' => $paymentProfile,
			'currencies' => $currencies,
			'apiPublicKey' => $paymentProfile->options['public_key'] ?? null,
			'apiUrl' => $this->getApiEndpoint(),
			'sandboxMode' => !\XF::config('enableLivePayments')
		];

		return $controller->view('XF:Purchase\NOWPaymentsInitiate', 'ozzmodz_nowpayments_payment_initiate_embed', $viewParams);
	}

	public function initiateRecurringPayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;

		$viewParams = [
			'purchase' => $purchase,
			'purchaseRequest' => $purchaseRequest,
			'paymentProfile' => $paymentProfile,
			'sandboxMode' => !\XF::config('enableLivePayments')
		];

		return $controller->view('XF:Purchase\NOWPaymentsInitiate', 'ozzmodz_nowpayments_payment_initiate_recurring', $viewParams);
	}

	protected function getPaymentParams(PurchaseRequest $purchaseRequest, Purchase $purchase, $forEmbedded = false)
	{
		$paymentProfile = $purchase->paymentProfile;

		$params = [
			'price_amount' => $purchase->cost,
			'price_currency' => $purchase->currency,
			'order_id' => $purchaseRequest->request_key,
			'order_description' => $purchaseRequest->Purchasable->title,
			'ipn_callback_url' => $this->getCallbackUrl(),
			'is_fixed_rate' => !empty($paymentProfile->options['is_fixed_rate']) ? 'true' : 'false',
			'is_fee_paid_by_user' => !empty($paymentProfile->options['is_fee_paid_by_user']) ? 'true' : 'false',
		];

		if (!$forEmbedded)
		{
			$params['success_url'] = $purchase->returnUrl;
			$params['cancel_url'] = $purchase->cancelUrl;
		}

		if (!\XF::config('enableLivePayments'))
		{
			$params['case'] = $purchaseRequest->PaymentProfile->options['test_case'] ?? 'failed';
		}

		return $params;
	}

	public function getInitiatePaymentParams(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
	{
		$paymentProfile = $purchase->paymentProfile;

		if (!$this->verifyCurrency($paymentProfile, $purchase->currency))
		{
			throw $controller->errorException(XF::phrase('ozzmodz_nowpayments_incorrect_currency'));
		}

		$paymentParams = $this->getPaymentParams($purchaseRequest, $purchase);

		/** @var \OzzModz\PaymentProviderNOWPayments\Helper\NOWPaymentsApi $apiHelper */
		$apiHelper = \XF::helper(
			'OzzModz\PaymentProviderNOWPayments:NOWPaymentsApi',
			$this->getApiEndpoint(),
			$paymentProfile->options['api_key']
		);

		$response = $apiHelper->createInvoice($paymentParams, $controller->request()->getIp());
		if (!$response || empty($response['id']))
		{
			throw $controller->exception($controller->error(XF::phrase('ozzmodz_nowpayments_no_api_response')));
		}

		if (empty($response['invoice_url']))
		{
			throw $controller->exception($controller->error(XF::phrase('ozzmodz_nowpayments_no_invoice_url')));
		}

		$viewParams = [
			'purchase' => $purchase,
			'paymentProfile' => $paymentProfile,
			'invoiceUrl' => $response['invoice_url'],
			'sandboxMode' => !\XF::config('enableLivePayments')
		];

		return $viewParams + $paymentParams;
	}

	/**
	 * @inheritDoc
	 */
	public function setupCallback(\XF\Http\Request $request)
	{
		return new CallbackState();
	}

	protected static function tksort(&$array)
	{
		$array = $array ?: [];
		ksort($array);
		foreach (array_keys($array) as $k)
		{
			if (gettype($array[$k]) == "array")
			{
				self::tksort($array[$k]);
			}
		}
	}

	protected function checkIpnRequestIsValid($sortedRequestJson, $secret, $signature, &$error = null)
	{
		if (!empty($signature))
		{
			$receivedHmac = $signature;

			if (!empty($sortedRequestJson))
			{
				$hmac = hash_hmac("sha512", $sortedRequestJson, trim($secret));
				if ($hmac == $receivedHmac)
				{
					return true;
				}
				else
				{
					$error = 'HMAC signature does not match';
				}
			}
			else
			{
				$error = 'Error reading POST data';
			}
		}
		else
		{
			$error = 'No HMAC signature sent.';
		}

		return false;
	}

	public function validateCallback(CallbackState $state)
	{
		$app = \XF::app();
		$request = $app->request();
		$signature = $request->getServer('HTTP_X_NOWPAYMENTS_SIG');

		$requestJson = @file_get_contents('php://input');
		$requestData = json_decode($requestJson, true);

		unset($requestData['_xfProvider']);
		unset($requestData['payment_profile_id']);

		self::tksort($requestData);
		$sortedRequestJson = json_encode($requestData, JSON_UNESCAPED_SLASHES);

		/** @var PaymentProfile $profile */
		$profile = \XF::em()->find('XF:PaymentProfile', $request->filter('payment_profile_id', 'uint'));
		if (!$profile)
		{
			$state->httpCode = 404;
			return false;
		}

		$secret = $profile->options['ipn_secret'] ?? '';
		if (!$this->checkIpnRequestIsValid($sortedRequestJson, $secret, $signature, $error))
		{
			$state->httpCode = 403;
			return false;
		}

		$state->requestData = $requestData;
		if (empty($state->requestData))
		{
			$state->logType = 'error';
			$state->logMessage = 'Empty callback response.';

			return false;
		}

		$app = XF::app();
		$inputFilterer = $app->inputFilterer();

		if (isset($requestData['payment_id']))
		{
			$state->trasactionType = 'payment';

			$state->transactionId = $inputFilterer->filter($requestData['payment_id'], 'str');
			$state->requestKey = $inputFilterer->filter($requestData['order_id'], 'str');

			$state->price_currency = $inputFilterer->filter($requestData['price_currency'], 'str');
			$state->price_amount = $inputFilterer->filter($requestData['price_amount'], 'str');
			$state->payment_status = $inputFilterer->filter($requestData['payment_status'], 'str');

			if ($state->payment_status == 'waiting')
			{
				$state->httpCode = 200;
				return false;
			}
		}
		else
		{
			$state->trasactionType = 'recurring';

			/** @var \OzzModz\PaymentProviderNOWPayments\Entity\Subscription $subscription */
			$subscription = \XF::em()->findOne('OzzModz\PaymentProviderNOWPayments:Subscription', [
				'subscription_plan_id' => $requestData['plan_id'],
				'email' => $requestData['email'],
			]);
			if (!$subscription)
			{
				$state->logMessage = 'No subscription log found';
				return false;
			}

			$state->transactionId = $inputFilterer->filter($requestData['id'], 'uint');
			$state->requestKey = $subscription->purchase_request_key;
			$state->subscriberId = $subscription->subscription_id;
			$state->status = $inputFilterer->filter($requestData['status'], 'str');

			if ($state->status == 'WAITING_PAY')
			{
				$state->httpCode = 200;
				return false;
			}
		}

		$state->logMessage = 'Ok';
		$state->httpCode = 200;

		return true;
	}

	public function validateTransaction(CallbackState $state)
	{
		if (!$state->requestKey)
		{
			$state->logType = 'info';
			$state->logMessage = 'No purchase request key. Unrelated payment, no action to take.';

			return false;
		}

		if (!$state->transactionId && !$state->subscriberId)
		{
			$state->logType = 'error';
			$state->logMessage = 'No transaction or Subscriber ID. No action to take.';

			return false;
		}

		/** @var \XF\Repository\Payment $paymentRepo */
		$paymentRepo = \XF::repository('XF:Payment');
		$matchingLogsFinder = $paymentRepo->findLogsByTransactionIdForProvider($state->transactionId, $this->providerId);
		if ($matchingLogsFinder->total())
		{
			$logs = $matchingLogsFinder->fetch();

			/** @var \XF\Entity\PaymentProviderLog $log */
			foreach ($logs AS $log)
			{
				// Allow processing refunds
				if ($log->log_type == 'payment' && $state->status == 'refunded')
				{
					return true;
				}
			}

			$state->logType = 'info';
			$state->logMessage = 'Transaction already processed. Skipping.';
			return false;
		}

		return true;
	}

	public function validateCost(CallbackState $state)
	{
		$purchaseRequest = $state->getPurchaseRequest();

		if ($state->trasactionType == 'recurring')
		{
			switch ($state->status)
			{
				case 'PARTIALLY_PAID':
					$state->logType = 'info';
					$state->logMessage = 'Transaction partially paid';
					break;
				case 'EXPIRED':
					$state->logType = 'error';
					$state->logMessage = 'Transaction expired';
					return false;
				default:
					break;
			}
		}
		else
		{
			switch ($state->payment_status)
			{
				case 'partially_paid':
					$state->logType = 'info';
					$state->logMessage = 'Transaction partially paid';
					break;
				case 'refunded':
					$state->logType = 'error';
					$state->logMessage = 'Transaction cancelled/refunded';
					return false;
				case 'failed':
					$state->logType = 'error';
					$state->logMessage = 'Transaction failed';
					return false;
				case 'expired':
					$state->logType = 'error';
					$state->logMessage = 'Transaction expired';
					return false;
				default:
					break;
			}

			if ($state->price_amount != $purchaseRequest->cost_amount)
			{
				$state->logType = 'error';
				$state->logMessage = 'Invalid cost amount';

				return false;
			}

			if (strcasecmp($state->price_currency, $purchaseRequest->cost_currency) == -1)
			{
				$state->logType = 'error';
				$state->logMessage = 'Invalid cost currency';

				return false;
			}
		}

		return true;
	}

	public function getPaymentResult(CallbackState $state)
	{
		if ($state->trasactionType == 'recurring')
		{
			if ($state->status == 'FINISHED' || $state->status == 'PAID')
			{
				$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
			}
		}
		else
		{
			if ($state->payment_status == 'finished')
			{
				$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
			}
			elseif ($state->payment_status == 'refunded')
			{
				$state->paymentResult = CallbackState::PAYMENT_REVERSED;
			}
		}
	}

	public function prepareLogData(CallbackState $state)
	{
		$state->logDetails = $state->requestData ?: [];
	}

	public function getCallbackUrl()
	{
		return \XF::app()->options()->boardUrl . "/payment_callback.php?_xfProvider=$this->providerId&payment_profile_id=$this->profileId";
	}
}
