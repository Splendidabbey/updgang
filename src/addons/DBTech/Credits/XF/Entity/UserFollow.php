<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XF\Entity\UserFollow
 */
class UserFollow extends XFCP_UserFollow
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('follow')
			->testApply([
				'owner_id' => $this->follow_user_id
			], $this->User)
		;

		$eventTriggerRepo->getHandler('followed')
			->testApply([
				'source_user_id' => $this->user_id
			], $this->FollowUser)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('follow')
			->apply($this->follow_user_id, [
				'owner_id'     => $this->follow_user_id,
				'content_type' => 'user',
				'content_id'   => $this->follow_user_id
			], $this->User)
		;

		$eventTriggerRepo->getHandler('followed')
			->apply($this->user_id, [
				'source_user_id' => $this->user_id,
				'content_type'   => 'user',
				'content_id'     => $this->follow_user_id
			], $this->FollowUser)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('follow')
			->testUndo([
				'owner_id' => $this->follow_user_id
			], $this->User)
		;

		$eventTriggerRepo->getHandler('followed')
			->testUndo([
				'source_user_id' => $this->user_id
			], $this->FollowUser)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		// Do parent stuff
		parent::_postDelete();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('follow')
			->undo($this->follow_user_id, [
				'owner_id'     => $this->follow_user_id,
				'content_type' => 'user',
				'content_id'   => $this->follow_user_id
			], $this->User)
		;

		$eventTriggerRepo->getHandler('followed')
			->undo($this->user_id, [
				'source_user_id' => $this->user_id,
				'content_type'   => 'user',
				'content_id'     => $this->follow_user_id
			], $this->FollowUser)
		;
	}
}