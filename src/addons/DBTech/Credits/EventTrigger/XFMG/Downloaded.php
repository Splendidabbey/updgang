<?php

namespace DBTech\Credits\EventTrigger\XFMG;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;

/**
 * Class Downloaded
 *
 * @package DBTech\Credits\EventTrigger\XFMG
 */
class Downloaded extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}

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
		$extraParams = array_replace([
			'apply_guest' => false,
			'extension' => '',
		], $extraParams);

		return parent::trigger($user, $refId, $negate, $extraParams);
	}

	/**
	 * @param EventEntity $event
	 * @param \XF\Entity\User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return bool
	 */
	protected function assertEvent(EventEntity $event, \XF\Entity\User $user, \ArrayObject $extraParams): bool
	{
		if (
			!$event->getSetting('apply_guest')
			&& !$user->user_id
		) {
			return false;
		}

		if ($extraParams->extension)
		{
			if (
				$event->getSetting('extension_include')
				&& !in_array($extraParams->extension, explode(',', $event->getSetting('extension_include')))
			) {
				// This extension didn't count
				return false;
			}

			if (
				$event->getSetting('extension_exclude')
				&& in_array($extraParams->extension, explode(',', $event->getSetting('extension_exclude')))
			) {
				// This extension didn't count
				return false;
			}
		}

		return parent::assertEvent($event, $user, $extraParams);
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
			return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_gallerydownloaded', $transaction);
		}
		else
		{
			return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_gallerydownloaded', $transaction);
		}
	}

	/**
	 * @return array
	 */
	public function getLabels(): array
	{
		$labels = parent::getLabels();

		$labels['minimum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_byte_minimum_amount');
		$labels['maximum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_byte_maximum_amount');
		$labels['minimum_action'] = \XF::phrase('dbtech_credits_eventtrigger_byte_minimum_action');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_eventtrigger_byte_minimum_action_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_byte_addition');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_byte_addition_explain');

		return $labels;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFilterOptions(): array
	{
		$filterOptions = parent::getFilterOptions();

		return \array_merge($filterOptions, [
			'extension_include' => 'str',
			'extension_exclude' => 'str',
			'apply_guest' => 'bool',
		]);
	}
}