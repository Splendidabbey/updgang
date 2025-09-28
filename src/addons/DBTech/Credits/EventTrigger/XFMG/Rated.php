<?php

namespace DBTech\Credits\EventTrigger\XFMG;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use XF\Mvc\Entity\Entity;

/**
 * Class Rated
 *
 * @package DBTech\Credits\EventTrigger\XFMG
 */
class Rated extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'canRebuild' => true,

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

		if ($transaction->negate)
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_galleryrated_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_galleryrated_negate', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_galleryrated', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_galleryrated', $transaction);
			}
		}
	}

	/**
	 * @return array
	 */
	public function getLabels(): array
	{
		$labels = parent::getLabels();

		$labels['minimum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_star_minimum_amount');
		$labels['maximum_amount'] = \XF::phrase('dbtech_credits_eventtrigger_star_maximum_amount');
		$labels['minimum_action'] = \XF::phrase('dbtech_credits_eventtrigger_star_minimum_action');
		$labels['minimum_action_explain'] = \XF::phrase('dbtech_credits_eventtrigger_star_minimum_action_explain');
		$labels['multiplier_addition'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_star_addition');
		$labels['multiplier_addition_explain'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_star_addition_explain');
		$labels['multiplier_negation'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_star_negation');
		$labels['multiplier_negation_explain'] = \XF::phrase('dbtech_credits_eventtrigger_multiplier_star_negation_explain');

		return $labels;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFilterOptions(): array
	{
		$filterOptions = parent::getFilterOptions();

		return \array_merge($filterOptions, [
			'apply_guest' => 'bool',
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 *
	 * @throws \XF\PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var \XFMG\Entity\Rating $entity */

		if ($entity->user_id != $entity->Content->user_id)
		{
			$this->apply($entity->rating_id, [
				'multiplier'     => $entity->rating,
				'source_user_id' => $entity->user_id,

				'content_type' => 'xfmg_rating',
				'content_id'   => $entity->rating_id,

				'timestamp'   => $entity->rating_date,
				'enableAlert' => false,
				'runPostSave' => false
			], $entity->Content->User);
		}
	}
}