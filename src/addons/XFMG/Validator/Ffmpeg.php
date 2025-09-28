<?php

namespace XFMG\Validator;

class Ffmpeg extends \XF\Validator\AbstractValidator
{
	protected $options = [
		'verify_executable' => true,
		'verify_can_transcode' => true
	];

	public function isValid($value, &$errorKey = null)
	{
		if (!$value)
		{
			$errorKey = 'path_error';
			return false;
		}

		if (!file_exists($value))
		{
			$errorKey = 'path_find_error';
			return false;
		}

		if (!$this->getOption('verify_executable'))
		{
			return true;
		}

		$class = '\XFMG\Ffmpeg\Runner';
		$class = \XF::extendClass($class);

		/** @var \XFMG\Ffmpeg\Runner $ffmpeg */
		$ffmpeg = new $class($value, false);

		$versionYear = $ffmpeg->getVersionYear();
		$encoders = $ffmpeg->getEncoders(['libvo_aacenc', 'aac', 'libx264', 'png']);

		if ($versionYear !== null && $versionYear < 2013)
		{
			$errorKey = 'version_error';
			return false;
		}

		if ($encoders)
		{
			$required = ['png'];
			if ($this->getOption('verify_can_transcode'))
			{
				$required[] = 'libx264';
			}
			$available = array_keys($encoders);

			$notAvailable = array_diff($required, $available);
			if ($notAvailable)
			{
				$errorKey = 'encoder_missing_' . reset($notAvailable);
				return false;
			}
			else
			{
				// FFmpeg 3.0 introduces a native AAC encoder, making libvo_aacenc no longer present.
				// If the other required encoders are available, make sure we have at least one of the AAC encoders.
				$validAacEncoders = ['aac', 'libvo_aacenc'];
				if (!array_intersect($available, $validAacEncoders))
				{
					$errorKey = 'encoder_missing_audio';
					return false;
				}
			}
		}

		if ($versionYear === null && !$encoders)
		{
			$errorKey = 'execute_error';
			return false;
		}

		return true;
	}

	public function coerceValue($value)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			$value = str_replace('/', '\\', $value);
		}

		return trim($value);
	}

	public function getPrintableErrorValue($errorKey)
	{
		switch ($errorKey)
		{
			case 'path_error':
				return \XF::phrase('xfmg_path_provided_was_not_valid');
				break;

			case 'path_find_error':
				return \XF::phrase('xfmg_could_not_find_ffmpeg_at_path_specified');
				break;

			case 'version_error':
				return \XF::phrase('xfmg_ffmpeg_version_requirement');
				break;

			case 'encoder_missing_png':
				return \XF::phrase('xfmg_png_encoder_is_required_for_generating_thumbnails');
				break;

			case 'encoder_missing_libx264':
				return \XF::phrase('xfmg_libx264_encoder_is_required_for_transcoding_video');
				break;

			case 'encoder_missing_audio':
				return \XF::phrase('xfmg_aac_or_libvo_aacenc_encoder_is_required_for_transcoding_audio');
				break;

			case 'execute_error':
				return \XF::phrase('xfmg_could_not_execute_ffmpeg_at_path_specified');
				break;
		}
	}
}