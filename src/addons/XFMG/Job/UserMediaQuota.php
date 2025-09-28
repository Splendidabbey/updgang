<?php

namespace XFMG\Job;

use XF\Db\Schema\Alter;
use XF\Job\AbstractJob;
use XFMG\Entity\MediaItem;
use XFMG\XF\Entity\User;

class UserMediaQuota extends AbstractJob
{
	protected $defaultData = [
		'start' => 0,
		'batch' => 100,
		'resetField' => false
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$db = $this->app->db();
		$em = $this->app->em();

		$ids = $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				AND (xfmg_media_quota > 0 OR xfmg_media_count > 0)
				ORDER BY user_id
			", $this->data['batch']
		), $this->data['start']);
		if (!$ids)
		{
			if ($this->data['resetField'])
			{
				$sm = \XF::db()->getSchemaManager();

				if ($sm->columnExists('xf_user', 'xengallery_media_quota'))
				{
					$sm->alterTable('xf_user', function(Alter $table)
					{
						$table->changeColumn('xengallery_media_quota', 'int');
					});
				}
				else
				{
					$sm->alterTable('xf_user', function(Alter $table)
					{
						$table->changeColumn('xfmg_media_quota', 'int');
					});
				}
			}
			return $this->complete();
		}

		$done = 0;

		foreach ($ids AS $id)
		{
			$this->data['start'] = $id;

			/** @var User $user */
			$user = $em->find('XF:User', $id);

			if ($user)
			{
				$user->rebuildMediaQuota();
				$user->save();
			}

			$done++;

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 1000);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('xfmg_user_media_quotas');
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return true;
	}
}