<?php

namespace XFMG\Job;

class MediaItemAction extends AbstractBatchUpdateAction
{
	protected function getColumn()
	{
		return 'media_id';
	}

	protected function getClassIdentifier()
	{
		return 'XFMG:MediaItem';
	}

	protected function applyInternalItemChange(\XF\Mvc\Entity\Entity $mediaItem)
	{
		/** @var \XFMG\Entity\MediaItem $mediaItem */

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->app->repository('XFMG:Media');

		if ($this->getActionValue('approve'))
		{
			$mediaItem->media_state = 'visible';
		}
		if ($this->getActionValue('unapprove'))
		{
			$mediaItem->media_state = 'moderated';
		}
		if ($this->getActionValue('soft_delete'))
		{
			$mediaItem->media_state = 'deleted';
		}
		if (!$this->getActionValue('remove_watermark')
			&& $this->getActionValue('add_watermark')
			&& $mediaItem->canAddWatermark(false)
		)
		{
			$tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var \XFMG\Service\Media\Watermarker $watermarker */
			$watermarker = $this->app->service('XFMG:Media\Watermarker', $mediaItem, $tempWatermark);
			$watermarker->watermark(false);
		}
		if (!$this->getActionValue('add_watermark')
			&& $this->getActionValue('remove_watermark')
			&& $mediaItem->canRemoveWatermark(false)
		)
		{
			/** @var \XFMG\Service\Media\Watermarker $watermarker */
			$watermarker = \XF::service('XFMG:Media\Watermarker', $mediaItem);
			$watermarker->unwatermark(false);
		}
	}

	protected function getTypePhrase()
	{
		return \XF::phrase('xfmg_media_items');
	}
}