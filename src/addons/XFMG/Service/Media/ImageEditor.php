<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;
use XF\Util\File;

class ImageEditor extends AbstractService
{
	protected $mediaItem;
	protected $cropData;

	public function __construct(\XF\App $app, \XFMG\Entity\MediaItem $mediaItem, array $cropData)
	{
		parent::__construct($app);
		$this->mediaItem = $mediaItem;
		$this->cropData = $cropData;
	}

	public function validateCropData()
	{
		$canEditImage = false;
		$cropData = $this->cropData;

		if ($cropData['scaleX'] === -1)
		{
			$canEditImage = true;
		}
		if ($cropData['scaleY'] === -1)
		{
			$canEditImage = true;
		}
		if ($cropData['rotate'] !== 0)
		{
			$canEditImage = true;
		}
		if ($cropData['width'] > 0 && $cropData['height'] > 0)
		{
			$canEditImage = true;
		}

		return $canEditImage;
	}

	protected function _apply()
	{
		$canApply = $this->validateCropData();
		if (!$canApply)
		{
			throw new \InvalidArgumentException(\XF::phrase('xfmg_crop_data_provided_indicates_there_no_valid_manipulations_to_apply'));
		}

		$cropData = $this->cropData;
		$mediaItem = $this->mediaItem;

		if ($mediaItem->watermarked)
		{
			$abstractedPath = $mediaItem->getOriginalAbstractedDataPath();
		}
		else
		{
			$abstractedPath = $mediaItem->getAbstractedDataPath();
		}
		$tempFile = File::copyAbstractedPathToTempFile($abstractedPath);

		$imageManager = $this->app->imageManager();
		$image = $imageManager->imageFromFile($tempFile);

		$x = $cropData['x'];
		$y = $cropData['y'];

		if ($cropData['scaleX'] === -1)
		{
			$image->flip($image::FLIP_HORIZONTAL);
		}
		if ($cropData['scaleY'] === -1)
		{
			$image->flip($image::FLIP_VERTICAL);
		}
		if ($cropData['rotate'] !== 0)
		{
			$width = $image->getWidth();
			$height = $image->getHeight();

			$image->rotate($cropData['rotate']);

			$rotatedWidth = $image->getWidth();
			$rotatedHeight = $image->getHeight();

			$diffX = $rotatedWidth - $width;
			$diffY = $rotatedHeight - $height;

			$x = ($diffX / -2) + $x;
			$y = ($diffY / -2) + $y;
		}
		if ($cropData['width'] > 0 && $cropData['height'] > 0)
		{
			$image->crop(
				$cropData['width'],
				$cropData['height'],
				$x,
				$y
			);
		}

		$image->save($tempFile);

		return $tempFile;
	}

	/**
	 * Applies the applicable manipulations and returns the resulting temp file as a data URI for previewing.
	 *
	 * @return string
	 */
	public function preview()
	{
		$tempFile = $this->_apply();
		$imageType = pathinfo($tempFile, PATHINFO_EXTENSION);
		$data = file_get_contents($tempFile);

		return 'data:image/' . $imageType . ';base64,' . base64_encode($data);
	}

	public function save()
	{
		$sourceFile = $this->_apply();

		$mediaItem = $this->mediaItem;
		$watermark = false;

		// Before watermarking again (if needed), copy new un-watermarked image
		if ($mediaItem->watermarked)
		{
			$originalPath = $mediaItem->getOriginalAbstractedDataPath();
			File::copyFileToAbstractedPath($sourceFile, $originalPath);
			$watermark = true;
		}

		$attachData = $mediaItem->Attachment->Data;
		$fileWrapper = new \XF\FileWrapper($sourceFile, $attachData->filename);

		/** @var \XF\Service\Attachment\Preparer $attachmentPreparer */
		$attachmentPreparer = $this->service('XF:Attachment\Preparer');
		$attachmentPreparer->updateDataFromFile($attachData, $fileWrapper);

		if ($mediaItem->thumbnail_date)
		{
			$imageManager = $this->app->imageManager();

			if ($imageManager->canResize($attachData->width, $attachData->height))
			{
				$generatorService = $this->service('XFMG:Media\ThumbnailGenerator');
				$tempThumb = $generatorService->generateThumbnailFromFile($sourceFile);

				if ($tempThumb)
				{
					$thumbPath = $mediaItem->getAbstractedThumbnailPath();
					File::copyFileToAbstractedPath($tempThumb, $thumbPath);
					$mediaItem->thumbnail_date = time();
				}
			}
		}

		$mediaItem->last_edit_date = time();
		if (!$mediaItem->save(false))
		{
			return false;
		}

		if ($watermark && $mediaItem->canAddWatermark(false))
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');
			$tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var \XFMG\Service\Media\Watermarker $watermarker */
			$watermarker = $this->service('XFMG:Media\Watermarker', $mediaItem, $tempWatermark);
			$watermarker->watermark();
		}

		if ($mediaItem->album_id)
		{
			$album = $mediaItem->Album;
			$album->rebuildAlbumThumbnail();
		}

		return true;
	}
}