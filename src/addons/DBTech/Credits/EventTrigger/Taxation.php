<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\Helper;

/**
 * Class Taxation
 *
 * @package DBTech\Credits\EventTrigger
 */
class Taxation extends AbstractHandler
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
		$options = $this->options();

		if (
			$options->dbtech_credits_eventtrigger_taxation_user
			&& $user->user_id == $options->dbtech_credits_eventtrigger_taxation_user
		) {
			return [];
		}

		return parent::trigger($user, $refId, $negate, $extraParams);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @throws \Exception
	 */
	protected function postSave(TransactionEntity $transaction): void
	{
		$transaction->TargetUser->fastUpdate('dbtech_credits_lasttaxation', $transaction->dateline);

		if ($transaction->transaction_state == 'visible')
		{
			$options = $this->options();

			/** @var \DBTech\Credits\XF\Entity\User $user */
			if (
				$options->dbtech_credits_eventtrigger_taxation_user
				&& $transaction->user_id != $options->dbtech_credits_eventtrigger_taxation_user
				&& $user = Helper::find(\XF\Entity\User::class, $options->dbtech_credits_eventtrigger_taxation_user)
			) {
				$transaction->Currency->verifyAdjustEvent();

				/** @var \XF\Language $language */
				$language = \XF::app()->language($user->language_id);

				/** @var \DBTech\Credits\EventTrigger\Adjust $adjustHandler */
				$adjustHandler = $this->getHandler('adjust');
				$adjustHandler->apply($transaction->transaction_id, [
					'currency_id' 		=> $transaction->currency_id,
					'multiplier' 		=> abs($transaction->amount),
					'message'  			=> $language->renderPhrase('dbtech_credits_eventtrigger_title.taxation'),
					'source_user_id' 	=> $transaction->user_id,
				], $user);
			}
		}
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
			return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_taxation', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_taxation', $transaction);
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