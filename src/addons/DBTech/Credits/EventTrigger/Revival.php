<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class Revival
 *
 * @package DBTech\Credits\EventTrigger
 */
class Revival extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'useOwner' => true,
			'canCancel' => true,
			
			'multiplier' => self::MULTIPLIER_LABEL
		]);
	}
	
	/**
	 * @param \XF\Entity\User $user
	 * @param mixed $refId
	 * @param bool $negate
	 * @param array $extraParams
	 *
	 * @return TransactionEntity[]
	 * @throws \XF\PrintableException
	 */
	protected function trigger(
		\XF\Entity\User $user,
		$refId,
		bool $negate = false,
		array $extraParams = []
	): array {
		$extraParams = array_replace([
			'last_post_date' => \XF::$time,
		], $extraParams);
		
		if (!$negate)
		{
			$options = $this->options();
			
			$timeSinceLastPost = (\XF::$time - $extraParams['last_post_date']);
			
			if (
				$options->dbtech_credits_eventtrigger_revival_threshold
				&& $timeSinceLastPost >= $options->dbtech_credits_eventtrigger_revival_threshold
			) {
				return [];
			}
		}
		
		return parent::trigger($user, $refId, $negate, $extraParams);
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
			return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_revival', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_revival', $transaction);
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
		
		$labels['minimum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_day_minimum_amount');
		$labels['maximum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_day_maximum_amount');
		$labels['minimum_action'] = \XF::phrase('dbtech_credits_eventtrigger_day_minimum_action');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_eventtrigger_day_minimum_action_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_day_addition');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_day_addition_explain');
		
		$labels['owner_explain'] = \XF::phrase('dbtech_credits_event_owner_explain_thread');
		$labels['owner_only_others'] = \XF::phrase('dbtech_credits_event_owner_only_others_thread');
		$labels['owner_only_own'] = \XF::phrase('dbtech_credits_event_owner_only_own_thread');
		
		return $labels;
	}
}