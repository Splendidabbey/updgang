<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class Category extends AbstractEmulatedData
{
	protected $watchers = [];

	public function getImportType()
	{
		return 'resource_category';
	}

	protected function getEntityShortName()
	{
		return 'XFRM:Category';
	}

	public function addWatcher($userId, array $params)
	{
		$this->watchers[$userId] = $params;
	}

	protected function preSave($oldId)
	{
		$this->forceNotEmpty('title', $oldId);
	}

	protected function postSave($oldId, $newId)
	{
		\XF::runOnce('xfrmCategoryImport', function()
		{
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XFRM:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
		});

		if ($this->watchers)
		{
			$insert = [];

			foreach ($this->watchers AS $userId => $params)
			{
				$insert[] = [
					'user_id' => $userId,
					'resource_category_id' => $newId,
					'notify_on' => $params['notify_on'],
					'send_alert' => $params['send_alert'],
					'send_email' => $params['send_email'],
					'include_children' => $params['include_children']
				];
			}

			if ($insert)
			{
				$this->db()->insertBulk(
					'xf_rm_category_watch',
					$insert,
					false,
					'
						notify_on = VALUES(notify_on),
						send_alert = VALUES(send_alert),
						send_email = VALUES(send_email), 
						include_children = VALUES(include_children)
					'
				);
			}
		}
	}
}