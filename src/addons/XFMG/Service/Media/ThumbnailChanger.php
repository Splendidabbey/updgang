<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;
use XFMG\Entity\MediaItem;

class ThumbnailChanger extends AbstractService
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $fileName;

	protected $width;

	protected $height;

	protected $type;

	protected $error;

	protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

	public function __construct(\XF\App $app, MediaItem $mediaItem)
	{
		parent::__construct($app);

		$this->setMediaItem($mediaItem);
	}

	protected function setMediaItem(MediaItem $mediaItem)
	{
		$this->mediaItem = $mediaItem;
	}

	public function useDefaultThumbnail()
	{
		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');
		$mediaItem = $this->mediaItem;

		$success = false;
		$posterSuccess = false;

		switch ($mediaItem->media_type)
		{
			case 'image':
				$success = $mediaRepo->rebuildImageThumb($mediaItem, false);
				break;

			case 'audio':
			case 'video':
				$success = $mediaRepo->rebuildFFmpegThumb($mediaItem, false);
				break;

			case 'embed':
				$success = $mediaRepo->rebuildEmbedThumb($mediaItem, false);
				break;
		}

		if ($success)
		{
			$mediaItem->thumbnail_date = time();
			$mediaItem->custom_thumbnail_date = 0;
		}
		else
		{
			$mediaItem->thumbnail_date = 0;
			$mediaItem->custom_thumbnail_date = 0;
		}

		switch ($mediaItem->media_type)
		{
			case 'audio':
			case 'video':
				$posterSuccess = $mediaRepo->rebuildFFmpegPoster($mediaItem, false);
				break;
		}

		if ($posterSuccess)
		{
			$mediaItem->poster_date = time();
		}
		else
		{
			$mediaItem->poster_date = 0;
		}

		return $mediaItem->saveIfChanged();
	}


	public function getError()
	{
		return $this->error;
	}

	public function setImage($fileName)
	{
		if (!$this->validateImage($fileName, $error))
		{
			$this->error = $error;
			$this->fileName = null;
			return false;
		}

		$this->fileName = $fileName;
		return true;
	}

	public function setThumbnailFromUpload(\XF\Http\Upload $upload)
	{
		$upload->requireImage();

		if (!$upload->isValid($errors))
		{
			$this->error = reset($errors);
			return false;
		}

		return $this->setImage($upload->getTempFile());
	}

	public function validateImage($fileName, &$error = null)
	{
		$error = null;

		if (!file_exists($fileName))
		{
			throw new \InvalidArgumentException("Invalid file '$fileName' passed to thumbnail changer service");
		}
		if (!is_readable($fileName))
		{
			throw new \InvalidArgumentException("'$fileName' passed to thumbnail changer service is not readable");
		}

		$imageInfo = filesize($fileName) ? getimagesize($fileName) : false;
		if (!$imageInfo)
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$type = $imageInfo[2];
		if (!in_array($type, $this->allowedTypes))
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];

		if (!$this->app->imageManager()->canResize($width, $height))
		{
			$error = \XF::phrase('uploaded_image_is_too_big');
			return false;
		}

		$this->width = $width;
		$this->height = $height;
		$this->type = $type;

		return true;
	}

	public function updateThumbnail()
	{
		if (!$this->fileName)
		{
			throw new \LogicException("No source file for thumbnail set");
		}

		$mediaItem = $this->mediaItem;
		$abstractedPath = $mediaItem->getAbstractedCustomThumbnailPath();

		/** @var ThumbnailGenerator $generator */
		$generator = $this->service('XFMG:Media\ThumbnailGenerator');

		$success = $generator->getTempThumbnailFromImage(
			$this->fileName, $abstractedPath, $this->width, $this->height
		);

		if (!$success)
		{
			return false;
		}

		$posterSuccess = $generator->getTempPosterFromImage(
			$this->fileName, $mediaItem->getAbstractedPosterPath()
		);

		if ($posterSuccess)
		{
			$mediaItem->poster_date = time();
		}

		$abstractedOriginalPath = $mediaItem->getAbstractedCustomThumbnailOriginalPath();
		\XF\Util\File::copyFileToAbstractedPath($this->fileName, $abstractedOriginalPath);

		$mediaItem->custom_thumbnail_date = time();
		$mediaItem->save();

		return true;
	}
}