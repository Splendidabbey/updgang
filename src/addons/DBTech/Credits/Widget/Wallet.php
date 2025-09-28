<?php

namespace DBTech\Credits\Widget;

use DBTech\Credits\Helper;
use XF\Widget\AbstractWidget;
use DBTech\Credits\Entity\Currency;

/**
 * Class Wallet
 *
 * @package DBTech\Credits\Widget
 */
class Wallet extends AbstractWidget
{
	/**
	 * @var array
	 */
	protected $defaultOptions = [
		'currencyIds' => ''
	];

	/**
	 * @param $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams($context): array
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$params['currencies'] = Helper::finder(\DBTech\Credits\Finder\Currency::class)
				->fetch()
				->pluckNamed('title', 'currency_id')
			;
		}
		return $params;
	}

	/**
	 * @return \XF\Widget\WidgetRenderer
	 */
	public function render(): \XF\Widget\WidgetRenderer
	{
		$options = $this->options;

		/** @var \DBTech\Credits\Entity\Currency[]|\XF\Mvc\Entity\AbstractCollection $currencies */
		$currencies = Helper::finder(\DBTech\Credits\Finder\Currency::class)
			->fetch()
			->filterViewable()
			->filter(function (Currency $currency) use ($options): ?Currency
			{
				if (
					$options['currencyIds']
					&& !in_array(0, $options['currencyIds'])
					&& !in_array($currency->currency_id, $options['currencyIds'])
				) {
					return null;
				}

				return $currency;
			})
		;

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('dbtech_credits_your_wallet'),
			'currencies' => $currencies,
		];
		return $this->renderer('dbtech_credits_widget_wallet', $viewParams);
	}

	/**
	 * @param \XF\Http\Request $request
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'currencyIds' => 'array-uint'
		]);
		if (empty($options['currencyIds']))
		{
			$error = \XF::phrase('dbtech_credits_wallet_widget_must_be_configured_to_display_one_or_more_currency');
			return false;
		}
		return true;
	}
}