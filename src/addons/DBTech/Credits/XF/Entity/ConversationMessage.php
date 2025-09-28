<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\ConversationMessage
 */
class ConversationMessage extends XFCP_ConversationMessage
{
	/**
	 * @param $inner
	 *
	 * @return string
	 */
	public function getQuoteWrapper($inner)
	{
		return parent::getQuoteWrapper(preg_replace(
			'#\[' . preg_quote(\XF::options()->dbtech_credits_eventtrigger_content_bbcode, '#') . '=(\d+|\d+[.,](\d+))\](.*)\[\/' . preg_quote(\XF::options()->dbtech_credits_eventtrigger_content_bbcode, '#') . '\]#si',
			\XF::phrase('dbtech_credits_stripped_content'),
			$inner
		));
	}

	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isInsert() || $this->isChanged('message'))
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$messageEvent = $eventTriggerRepo->getHandler('message');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$messageEvent->testUndo([
					'multiplier' => $this->getPreviousValue('message'),
				], $this->User);
			}

			// Apply the new event
			$messageEvent->testApply([
				'multiplier' => $this->message,
			], $this->User);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isInsert() || $this->isChanged('message'))
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$messageEvent = $eventTriggerRepo->getHandler('message');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$messageEvent->undo($this->message_id, [
					'multiplier'   => $this->getPreviousValue('message'),
					'content_type' => 'conversation_message',
					'content_id'   => $this->message_id
				], $this->User);
			}

			// Apply the new event
			$messageEvent->apply($this->message_id, [
				'multiplier'   => $this->message,
				'content_type' => 'conversation_message',
				'content_id'   => $this->message_id,
				'enableAlert'  => $this->isInsert()
			], $this->User);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		if (!$this->user_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		// Undo the event
		$eventTriggerRepo->getHandler('message')
			->testUndo([
				'multiplier' => $this->getPreviousValue('message'),
			], $this->User)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		// Do parent stuff
		parent::_postDelete();

		if (!$this->user_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		// Undo the event
		$eventTriggerRepo->getHandler('message')
			->undo($this->message_id, [
				'multiplier' => $this->getPreviousValue('message'),
				'content_type' => 'conversation_message',
				'content_id' => $this->message_id
			], $this->User)
		;
	}
}