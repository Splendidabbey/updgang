<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class Daily
 *
 * @package DBTech\Credits\EventTrigger
 */
class Daily extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
		]);
	}
	
	/**
	 * @param TransactionEntity $transaction
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$transaction->TargetUser->fastUpdate('dbtech_credits_lastdaily', $transaction->dateline);
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
			return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_daily', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_daily', $transaction);
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