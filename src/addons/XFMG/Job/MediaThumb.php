<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;
use XFMG\Entity\MediaItem;

class MediaThumb extends AbstractJob
{
	/**
	 * @var \XF\Image\Manager
	 */
	protected $imageManager;

	protected $defaultData = [
		'start' => 0,
		'batch' => 100
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$db = $this->app->db();
		$em = $this->app->em();

		$ids = $db->fetchAllColumn($db->limit(
			"
				SELECT media_id
				FROM xf_mg_media_item
				WHERE media_id > ?
				ORDER BY media_id
			", $this->data['batch']
		), $this->data['start']);
		if (!$ids)
		{
			return $this->complete();
		}

		$done = 0;

		foreach ($ids AS $id)
		{
			$this->data['start'] = $id;

			/** @var MediaItem $mediaItem */
			$mediaItem = $em->getFinder('XFMG:MediaItem')
				->where('media_id', $id)
				->with('Attachment')
				->with('Attachment.Data')
				->fetchOne();

			if ($mediaItem)
			{
				$mediaItem->rebuildThumbnail();
			}

			$done++;

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}

		\XF\Util\File::cleanUpTempFiles();

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 1000);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('xfmg_media_thumbnails');
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