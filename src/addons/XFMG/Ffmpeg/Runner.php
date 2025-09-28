<?php

namespace XFMG\Ffmpeg;

class Runner
{
	protected $ffmpegPath;

	protected $fileName;

	protected $type;

	public function __construct($ffmpegPath, $validatePath = true)
	{
		$this->setFfmpegPath($ffmpegPath, $validatePath);
	}

	protected function setFfmpegPath($ffmpegPath, $validatePath)
	{
		if ($validatePath)
		{
			/** @var \XFMG\Validator\Ffmpeg $validator */
			$validator = \XF::app()->validator('XFMG:Ffmpeg');
			$validator->setOption('verify_executable', false);

			$ffmpegPath = $validator->coerceValue($ffmpegPath);
			if (!$validator->isValid($ffmpegPath, $errorKey))
			{
				throw new \LogicException($validator->getPrintableErrorValue($errorKey));
			}
		}

		$this->ffmpegPath = $ffmpegPath;
	}

	public function setFileName($fileName)
	{
		if (file_exists($fileName) && is_file($fileName) && is_readable($fileName))
		{
			$this->fileName = $fileName;
		}
		else
		{
			$this->filename = null;
			throw new \InvalidArgumentException("File '$fileName' does not exist or cannot be read");
		}
	}

	public function setType($type)
	{
		switch ($type)
		{
			case 'video':
			case 'audio':
				$this->type = $type;
				break;
			case 'default':
				throw new \InvalidArgumentException("Type '$type' is not valid for Ffmpeg processing");
		}
	}

	public function getFileName()
	{
		return $this->fileName;
	}

	public function getVersionYear()
	{
		$output = $this->run('-L');

		foreach ($output AS $line)
		{
			$line = trim($line);
			if (preg_match('/\(c\)\s\d{4}-(\d{4})/is', $line, $matches))
			{
				if (isset($matches[1]))
				{
					return $matches[1];
				}
			}
		}

		return null;
	}

	public function getEncoders($toCheck)
	{
		$output = $this->run('-encoders');

		$encoders = [];

		if (is_array($toCheck))
		{
			$toCheck = implode('|', $toCheck);
			$returnSingle = false;
		}
		else
		{
			$returnSingle = true;
		}

		foreach ($output AS $line)
		{
			$line = trim($line);

			if (preg_match("/(?:[A-Z]|\.){6}\s($toCheck)\b/m", $line, $matches))
			{
				if (!$returnSingle)
				{
					$encoders[$matches[1]] = true;
				}
				else
				{
					return true;
				}
			}
		}

		return $returnSingle ? false : $encoders;
	}

	public function getVideoInfo(&$return = null)
	{
		return $this->run("-i {file} 2>&1", array('file' => $this->fileName), $return);
	}

	public function getCodec($type)
	{
		$output = $this->getVideoInfo();
		foreach ($output AS $line)
		{
			$line = trim($line);
			if ($line && preg_match("/Stream\s+#(\d+:\d+(?:\([^)]+\))?):\s*(?:{$type}):\s*([^,\s]+)(?:,|\s|$)/i", $line, $match))
			{
				if (isset($match[2]))
				{
					return $match[2];
				}
			}
		}

		return null;
	}

	public function getKeyFrame()
	{
		if (!$this->type)
		{
			throw new \InvalidArgumentException('Cannot get a key frame from a file without knowing its type');
		}

		$newTempFile = \XF\Util\File::getTempFile();
		if (!$newTempFile)
		{
			return null;
		}

		$seekString = '';
		if ($this->type === 'video')
		{
			// Get a frame from somewhere in the first tenth of the video.
			// But avoid long seeks on longer videos by restricting it to the first 10 seconds.
			$duration = $this->getVideoDuration();
			$seek = min(10, intval(round($duration / 10)));

			$seekString = "-ss $seek";
		}

		if ($this->supportsThumbnailFilter())
		{
			$this->run("$seekString -i {input} -an -vf thumbnail=50 -vsync 0 -f image2pipe -vcodec png -frames:v 1 -y {output}", [
				'input' => $this->fileName,
				'output' => $newTempFile
			], $ret);
		}
		else
		{
			$this->run("$seekString -i {input} -vframes 1 -f image2pipe -y {output}", [
				'input' => $this->fileName,
				'output' => $newTempFile
			], $ret);
		}

		if ($ret === 0)
		{
			return $newTempFile;
		}
		else
		{
			return null;
		}
	}

	public function getVideoDuration()
	{
		$output = $this->getVideoInfo();
		foreach ($output AS $line)
		{
			$line = trim($line);
			if ($line && preg_match('/Duration: (\d+):(\d+):(\d+)/s', $line, $match))
			{
				array_shift($match);
				list($hours, $minutes, $seconds) = $match;

				$duration = 0;

				$duration += $hours * 60 * 60;
				$duration += $minutes * 60;
				$duration += $seconds;

				return $duration;
			}
		}

		return null;
	}

	public function supportsThumbnailFilter()
	{
		$output = $this->run('-filters');

		foreach ($output AS $line)
		{
			$line = trim($line);
			if (preg_match('/^thumbnail\b/im', $line))
			{
				return true;
			}
			else if (preg_match("/(?:[a-z]|\.){3}\s(?:thumbnail)\b/im", $line))
			{
				return true;
			}
		}

		return false;
	}

	public function transcode()
	{
		if ($this->type == 'video')
		{
			if ($this->getEncoders('libvo_aacenc'))
			{
				$audioCodec = '-acodec libvo_aacenc';
			}
			else if ($this->getEncoders('aac'))
			{
				// some versions of FFmpeg have aac listed as experimental
				// -strict -2 should ignore the warnings about that and still run
				$audioCodec = '-acodec aac -strict -2';
			}
			else
			{
				throw new \LogicException(\XF::phrase('xfmg_ffmpeg_has_access_to_neither_libvo_aacenc_codec_or_aac_codec'));
			}
			$videoCodec = '-vcodec libx264';
			$flags = '-ac 2 -movflags faststart -f mp4';
		}
		else
		{
			$audioCodec = '-acodec mp3';
			$videoCodec = '';
			$flags = '-f mp3';
		}

		$inputFile = $this->fileName;
		$outputFile = \XF\Util\File::getTempFile();

		$this->run("-i {input} {$videoCodec} {$audioCodec} -ar 48000 {$flags} -y {output}", [
			'input' => $inputFile,
			'output' => $outputFile
		]);

		return $outputFile;
	}

	public function run($command, array $args = [], &$return = null)
	{
		$ffmpegPath = escapeshellarg($this->ffmpegPath);

		$origCommand = $command;

		preg_match_all('#\{([a-z0-9_]+)}#i', $command, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			$key = $match[1];
			if (!isset($args[$key]))
			{
				throw new \InvalidArgumentException("Command '$origCommand' did not provide argument '$key'");
			}

			$value = escapeshellarg($args[$key]);
			$command = str_replace($match[0], $value, $command);
		}

		$output = [];
		exec("$ffmpegPath $command 2>&1", $output, $return);

		return $output;
	}
}