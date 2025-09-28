<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class PaidThreadAccess
 *
 * @package DBTech\Credits\EventTrigger
 */
class ContentAccess extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
//			'isGlobal' => true,
			'canCharge' => false,
			'canCancel' => true,

			'multiplier' => self::MULTIPLIER_CURRENCY
		]);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($which == 'spent')
		{
			return $this->getAlertPhrase('dbtech_credits_paid_x_y_via_content_access', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_earned_x_y_via_content_access', $transaction);
		}
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}
}