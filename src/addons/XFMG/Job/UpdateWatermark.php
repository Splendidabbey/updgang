<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;
use XFMG\Entity\MediaItem;

class UpdateWatermark extends AbstractJob
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
		$options = $this->app->options();
		$mediaRepo = $this->app->repository('XFMG:Media');

		if ($options->xfmgWatermarking['enabled'] && $options->xfmgWatermarking['watermark_hash'])
		{
			$watermarkPath = $mediaRepo->getAbstractedWatermarkPath($options->xfmgWatermarking['watermark_hash']);
			if (!$this->app->fs()->has($watermarkPath))
			{
				return $this->complete();
			}
			$tempWatermark = \XF\Util\File::copyAbstractedPathToTempFile($watermarkPath);
		}
		else
		{
			return $this->complete();
		}

		$ids = $db->fetchAllColumn($db->limit(
			"
				SELECT media_id
				FROM xf_mg_media_item
				WHERE media_id > ?
				AND media_type = 'image'
				AND watermarked = 1
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
			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}

			$this->data['start'] = $id;

			/** @var MediaItem $mediaItem */
			$mediaItem = $em->getFinder('XFMG:MediaItem')
				->where('media_id', $id)
				->with('Attachment.Data', true)
				->fetchOne();

			if (!$mediaItem)
			{
				continue;
			}

			/** @var \XFMG\Service\Media\Watermarker $watermarker */
			$watermarker = $this->app->service('XFMG:Media\Watermarker', $mediaItem, $tempWatermark);

			if ($watermarker->watermark())
			{
				$done++;
			}
		}

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 1000);

		return $this->resume();
	}

	protected function getImageManager()
	{
		if ($this->imageManager !== null)
		{
			return $this->imageManager;
		}
		$this->imageManager = $this->app->imageManager();
		return $this->imageManager;
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('updating');
		$typePhrase = \XF::phrase('xfmg_media_watermarks');
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