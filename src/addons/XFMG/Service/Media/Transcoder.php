<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;

class Transcoder extends AbstractService
{
	/**
	 * @var \XFMG\Entity\TranscodeQueue
	 */
	protected $queueItem;

	public function __construct(\XF\App $app, \XFMG\Entity\TranscodeQueue $queueItem)
	{
		parent::__construct($app);
		$this->setQueueItem($queueItem);
	}

	protected function setQueueItem(\XFMG\Entity\TranscodeQueue $queueItem)
	{
		$this->queueItem = $queueItem;
	}

	public function beginTranscode()
	{
		$ds = \XF::$DS;
		$transcoder = realpath(\XF::getAddOnDirectory()) . "{$ds}XFMG{$ds}Ffmpeg{$ds}transcoder.php";

		$ffmpegOptions = $this->app->options()->xfmgFfmpeg;

		$command = sprintf(
			"%s %s %s",
			escapeshellarg($ffmpegOptions['phpPath']),
			escapeshellarg($transcoder),
			escapeshellarg($this->queueItem->transcode_queue_id)
		);

		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') // Windows
		{
			if (class_exists('COM', false))
			{
				$shell = new \COM("WScript.Shell");
				$shell->Run($command, 0, false);
			}
			else
			{
				pclose(popen("start \"XFMG\" /B $command", 'r'));
			}
		}
		else if (PHP_OS === 'Darwin') // macOS
		{
			exec("$command > /dev/null 2> /dev/null && disown -h");
		}
		else
		{
			exec("nohup $command > /dev/null 2> /dev/null &");
		}
	}

	public function transcodeProcess()
	{
		$queueItem = $this->queueItem;
		$filename = $queueItem->queue_data['fileName'];

		if (!$filename || !$this->app->fs()->has($filename))
		{
			// This will skip to finalizeTranscode and an exception will be thrown and user notified
			// that the transcode process failed.
			return null;
		}

		$tempPath = \XF\Util\File::copyAbstractedPathToTempFile($filename);

		$ffmpegOptions = $this->app->options()->xfmgFfmpeg;

		$class = 'XFMG\Ffmpeg\Runner';
		$class = \XF::extendClass($class);

		/** @var \XFMG\Ffmpeg\Runner $ffmpeg */
		$ffmpeg = new $class($ffmpegOptions['ffmpegPath']);
		$ffmpeg->setFileName($tempPath);
		$ffmpeg->setType($queueItem->queue_data['type']);

		return $ffmpeg->transcode();
	}

	public function finalizeTranscode($outputFile)
	{
		$queueItem = $this->queueItem;
		$queueData = $queueItem->queue_data;
		$queueItem->delete();

		if (!file_exists($outputFile))
		{
			$this->transcodeException($queueData);
		}

		$attachment = $this->em()->find('XF:Attachment', $queueData['attachment_id'], 'Data');
		if (!$attachment || !$attachment->Data)
		{
			$this->transcodeException($queueData);
		}

		if ($queueData['type'] == 'video')
		{
			$preparer = new \XFMG\VideoInfo\Preparer($outputFile);
			$result = $preparer->getInfo();

			if (!$result->isValid() || $result->requiresTranscoding())
			{
				$this->transcodeException($queueData);
			}
		}
		else
		{
			/** @var \XFMG\Service\Media\MP3Detector $MP3Detector */
			$MP3Detector = $this->app->service('XFMG:Media\MP3Detector', $outputFile);

			if (!$MP3Detector->isValidMP3())
			{
				$this->transcodeException($queueData);
			}
		}

		\XF\Util\File::deleteFromAbstractedPath($queueData['fileName']);

		clearstatcache();
		$updates = [
			'file_hash' => md5_file($outputFile),
			'file_size' => filesize($outputFile)
		];

		$data = $attachment->Data;
		$data->bulkSet($updates);
		if (!$data->save(false, false))
		{
			$this->transcodeException($queueData);
		}
		$finalPath = $data->getAbstractedDataPath();

		\XF\Util\File::copyFileToAbstractedPath($outputFile, $finalPath);

		$tempMedia = $this->em()->findOne('XFMG:MediaTemp', ['attachment_id' => $attachment->attachment_id]);
		$user = $this->em()->find('XF:User', $queueData['user_id']);

		\XF::asVisitor($user, function() use ($tempMedia, $queueData, $attachment)
		{
			/** @var \XFMG\Service\Media\Creator $creator */
			$creator = $this->service('XFMG:Media\Creator', $tempMedia);

			$container = null;

			if (isset($queueData['album_id']))
			{
				$container = $this->em()->find('XFMG:Album', $queueData['album_id']);
			}
			else if (isset($queueData['category_id']))
			{
				$container = $this->em()->find('XFMG:Category', $queueData['category_id']);
			}

			$creator->setContainer($container);
			$creator->setTitle($queueData['title'], $queueData['description']);
			$creator->setTags($queueData['tags']);
			$creator->setCustomFields($queueData['custom_fields']);
			$creator->setAttachment($attachment);
			$creator->logIp($queueData['ip']);
			$creator->checkForSpam();
			if ($creator->validate($errors))
			{
				/** @var \XFMG\Entity\MediaItem $mediaItem */
				if ($mediaItem = $creator->save())
				{
					$queueData['media_id'] = $mediaItem->media_id;

					$creator->sendNotifications();

					/** @var \XFMG\Repository\MediaWatch $watchRepo */
					$watchRepo = $this->repository('XFMG:MediaWatch');
					$watchRepo->autoWatchMediaItem($mediaItem, \XF::visitor(), true);

					/** @var \XFMG\Repository\Media $mediaRepo */
					$mediaRepo = $this->repository('XFMG:Media');
					$mediaRepo->sendTranscodeAlert($queueData, true);

					return true;
				}
			}

			$this->transcodeException($queueData, 'xfmg_transcoded_item_by_x_named_y_failed_creation');
			return false;
		});

		$this->enqueueJobIfNecessary();
	}

	protected function transcodeException(array $queueData, $errorMessage = 'xfmg_transcoded_item_by_x_named_y_failed_transcoding', array $params = [])
	{
		if (!$params)
		{
			$params = $queueData;
		}

		/** @var \XFMG\Repository\Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');
		$mediaRepo->sendTranscodeAlert($queueData, false);

		$this->enqueueJobIfNecessary();

		throw new \Exception(\XF::phrase($errorMessage, $params)->render());
	}

	protected function enqueueJobIfNecessary()
	{
		$jobManager = $this->app->jobManager();
		if (!$jobManager->getUniqueJob('xfmgTranscodeQueue'))
		{
			try
			{
				$jobManager->enqueueUnique('xfmgTranscodeQueue', 'XFMG:TranscodeQueue');
			}
			catch (\Exception $e) {}
		}
	}
}