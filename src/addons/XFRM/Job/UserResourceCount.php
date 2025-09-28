<?php

namespace XFRM\Job;

use XF\Job\AbstractRebuildJob;

class UserResourceCount extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFRM\Repository\ResourceItem $repo */
		$repo = $this->app->repository('XFRM:ResourceItem');
		$count = $repo->getUserResourceCount($id);

		$this->app->db()->update('xf_user', ['xfrm_resource_count' => $count], 'user_id = ?', $id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfrm_resource_counts');
	}
}