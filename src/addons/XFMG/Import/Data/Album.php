<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\HasDeletionLogTrait;

class Album extends AbstractEmulatedData
{
	use HasDeletionLogTrait, HasWatchTrait;

	protected $loggedIp;

	public function getImportType()
	{
		return 'xfmg_album';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:Album';
	}

	public function setLoggedIp($loggedIp)
	{
		$this->loggedIp = $loggedIp;
	}

	public function addSharedUserView($userId)
	{
		$users = $this->view_users;
		$users[] = $userId;
		$this->view_users = $users;
	}

	public function addSharedUserAdd($userId)
	{
		$users = $this->add_users;
		$users[] = $userId;
		$this->add_users = $users;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('username', $oldId);
		$this->forceNotEmpty('title', $oldId);

		if (!$this->album_hash)
		{
			$this->album_hash = md5(microtime(true) . \XF::generateRandomString(8, true));
		}
	}

	protected function postSave($oldId, $newId)
	{
		$this->logIp($this->loggedIp, $this->create_date);
		$this->insertStateRecord($this->album_state, $this->create_date);

		$db = $this->db();

		foreach ($this->view_users AS $userId)
		{
			$db->insert('xf_mg_shared_map_view', [
				'album_id' => $newId,
				'user_id' => $userId
			]);
		}

		foreach ($this->add_users AS $userId)
		{
			$db->insert('xf_mg_shared_map_add', [
				'album_id' => $newId,
				'user_id' => $userId
			]);
		}

		$this->insertWatchers($newId);
	}
}