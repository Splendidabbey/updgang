<?php

namespace XFRM\Job;

use XF\Job\AbstractRebuildJob;

class Category extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT resource_category_id
				FROM xf_rm_category
				WHERE resource_category_id > ?
				ORDER BY resource_category_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFRM\Entity\Category $category */
		$category = $this->app->em()->find('XFRM:Category', $id);
		if ($category)
		{
			$category->rebuildCounters();
			$category->save();
		}
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfrm_resource_categories');
	}
}