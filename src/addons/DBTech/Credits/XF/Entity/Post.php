<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\Post
 */
class Post extends XFCP_Post
{
	/**
	 * @param $inner
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

		if (!$this->user_id || $this->isInsert())
		{
			return;
		}

		// Get thread info
		$previousThread = $this->isChanged('thread_id')
			? Helper::find(\XF\Entity\Thread::class, $this->getPreviousValue('thread_id'))
			: $this->Thread;

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if (!$this->isFirstPost())
		{
			// Shorthand
			$visibilityChange = $this->isStateChanged('message_state', 'visible');

			// BEGIN POST EVENT
			// Init the event
			$postEvent = $eventTriggerRepo->getHandler('post');

			if ($visibilityChange == 'leave')
			{
				// Undo the event
				$postEvent->testUndo([
					'node_id'    => $previousThread->node_id,
					'thread_id'  => $this->getPreviousValue('thread_id'),
					'multiplier' => $this->getPreviousValue('message'),
					'owner_id'   => $previousThread->user_id
				], $this->User);
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event
				$postEvent->testApply([
					'node_id'    => $this->Thread->node_id,
					'thread_id'  => $this->thread_id,
					'multiplier' => $this->message,
					'owner_id'   => $this->Thread->user_id
				], $this->User);
			}
			// END POST EVENT

			if ($this->user_id != $this->Thread->user_id)
			{
				// BEGIN REPLY EVENT
				// Init the event
				$replyEvent = $eventTriggerRepo->getHandler('reply');

				if ($visibilityChange == 'leave')
				{
					// Undo the event
					$replyEvent->testUndo([
						'node_id'        => $previousThread->node_id,
						'thread_id'      => $this->getPreviousValue('thread_id'),
						'source_user_id' => $this->getPreviousValue('user_id')
					], $previousThread->User);
				}
				elseif ($visibilityChange == 'enter')
				{
					// Reapply the event
					$replyEvent->testApply([
						'node_id'        => $this->Thread->node_id,
						'thread_id'      => $this->thread_id,
						'source_user_id' => $this->user_id
					], $this->Thread->User);
				}
				// END REPLY EVENT
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		if (!$this->user_id || $this->isInsert())
		{
			return;
		}

		if ($this->isChanged('message'))
		{
			// Get rid of any existing charge tags, the BBCode renderer will re-process them
			$this->db()->delete(
				'xf_dbtech_credits_charge',
				'content_type = ? AND content_id = ?',
				['post', $this->post_id]
			);
		}

		// Get thread info
		$previousThread = $this->isChanged('thread_id')
			? Helper::find(\XF\Entity\Thread::class, $this->getPreviousValue('thread_id'))
			: $this->Thread;

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		// Shorthand
		$visibilityChange = $this->isStateChanged('message_state', 'visible');

		if (!$this->isFirstPost())
		{
			// BEGIN POST EVENT
			// Init the event
			$postEvent = $eventTriggerRepo->getHandler('post');

			if ($visibilityChange == 'leave')
			{
				// Undo the event
				$postEvent->undo($this->getPreviousValue('post_id'), [
					'node_id'      => $previousThread->node_id,
					'thread_id'    => $this->getPreviousValue('thread_id'),
					'multiplier'   => $this->getPreviousValue('message'),
					'owner_id'     => $previousThread->user_id,
					'content_type' => 'post',
					'content_id'   => $this->getPreviousValue('post_id')
				], $this->User);
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event
				$postEvent->apply($this->post_id, [
					'node_id'      => $this->Thread->node_id,
					'thread_id'    => $this->thread_id,
					'multiplier'   => $this->message,
					'owner_id'     => $this->Thread->user_id,
					'content_type' => 'post',
					'content_id'   => $this->post_id
				], $this->User);
			}
			// END POST EVENT

			if ($this->user_id != $this->Thread->user_id)
			{
				// BEGIN REPLY EVENT
				// Init the event
				$replyEvent = $eventTriggerRepo->getHandler('reply');

				if ($visibilityChange == 'leave')
				{
					// Undo the event
					$replyEvent->undo($this->getPreviousValue('post_id'), [
						'node_id'        => $previousThread->node_id,
						'thread_id'      => $this->getPreviousValue('thread_id'),
						'source_user_id' => $this->getPreviousValue('user_id'),
						'content_type'   => 'post',
						'content_id'     => $this->getPreviousValue('post_id')
					], $previousThread->User);
				}
				elseif ($visibilityChange == 'enter')
				{
					// Reapply the event
					$replyEvent->apply($this->post_id, [
						'node_id'        => $this->Thread->node_id,
						'thread_id'      => $this->thread_id,
						'source_user_id' => $this->user_id,
						'content_type'   => 'post',
						'content_id'     => $this->post_id
					], $this->Thread->User);
				}
				// END REPLY EVENT
			}
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

		if ($this->isFirstPost())
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if ($this->message_state == 'visible')
		{
			// BEGIN POST EVENT
			// Undo the event
			$eventTriggerRepo->getHandler('post')
				->testUndo([
					'node_id'    => $this->Thread->node_id,
					'thread_id'  => $this->thread_id,
					'multiplier' => $this->message,
					'owner_id'   => $this->Thread->user_id
				], $this->User)
			;
			// END POST EVENT

			if ($this->user_id != $this->Thread->user_id)
			{
				// BEGIN REPLY EVENT
				// Undo the event
				$eventTriggerRepo->getHandler('reply')
					->testUndo([
						'node_id'        => $this->Thread->node_id,
						'thread_id'      => $this->thread_id,
						'source_user_id' => $this->user_id
					], $this->Thread->User)
				;
				// END REPLY EVENT
			}
		}
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

		if ($this->isFirstPost())
		{
			if ($this->Thread->discussion_state == 'visible')
			{
				// Undo the event
				$eventTriggerRepo->getHandler('thread')
					->undo($this->thread_id, [
						'multiplier'   => $this->message,
						'node_id'      => $this->Thread->node_id,
						'content_type' => 'thread',
						'content_id'   => $this->thread_id
					], $this->User)
				;
			}
		}
		else
		{
			if ($this->message_state == 'visible')
			{
				// BEGIN POST EVENT
				// Undo the event
				$eventTriggerRepo->getHandler('post')
					->undo($this->post_id, [
						'node_id'      => $this->Thread->node_id,
						'thread_id'    => $this->thread_id,
						'multiplier'   => $this->message,
						'owner_id'     => $this->Thread->user_id,
						'content_type' => 'post',
						'content_id'   => $this->post_id
					], $this->User)
				;
				// END POST EVENT

				if ($this->user_id != $this->Thread->user_id)
				{
					// BEGIN REPLY EVENT
					// Undo the event
					$eventTriggerRepo->getHandler('reply')
						->undo($this->post_id, [
							'node_id'        => $this->Thread->node_id,
							'thread_id'      => $this->thread_id,
							'source_user_id' => $this->user_id,
							'content_type'   => 'post',
							'content_id'     => $this->post_id
						], $this->Thread->User)
					;
					// END REPLY EVENT
				}
			}
		}
	}
}