<?php

namespace DBTech\Credits\Option;

use DBTech\Credits\Helper;
use XF\Option\AbstractOption;

class Currency extends AbstractOption
{
	/**
	 * @param \XF\Entity\Option $option
	 * @param array $htmlParams
	 *
	 * @return string
	 */
	public static function renderSelect(\XF\Entity\Option $option, array $htmlParams): string
	{
		$data = self::getSelectData($option, $htmlParams);

		return self::getTemplater()->formSelectRow(
			$data['controlOptions'],
			$data['choices'],
			$data['rowOptions']
		);
	}

	/**
	 * @param \XF\Entity\Option $option
	 * @param array $htmlParams
	 *
	 * @return string
	 */
	public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams): string
	{
		$data = self::getSelectData($option, $htmlParams);
		$data['controlOptions']['multiple'] = true;
		$data['controlOptions']['size'] = 8;

		return self::getTemplater()->formSelectRow(
			$data['controlOptions'],
			$data['choices'],
			$data['rowOptions']
		);
	}

	/**
	 * @param \XF\Entity\Option $option
	 * @param array $htmlParams
	 *
	 * @return array
	 */
	protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams): array
	{
		$currencyRepo = Helper::repository(\DBTech\Credits\Repository\Currency::class);

		$choices = $currencyRepo->getCurrencyOptionsData(true, 'option');
		$choices = array_map(function ($v): array
		{
			$v['label'] = \XF::escapeString($v['label']);
			return $v;
		}, $choices);

		return [
			'choices' => $choices,
			'controlOptions' => self::getControlOptions($option, $htmlParams),
			'rowOptions' => self::getRowOptions($option, $htmlParams)
		];
	}
}