<?php

namespace XFMG\Job;

use XF\Job\AbstractRebuildJob;

class SyncAttachmentMirror extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT attachment_id
				FROM xf_attachment
				WHERE attachment_id > ?
				ORDER BY attachment_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var \XFMG\XF\Entity\Attachment $attachment */
		$attachment = $this->app->em()->find('XF:Attachment', $id);
		if (!$attachment)
		{
			return;
		}

		$manager = $this->app->service('XFMG:Media\MirrorManager');
		$manager->syncMirrorState($attachment);
	}

	protected function getStatusType()
	{
		return \XF::phrase('xfmg_sync_attachment_media_mirroring');
	}
}