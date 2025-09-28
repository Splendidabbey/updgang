<?php

namespace XFMG\Job;

use XF\Job\AbstractRebuildJob;

class MediaItem extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT media_id
				FROM xf_mg_media_item
				WHERE media_id > ?
				ORDER BY media_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFMG\Entity\MediaItem $mediaItem */
		$mediaItem = $this->app->em()->find('XFMG:MediaItem', $id);
		if ($mediaItem)
		{
			$mediaItem->rebuildCounters();
			$mediaItem->save();
		}
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfmg_media_items');
	}
}