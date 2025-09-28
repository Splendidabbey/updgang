<?php

namespace XFMG\Option;

class Ffmpeg extends \XF\Option\AbstractOption
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

		/** @var \XFMG\Validator\Ffmpeg $validator */
		$validator = \XF::app()->validator('XFMG:Ffmpeg');
		$validator->setOption('verify_can_transcode', !empty($values['transcode']));

		$ffmpegPath = $validator->coerceValue($values['ffmpegPath']);

		if (!$validator->isValid($ffmpegPath, $errorKey))
		{
			$option->error($validator->getPrintableErrorValue($errorKey), $option->option_id);
			return false;
		}

		if (!empty($values['transcode']))
		{
			try
			{
				if (!is_file($values['phpPath']) && !is_executable($values['phpPath']))
				{
					$option->error(\XF::phrase('xfmg_php_binary_path_could_not_be_verified'));
					return false;
				}
			}
			catch (\Exception $e)
			{
				$option->error($e->getMessage(), $option->option_id);
				return false;
			}
		}

		return true;
	}
}