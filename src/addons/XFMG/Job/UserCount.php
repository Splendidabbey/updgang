<?php

namespace XFMG\Job;

use XF\Job\AbstractRebuildJob;

class UserCount extends AbstractRebuildJob
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
		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->app->repository('XFMG:Media');
		$mediaCount = $mediaRepo->getUserMediaCount($id);

		/** @var \XFMG\Repository\Album $albumRepo */
		$albumRepo = $this->app->repository('XFMG:Album');
		$albumCount = $albumRepo->getUserAlbumCount($id);

		$this->app->db()->update('xf_user', [
			'xfmg_media_count' => $mediaCount,
			'xfmg_album_count' => $albumCount
		], 'user_id = ?', $id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfmg_rebuild_user_counts');
	}
}