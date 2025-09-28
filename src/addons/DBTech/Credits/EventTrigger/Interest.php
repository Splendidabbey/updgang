<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class Interest
 *
 * @package DBTech\Credits\EventTrigger
 */
class Interest extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			
			'multiplier' => self::MULTIPLIER_LABEL
		]);
	}
	
	/**
	 * @param TransactionEntity $transaction
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$transaction->TargetUser->fastUpdate('dbtech_credits_lastinterest', $transaction->dateline);
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
			return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_interest', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_interest', $transaction);
		}
	}
	
	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}
	
	/**
	 * @return array
	 */
	public function getLabels(): array
	{
		$labels = parent::getLabels();
		
		$labels['minimum_amount'] = \XF::phrase('dbtech_credits_event_multmin_currency');
		$labels['maximum_amount'] = \XF::phrase('dbtech_credits_event_multmax_currency');
		$labels['minimum_action'] = \XF::phrase('dbtech_credits_event_minaction_currency');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_event_minaction_currency_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_event_mult_add_currency');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_event_mult_add_currency_explain');
		$labels['multiplier_negation'] = \XF::phrase('dbtech_credits_event_mult_sub_currency');
		$labels['multiplier_negation_explain'] = \XF::phrase('dbtech_credits_event_mult_sub_currency_explain');
		
		return $labels;
	}
}