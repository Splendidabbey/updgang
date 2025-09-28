<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\Poll
 */
class Poll extends XFCP_Poll
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		$contentInfo = $this->getContent();
		if ($contentInfo !== null && $contentInfo->isValidRelation('User'))
		{
			$nodeId = 0;
			if ($this->content_type == 'thread')
			{
				$nodeId = $contentInfo->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$pollEvent = $eventTriggerRepo->getHandler('poll');

			if ($this->isUpdate())
			{
				// Undo previous event
				$pollEvent->testUndo([
					'multiplier' => count($this->getPreviousValue('responses')),
					'node_id' => $nodeId
				], $contentInfo->User);
			}

			// Apply the event
			$pollEvent->testApply([
				'multiplier' => count($this->responses),
				'node_id' => $nodeId
			], $contentInfo->User);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		$contentInfo = $this->getContent();
		if ($contentInfo !== null && $contentInfo->isValidRelation('User'))
		{
			$nodeId = 0;
			if ($this->content_type == 'thread')
			{
				$nodeId = $contentInfo->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$pollEvent = $eventTriggerRepo->getHandler('poll');

			if ($this->isUpdate())
			{
				// Undo previous event
				$pollEvent->undo($this->poll_id, [
					'multiplier' => count($this->getPreviousValue('responses')),
					'node_id' => $nodeId,
					'content_type' => $this->content_type,
					'content_id' => $this->content_id
				], $contentInfo->User);
			}

			// Apply the event
			$pollEvent->apply($this->poll_id, [
				'multiplier' => count($this->responses),
				'node_id' => $nodeId,
				'content_type' => $this->content_type,
				'content_id' => $this->content_id
			], $contentInfo->User);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		$contentInfo = $this->getContent();
		if ($contentInfo !== null && $contentInfo->isValidRelation('User'))
		{
			$nodeId = 0;
			if ($this->content_type == 'thread')
			{
				$nodeId = $contentInfo->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			// Undo event
			$eventTriggerRepo->getHandler('poll')
				->testUndo([
					'multiplier' => count($this->getPreviousValue('responses')),
					'node_id' => $nodeId
				], $contentInfo->User)
			;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		// Do parent stuff
		parent::_postDelete();

		$contentInfo = $this->getContent();
		if ($contentInfo !== null && $contentInfo->isValidRelation('User'))
		{
			$nodeId = 0;
			if ($this->content_type == 'thread')
			{
				$nodeId = $contentInfo->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			// Undo event
			$eventTriggerRepo->getHandler('poll')
				->undo($this->poll_id, [
					'multiplier' => count($this->getPreviousValue('responses')),
					'node_id' => $nodeId,
					'content_type' => $this->content_type,
					'content_id' => $this->content_id
				], $contentInfo->User)
			;
		}
	}
}