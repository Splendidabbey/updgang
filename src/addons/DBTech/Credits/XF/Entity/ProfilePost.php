<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\ProfilePost
 */
class ProfilePost extends XFCP_ProfilePost
{
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

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		// BEGIN VISITOR EVENT
		if (!$this->isUpdate() || $this->isChanged('message'))
		{
			$visitorEvent = $eventTriggerRepo->getHandler('visitor');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$visitorEvent->testUndo([
					'multiplier' => $this->getPreviousValue('message'),
					'owner_id'   => $this->profile_user_id
				], $this->User);
			}

			// Apply the new event
			$visitorEvent->testApply([
				'multiplier' => $this->message,
				'owner_id'   => $this->profile_user_id
			], $this->User);
		}
		// END VISITOR EVENT

		// BEGIN WALL EVENT
		if (!$this->isUpdate() || $this->isChanged('message'))
		{
			// Init the event
			$wallEvent = $eventTriggerRepo->getHandler('wall');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$wallEvent->testUndo([
					'multiplier'     => $this->getPreviousValue('message'),
					'source_user_id' => $this->user_id
				], $this->ProfileUser);
			}

			// Apply the new event
			$wallEvent->testApply([
				'multiplier'     => $this->message,
				'source_user_id' => $this->user_id
			], $this->ProfileUser);
		}
		// END WALL EVENT
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

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		// BEGIN VISITOR EVENT
		if (!$this->isUpdate() || $this->isChanged('message'))
		{
			$visitorEvent = $eventTriggerRepo->getHandler('visitor');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$visitorEvent->undo($this->profile_post_id, [
					'multiplier'   => $this->getPreviousValue('message'),
					'owner_id'     => $this->profile_user_id,
					'content_type' => 'profile_post',
					'content_id'   => $this->profile_post_id
				], $this->User);
			}

			// Apply the new event
			$visitorEvent->apply($this->profile_post_id, [
				'multiplier'   => $this->message,
				'owner_id'     => $this->profile_user_id,
				'content_type' => 'profile_post',
				'content_id'   => $this->profile_post_id,
				'enableAlert'  => $this->isInsert()
			], $this->User);
		}
		// END VISITOR EVENT

		// BEGIN WALL EVENT
		if (!$this->isUpdate() || $this->isChanged('message'))
		{
			// Init the event
			$wallEvent = $eventTriggerRepo->getHandler('wall');

			if ($this->isUpdate())
			{
				// Undo the previous event
				$wallEvent->undo($this->profile_post_id, [
					'multiplier'     => $this->getPreviousValue('message'),
					'source_user_id' => $this->user_id,
					'content_type'   => 'profile_post',
					'content_id'     => $this->profile_post_id
				], $this->ProfileUser);
			}

			// Apply the new event
			$wallEvent->apply($this->profile_post_id, [
				'multiplier'     => $this->message,
				'source_user_id' => $this->user_id,
				'content_type'   => 'profile_post',
				'content_id'     => $this->profile_post_id,
				'enableAlert'    => $this->isInsert()
			], $this->ProfileUser);
		}
		// END WALL EVENT
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

		// BEGIN VISITOR EVENT
		$eventTriggerRepo->getHandler('visitor')
			->testUndo([
				'multiplier' => $this->message,
				'owner_id' => $this->profile_user_id
			], $this->User)
		;
		// END VISITOR EVENT

		// BEGIN WALL EVENT
		$eventTriggerRepo->getHandler('wall')
			->testUndo([
				'multiplier' => $this->message,
				'source_user_id' => $this->user_id
			], $this->ProfileUser)
		;
		// END WALL EVENT
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

		// BEGIN VISITOR EVENT
		$eventTriggerRepo->getHandler('visitor')
			->undo($this->profile_post_id, [
				'multiplier' => $this->message,
				'owner_id' => $this->profile_user_id,
				'content_type' => 'profile_post',
				'content_id' => $this->profile_post_id
			], $this->User)
		;
		// END VISITOR EVENT

		// BEGIN WALL EVENT
		$eventTriggerRepo->getHandler('wall')
			->undo($this->profile_post_id, [
				'multiplier' => $this->message,
				'source_user_id' => $this->user_id,
				'content_type' => 'profile_post',
				'content_id' => $this->profile_post_id
			], $this->ProfileUser)
		;
		// END WALL EVENT
	}
}