<?php

namespace XFRM\Job;

use XF\Job\AbstractRebuildJob;

class ResourceItem extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT resource_id
				FROM xf_rm_resource
				WHERE resource_id > ?
				ORDER BY resource_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $this->app->em()->find('XFRM:ResourceItem', $id);
		if ($resource)
		{
			$resource->rebuildCounters();
			$resource->save(false);
		}
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfrm_resources');
	}
}