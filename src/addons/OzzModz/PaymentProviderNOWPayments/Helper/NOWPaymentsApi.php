<?php

namespace OzzModz\PaymentProviderNOWPayments\Helper;

class NOWPaymentsApi
{
	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $client;

	protected $endpoint;

	protected $apiKey;

	protected $jwtToken;

	public function __construct($endpoint, $apiKey, $jwtToken = null)
	{
		$this->endpoint = $endpoint;
		$this->client = \XF::app()->http()->client();
		$this->apiKey = $apiKey;
		$this->jwtToken = $jwtToken;
	}

	public function getJwtToken()
	{
		return $this->jwtToken;
	}

	public function authenticate($email, $password, &$error = null)
	{
		$response = null;
		try
		{
			$response = \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/auth", [
				'headers' => [
					'Content-Type' => 'application/json'
				],
				\GuzzleHttp\RequestOptions::JSON => [
					'email' => $email,
					'password' => $password
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$error = $e->getMessage();
		}

		if (!empty($response['token']))
		{
			$this->jwtToken = $response['token'];
		}

		return $response;
	}

	public function createInvoice($params, $originIp, &$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/invoice", [
				'headers' => [
					'x-api-key' => $this->apiKey,
					'origin-ip' => $originIp,
				],
				\GuzzleHttp\RequestOptions::JSON => $params,
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function createPaymentByInvoice($invoiceId, $payCurrency, $originIp, &$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/invoice-payment", [
				'headers' => [
					'x-api-key' => $this->apiKey,
					'origin-ip' => $originIp,
				],
				\GuzzleHttp\RequestOptions::JSON => [
					'iid' => $invoiceId,
					'pay_currency' => $payCurrency,
					'case' => 'success'
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function createPayment($params, $originIp, &$error = null)
	{
		if (empty($params['price_amount'])
			|| empty($params['price_currency'])
			|| empty($params['pay_currency'])
		)
		{
			throw new \InvalidArgumentException("Missing required parameters");
		}

		try
		{
			return \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/payment", [
				'headers' => [
					'x-api-key' => $this->apiKey,
					'origin-ip' => $originIp,
				],
				\GuzzleHttp\RequestOptions::JSON => $params,
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$error = $e->getResponse()->getBody()->getContents();
		}

		return null;
	}

	public function getPayment($paymentId, &$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->get("$this->endpoint/v1/payment/$paymentId", [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getResponse()->getBody()->getContents();
		}

		return null;
	}

	public function getAndUpdatePaymentEstimate($paymentId, &$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/payment/$paymentId/update-merchant-estimate", [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function getEstimatedPrice($amount, $currencyFrom, $currencyTo, &$error = null)
	{
		$params = [
			'amount' => $amount,
			'currency_from' => $currencyFrom,
			'currency_to' => $currencyTo
		];
		try
		{
			return \GuzzleHttp\json_decode($this->client->get("$this->endpoint/v1/estimate?" . http_build_query($params), [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function getFullCurrencies(&$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->get("$this->endpoint/v1/full-currencies", [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function getAvailableCheckedCurrencies(&$error = null)
	{
		try
		{
			return \GuzzleHttp\json_decode($this->client->get("$this->endpoint/v1/merchant/coins", [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return null;
	}

	public function getPlan($planId, &$error = null)
	{
		$result = null;
		try
		{
			$result = \GuzzleHttp\json_decode($this->client->get("$this->endpoint/v1/subscriptions/plans/$planId", [
				'headers' => [
					'x-api-key' => $this->apiKey
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$error = $e->getMessage();
		}

		return $result;
	}

	public function createPlan($params, &$error = null)
	{
		$result = null;
		try
		{
			$result = \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/subscriptions/plans", [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->jwtToken
				],
				\GuzzleHttp\RequestOptions::JSON => $params,
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$error = $e->getMessage();
		}

		return $result;
	}

	public function updatePlan($planId, $params, &$error = null)
	{
		$result = null;
		try
		{
			$result = \GuzzleHttp\json_decode($this->client->patch("$this->endpoint/v1/subscriptions/plans/$planId", [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->jwtToken
				],
				\GuzzleHttp\RequestOptions::JSON => $params,
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			\XF::logException($e);
			$error = $e->getMessage();
		}

		return $result;
	}

	public function createSubscription($planId, $email, &$error = null)
	{
		$response = null;
		try
		{
			$response = \GuzzleHttp\json_decode($this->client->post("$this->endpoint/v1/subscriptions", [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->jwtToken,
					'x-api-key' => $this->apiKey,
					'Content-Type' => 'application/json'
				],
				\GuzzleHttp\RequestOptions::JSON => [
					'subscription_plan_id' => $planId,
					'email' => $email
				],
			])->getBody()->getContents(), true);
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$contents = $e->getResponse()->getBody()->getContents();
			$json = @json_decode($contents, true);

			if ($json && isset($json['message']))
			{
				$error = $json['message'];
			}
		}

		return $response;
	}

	public function deleteSubscription($subscriptionId, &$error = null)
	{
		$result = null;
		try
		{
			$response = $this->client->delete("$this->endpoint/v1/subscriptions/$subscriptionId", [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->jwtToken,
				]
			]);

			$result = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
			if ($response->getStatusCode() == 404)
			{
				$error = 'Subscription not found';
			}
		}
		catch (\GuzzleHttp\Exception\RequestException $e)
		{
			$error = $e->getMessage();
		}

		return $result;
	}
}