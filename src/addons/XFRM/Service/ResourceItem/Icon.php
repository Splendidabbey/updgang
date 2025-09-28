<?php

namespace XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Icon extends \XF\Service\AbstractService
{
	/**
	 * @var \XFRM\Entity\ResourceItem
	 */
	protected $resource;

	protected $logIp = true;

	protected $fileName;

	protected $width;

	protected $height;

	protected $type;

	protected $error = null;

	protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];


	public function __construct(\XF\App $app, ResourceItem $resource)
	{
		parent::__construct($app);
		$this->resource = $resource;
	}

	public function getResource()
	{
		return $this->resource;
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function getError()
	{
		return $this->error;
	}

	public function setImage($fileName)
	{
		if (!$this->validateImageAsIcon($fileName, $error))
		{
			$this->error = $error;
			$this->fileName = null;
			return false;
		}

		$this->fileName = $fileName;
		return true;
	}

	public function setImageFromUpload(\XF\Http\Upload $upload)
	{
		$upload->requireImage();

		if (!$upload->isValid($errors))
		{
			$this->error = reset($errors);
			return false;
		}

		return $this->setImage($upload->getTempFile());
	}

	public function validateImageAsIcon($fileName, &$error = null)
	{
		$error = null;

		if (!file_exists($fileName))
		{
			throw new \InvalidArgumentException("Invalid file '$fileName' passed to icon service");
		}
		if (!is_readable($fileName))
		{
			throw new \InvalidArgumentException("'$fileName' passed to icon service is not readable");
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

	public function updateIcon()
	{
		if (!$this->fileName)
		{
			throw new \LogicException("No source file for icon set");
		}

		$imageManager = $this->app->imageManager();
		$targetSize = $this->app->container('xfrmIconSizeMap')['m'];
		$outputFile = null;

		if ($this->width != $targetSize || $this->height != $targetSize)
		{
			$image = $imageManager->imageFromFile($this->fileName);
			if (!$image)
			{
				return false;
			}

			$image->resizeAndCrop($targetSize);

			$newTempFile = \XF\Util\File::getTempFile();
			if ($newTempFile && $image->save($newTempFile))
			{
				$outputFile = $newTempFile;
			}
		}
		else
		{
			$outputFile = $this->fileName;
		}

		if (!$outputFile)
		{
			throw new \RuntimeException("Failed to save image to temporary file; check internal_data/data permissions");
		}

		$dataFile = $this->resource->getAbstractedIconPath();
		\XF\Util\File::copyFileToAbstractedPath($outputFile, $dataFile);

		$this->resource->icon_date = \XF::$time;
		$this->resource->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('update', $ip);
		}

		return true;
	}

	public function deleteIcon()
	{
		$this->deleteIconFiles();

		$this->resource->icon_date = 0;
		$this->resource->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('delete', $ip);
		}

		return true;
	}

	public function deleteIconForResourceDelete()
	{
		$this->deleteIconFiles();

		return true;
	}

	protected function deleteIconFiles()
	{
		if ($this->resource->icon_date)
		{
			\XF\Util\File::deleteFromAbstractedPath($this->resource->getAbstractedIconPath());
		}
	}

	protected function writeIpLog($action, $ip)
	{
		$resource = $this->resource;

		/** @var \XF\Repository\Ip $ipRepo */
		$ipRepo = $this->repository('XF:Ip');
		$ipRepo->logIp(\XF::visitor()->user_id, $ip, 'resource', $resource->resource_id, 'avatar_' . $action);
	}
}