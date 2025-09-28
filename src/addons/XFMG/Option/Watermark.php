<?php

namespace XFMG\Option;

use XF\Util\File;

class Watermark extends \XF\Option\AbstractOption
{
	public static function verifyOption(array &$values, \XF\Entity\Option $option)
	{
		if ($option->isInsert())
		{
			return true;
		}

		if (empty($values['enabled']))
		{
			return true;
		}

		$mediaRepo = \XF::repository('XFMG:Media');

		$upload = \XF::app()->request()->getFile('watermark', false);
		if ($upload)
		{
			self::deleteWatermark($option->getOptionValue()['watermark_hash']);

			$tempFile = $upload->getTempFile();
			$hash = md5_file($tempFile);
			$values['watermark_hash'] = $hash;

			$watermarkPath = $mediaRepo->getAbstractedWatermarkPath($hash);
			File::copyFileToAbstractedPath($tempFile, $watermarkPath);
		}
		else if (!empty($values['watermark_hash']))
		{
			return true;
		}
		else
		{
			$option->error(\XF::phrase('xfmg_error_while_uploading_watermark_image'));
			return false;
		}

		return true;
	}

	protected static function deleteWatermark($hash)
	{
		$mediaRepo = \XF::repository('XFMG:Media');
		$watermarkPath = $mediaRepo->getAbstractedWatermarkPath($hash);
		File::deleteFromAbstractedPath($watermarkPath);
	}
}