<?php

namespace XFRM\Import\Importer;

use XF\Import\Importer\AbstractAddOnImporter;

abstract class AbstractRMImporter extends AbstractAddOnImporter
{
	protected function isForumType($importType)
	{
		if ($importType === 'resource')
		{
			return false;
		}

		return (strpos($importType, 'resource_') !== 0);
	}

	public function canRetainIds()
	{
		$db = $this->app->db();

		$maxResourceId = $db->fetchOne("SELECT MAX(resource_id) FROM xf_rm_resource");
		if ($maxResourceId)
		{
			return false;
		}

		return true;
	}

	public function resetDataForRetainIds()
	{
		// category 1 is created by default in the installer so we need to remove that if retaining IDs
		$category = $this->em()->find('XFRM:Category', 1);
		if ($category)
		{
			$category->delete();
		}
	}

	public function getFinalizeJobs(array $stepsRun)
	{
		$jobs = [];

		$jobs[] = 'XFRM:Category';
		$jobs[] = 'XFRM:ResourceItem';
		$jobs[] = 'XFRM:UserResourceCount';
		$jobs[] = 'XF:PermissionRebuild';

		return $jobs;
	}
}