<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class Content
 *
 * @package DBTech\Credits\EventTrigger
 */
class Content extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'useOwner' => true,
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
			return $this->getAlertPhrase('dbtech_credits_paid_x_y_via_content', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_earned_x_y_via_content', $transaction);
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
		
		$labels['owner_explain'] = \XF::phrase('dbtech_credits_event_owner_explain_post');
		$labels['owner_only_others'] = \XF::phrase('dbtech_credits_event_owner_only_others_post');
		$labels['owner_only_own'] = \XF::phrase('dbtech_credits_event_owner_only_own_post');
		
		return $labels;
	}
}