<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\EditHistory;
use XF\Import\Data\HasDeletionLogTrait;

class Comment extends AbstractEmulatedData
{
	use HasDeletionLogTrait;

	protected $loggedIp;

	/**
	 * @var EditHistory[]
	 */
	protected $editHistory = [];

	public function getImportType()
	{
		return 'xfmg_comment';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:Comment';
	}

	public function setLoggedIp($loggedIp)
	{
		$this->loggedIp = $loggedIp;
	}

	public function addHistory($oldId, EditHistory $history)
	{
		$this->editHistory[$oldId] = $history;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('username', $oldId);
	}

	protected function postSave($oldId, $newId)
	{
		$this->logIp($this->loggedIp, $this->comment_date);
		$this->insertStateRecord($this->comment_state, $this->comment_date);

		if ($this->editHistory)
		{
			foreach ($this->editHistory AS $oldHistoryId => $history)
			{
				$history->content_id = $newId;
				$history->log(false);
				$history->checkExisting(false);
				$history->useTransaction(false);

				$history->save($oldHistoryId);
			}
		}
	}
}