<?php

namespace XFMG\Cron;

class CleanUp
{
	public static function runDailyCleanUp()
	{
		$app = \XF::app();

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->pruneMediaViewLogs();
	}

	public static function runHourlyCleanUp()
	{
		$app = \XF::app();

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $app->repository('XFMG:Media');
		$mediaRepo->pruneTempMedia();
		$mediaRepo->pruneTempAttachmentExif();

		/** @var \XFMG\Repository\MediaNote $noteRepo */
		$noteRepo = $app->repository('XFMG:MediaNote');
		$noteRepo->pruneUnapprovedTags();
	}
}