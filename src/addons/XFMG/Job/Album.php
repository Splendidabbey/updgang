<?php

namespace XFMG\Job;

use XF\Job\AbstractRebuildJob;

class Album extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT album_id
				FROM xf_mg_album
				WHERE album_id > ?
				ORDER BY album_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFMG\Entity\Album $album */
		$album = $this->app->em()->find('XFMG:Album', $id);
		if ($album)
		{
			$album->rebuildCounters();
			$album->save();
		}
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfmg_albums');
	}
}