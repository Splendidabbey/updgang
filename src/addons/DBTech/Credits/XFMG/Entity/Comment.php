<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFMG\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XFMG\Entity\Comment
 */
class Comment extends XFCP_Comment
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if (!$this->user_id || $this->rating_id)
		{
			return;
		}

		if ($this->isUpdate())
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$commentEvent = $eventTriggerRepo->getHandler('gallerycomment');
			$commentedEvent = $eventTriggerRepo->getHandler('gallerycommented');

			$visibilityChange = $this->isStateChanged('comment_state', 'visible');
			if ($visibilityChange == 'leave')
			{
				// Undo the event
				$commentEvent
					->testUndo([
						'multiplier' => $this->getPreviousValue('message'),
						'owner_id' => $this->Content->user_id
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->testUndo([
							'multiplier'     => $this->getPreviousValue('message'),
							'source_user_id' => $this->user_id
						], $this->Content->User);
				}
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event
				$commentEvent
					->testApply([
						'multiplier' => $this->message,
						'owner_id' => $this->Content->user_id
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->testApply([
							'multiplier'     => $this->message,
							'source_user_id' => $this->user_id
						], $this->Content->User);
				}
			}
			elseif ($this->isChanged('message'))
			{
				// Undo the event
				$commentEvent
					->testUndo([
						'multiplier' => $this->getPreviousValue('message'),
						'owner_id' => $this->Content->user_id
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->testUndo([
							'multiplier'     => $this->getPreviousValue('message'),
							'source_user_id' => $this->user_id
						], $this->Content->User);
				}

				// Reapply the event
				$commentEvent
					->testApply([
						'multiplier' => $this->message,
						'owner_id' => $this->Content->user_id
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->testApply([
							'multiplier'     => $this->message,
							'source_user_id' => $this->user_id
						], $this->Content->User);
				}
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

		if (!$this->user_id || $this->rating_id)
		{
			return;
		}

		if ($this->isUpdate())
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			$commentEvent = $eventTriggerRepo->getHandler('gallerycomment');
			$commentedEvent = $eventTriggerRepo->getHandler('gallerycommented');

			$visibilityChange = $this->isStateChanged('comment_state', 'visible');
			if ($visibilityChange == 'leave')
			{
				// Undo the event
				$commentEvent
					->undo($this->comment_id, [
						'multiplier' => $this->getPreviousValue('message'),
						'owner_id' => $this->Content->user_id,
						'content_type' => 'xfmg_comment',
						'content_id' => $this->comment_id,
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->undo($this->comment_id, [
							'multiplier'     => $this->getPreviousValue('message'),
							'source_user_id' => $this->user_id,
							'content_type'   => 'xfmg_comment',
							'content_id'     => $this->comment_id,
						], $this->Content->User);
				}
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event
				$commentEvent
					->apply($this->comment_id, [
						'multiplier' => $this->message,
						'owner_id' => $this->Content->user_id,
						'content_type' => 'xfmg_comment',
						'content_id' => $this->comment_id,
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->apply($this->comment_id, [
							'multiplier'     => $this->message,
							'source_user_id' => $this->user_id,
							'content_type'   => 'xfmg_comment',
							'content_id'     => $this->comment_id,
						], $this->Content->User);
				}
			}
			elseif ($this->isChanged('message'))
			{
				// Undo the event
				$commentEvent
					->undo($this->comment_id, [
						'multiplier' => $this->getPreviousValue('message'),
						'owner_id' => $this->Content->user_id,
						'content_type' => 'xfmg_comment',
						'content_id' => $this->comment_id,
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->undo($this->comment_id, [
							'multiplier'     => $this->getPreviousValue('message'),
							'source_user_id' => $this->user_id,
							'content_type'   => 'xfmg_comment',
							'content_id'     => $this->comment_id,
						], $this->Content->User);
				}

				// Reapply the event
				$commentEvent
					->apply($this->comment_id, [
						'multiplier' => $this->message,
						'owner_id' => $this->Content->user_id,
						'content_type' => 'xfmg_comment',
						'content_id' => $this->comment_id,
					], $this->User)
				;

				if ($this->user_id != $this->Content->user_id)
				{
					$commentedEvent
						->apply($this->comment_id, [
							'multiplier'     => $this->message,
							'source_user_id' => $this->user_id,
							'content_type'   => 'xfmg_comment',
							'content_id'     => $this->comment_id,
						], $this->Content->User);
				}
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

		if (!$this->user_id || $this->rating_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('gallerycomment')
			->testUndo([
				'multiplier' => $this->message,
				'owner_id' => $this->Content->user_id
			], $this->User)
		;

		$eventTriggerRepo->getHandler('gallerycommented')
			->testUndo([
				'multiplier' => $this->message,
				'source_user_id' => $this->user_id
			], $this->Content->User)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		if (!$this->user_id || $this->rating_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('gallerycomment')
			->undo($this->comment_id, [
				'multiplier' => $this->message,
				'owner_id' => $this->Content->user_id,
				'content_type' => 'xfmg_comment',
				'content_id' => $this->comment_id,
			], $this->User)
		;

		$eventTriggerRepo->getHandler('gallerycommented')
			->undo($this->comment_id, [
				'multiplier' => $this->message,
				'source_user_id' => $this->user_id,
				'content_type' => 'xfmg_comment',
				'content_id' => $this->comment_id,
			], $this->Content->User)
		;
	}
}