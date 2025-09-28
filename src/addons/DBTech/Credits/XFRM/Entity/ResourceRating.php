<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFRM\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XFRM\Entity\ResourceRating
 */
class ResourceRating extends XFCP_ResourceRating
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if (!$this->user_id || $this->isUpdate())
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('resourcerate')
			->testApply([
				'multiplier' => $this->rating,
				'owner_id' => $this->Resource->user_id
			], $this->User)
		;

		if ($this->Resource->user_id != $this->user_id)
		{
			$eventTriggerRepo->getHandler('resourcerated')
				->testApply([
					'multiplier' => $this->rating,
					'source_user_id' => $this->user_id
				], $this->Resource->User)
			;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		if (!$this->user_id || $this->isUpdate())
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('resourcerate')
			->apply($this->resource_rating_id, [
				'multiplier' => $this->rating,
				'owner_id' => $this->Resource->user_id,
				'content_type' => 'resource_rating',
				'content_id' => $this->resource_rating_id
			], $this->User)
		;

		if ($this->Resource->user_id != $this->user_id)
		{
			$eventTriggerRepo->getHandler('resourcerated')
				->apply($this->resource_rating_id, [
					'multiplier' => $this->rating,
					'source_user_id' => $this->user_id,
					'content_type' => 'resource_rating',
					'content_id' => $this->resource_rating_id
				], $this->Resource->User)
			;
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

		$eventTriggerRepo->getHandler('resourcerate')
			->testUndo([
				'multiplier' => $this->rating,
				'owner_id' => $this->Resource->user_id
			], $this->User)
		;

		if ($this->Resource->user_id != $this->user_id)
		{
			$eventTriggerRepo->getHandler('resourcerated')
				->testUndo([
					'multiplier' => $this->rating,
					'source_user_id' => $this->user_id
				], $this->Resource->User)
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

		if (!$this->user_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		$eventTriggerRepo->getHandler('resourcerate')
			->undo($this->resource_rating_id, [
				'multiplier' => $this->rating,
				'owner_id' => $this->Resource->user_id,
				'content_type' => 'resource_rating',
				'content_id' => $this->resource_rating_id
			], $this->User)
		;

		if ($this->Resource->user_id != $this->user_id)
		{
			$eventTriggerRepo->getHandler('resourcerated')
				->undo($this->resource_rating_id, [
					'multiplier' => $this->rating,
					'source_user_id' => $this->user_id,
					'content_type' => 'resource_rating',
					'content_id' => $this->resource_rating_id
				], $this->Resource->User)
			;
		}
	}
}