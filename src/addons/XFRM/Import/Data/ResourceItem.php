<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\HasDeletionLogTrait;

class ResourceItem extends AbstractEmulatedData
{
	use HasDeletionLogTrait;

	protected $iconPath;

	protected $watchers = [];

	/**
	 * @var ResourceVersion[]
	 */
	protected $versions;

	/**
	 * @var ResourceUpdate[]
	 */
	protected $updates;

	public function getImportType()
	{
		return 'resource';
	}

	protected function getEntityShortName()
	{
		return 'XFRM:ResourceItem';
	}

	public function addWatcher($userId, $emailSubscribe)
	{
		$this->watchers[$userId] = $emailSubscribe;
	}

	public function setCustomFields(array $customFields)
	{
		foreach ($customFields AS &$fieldValue)
		{
			if (is_string($fieldValue))
			{
				$fieldValue = $this->convertToUtf8($fieldValue);
			}
		}

		$this->custom_fields = $customFields;
	}

	public function setTeamMemberUserIds(array $ids)
	{
		foreach ($ids AS $k => $v)
		{
			$v = intval($v);
			if (!$v)
			{
				unset($ids[$k]);
			}
		}

		$ids = array_unique($ids);
		sort($ids, SORT_NUMERIC);

		$this->team_member_user_ids = $ids;
	}

	public function setIconPath($path)
	{
		$this->iconPath = $path;
	}

	public function addVersion($oldId, $version)
	{
		$this->versions[$oldId] = $version;
	}

	public function addUpdate($oldId, $update)
	{
		$this->updates[$oldId] = $update;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('username', $oldId);
		$this->forceNotEmpty('title', $oldId);
		$this->forceNotEmpty('tag_line', $oldId);
	}

	protected function postSave($oldId, $newId)
	{
		$this->insertStateRecord($this->resource_state, $this->resource_date);

		if ($this->custom_fields)
		{
			$this->insertCustomFieldValues('xf_rm_resource_field_value', 'resource_id', $newId, $this->custom_fields);
		}

		if ($this->team_member_user_ids)
		{
			$insert = [];

			foreach ($this->team_member_user_ids AS $userId)
			{
				$insert[] = [
					'resource_id' => $newId,
					'user_id' => $userId
				];
			}

			if ($insert)
			{
				$this->db()->insertBulk(
					'xf_rm_resource_team_member',
					$insert,
					false,
					false,
					'IGNORE'
				);
			}
		}

		if ($this->watchers)
		{
			$insert = [];

			foreach ($this->watchers AS $userId => $emailSubscribe)
			{
				$insert[] = [
					'user_id' => $userId,
					'resource_id' => $newId,
					'email_subscribe' => $emailSubscribe ? 1 : 0
				];
			}

			if ($insert)
			{
				$this->db()->insertBulk(
					'xf_rm_resource_watch',
					$insert,
					false,
					'email_subscribe = VALUES(email_subscribe)'
				);
			}
		}

		if ($this->versions)
		{
			foreach ($this->versions AS $oldVersionId => $version)
			{
				$version->resource_id = $newId;
				$version->useTransaction(false);

				$version->save($oldVersionId);
			}
		}

		if ($this->updates)
		{
			foreach ($this->updates AS $oldUpdateId => $update)
			{
				$update->resource_id = $newId;
				$update->useTransaction(false);

				$update->save($oldUpdateId);
			}
		}

		/** @var \XFRM\Entity\ResourceItem $resourceItem */
		$resourceItem = $this->em()->find('XFRM:ResourceItem', $newId);

		if (!$resourceItem)
		{
			return;
		}

		if ($this->iconPath)
		{
			if (file_exists($this->iconPath) && is_readable($this->iconPath))
			{
				\XF\Util\File::copyFileToAbstractedPath(
					$this->iconPath, $resourceItem->getAbstractedIconPath()
				);
				$resourceItem->icon_date = time();
			}
		}

		$resourceItem->saveIfChanged($null, false, false);

		// Note: Entity is called in the importer itself so beneficial to keep this cached.
		// The importer detaches the entity when it is finished.
	}
}