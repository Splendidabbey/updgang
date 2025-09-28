<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use XF\Mvc\Entity\Entity;

/**
 * Class React
 *
 * @package DBTech\Credits\EventTrigger
 */
class React extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'canRevert' => true,
			'canCancel' => true,
			'useOwner' => true,
			'canRebuild' => true,
		]);
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
		if ($extraParams->reaction_id)
		{
			$reactionIds = $event->getSetting('reaction_ids');

			if (
				$reactionIds
				&& !in_array(-1, $reactionIds)
				&& !in_array($extraParams->reaction_id, $reactionIds)
			) {
				// This reaction didn't count
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

		if ($transaction->negate)
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_react_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_react_negate', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_react', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_react', $transaction);
			}
		}
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

	/**
	 * @inheritDoc
	 */
	protected function getFilterOptions(): array
	{
		$filterOptions = parent::getFilterOptions();

		return \array_merge($filterOptions, [
			'reaction_ids' => 'array-uint',
		]);
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 *
	 * @throws \XF\PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var \DBTech\Credits\XF\Entity\ReactionContent $entity */

		$contentInfo = $entity->getContent();
		if ($contentInfo !== null)
		{
			$nodeId = 0;

			switch ($entity->content_type)
			{
				case 'post':
					/** @var \XF\Entity\Post $contentInfo */
					if (!$contentInfo->Thread)
					{
						break;
					}

					$nodeId = $contentInfo->Thread->node_id;
					break;
			}

			$this->apply($entity->reaction_content_id, [
				'node_id' => $nodeId,
				'owner_id' => $entity->content_user_id,
				'reaction_id' => $entity->reaction_id,

				'content_type' => $entity->content_type,
				'content_id'   => $entity->content_id,

				'timestamp'   => $entity->reaction_date,
				'enableAlert' => false,
				'runPostSave' => false
			], $entity->ReactionUser);
		}
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return ['ReactionUser'];
	}
}