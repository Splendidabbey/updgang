<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFRM\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XFRM\Entity\ResourceUpdate
 */
class ResourceUpdate extends XFCP_ResourceUpdate
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if ($this->isInsert() && !$this->isDescription())
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$eventTriggerRepo->getHandler('resourceupdate')
				->testApply([], $this->Resource->User)
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

		if ($this->isInsert() && !$this->isDescription())
		{
			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
			$eventTriggerRepo->getHandler('resourceupdate')
				->apply($this->resource_update_id, [
					'content_type' => 'resource_update',
					'content_id' => $this->resource_update_id
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

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$eventTriggerRepo->getHandler('resourceupdate')
			->testUndo([], $this->Resource->User)
		;
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$eventTriggerRepo->getHandler('resourceupdate')
			->undo($this->resource_update_id, [
				'content_type' => 'resource_update',
				'content_id' => $this->resource_update_id
			], $this->Resource->User)
		;
	}
}