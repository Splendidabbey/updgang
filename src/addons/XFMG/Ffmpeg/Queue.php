<?php

namespace XFMG\Ffmpeg;

class Queue
{
	/**
	 * @var \XF\App
	 */
	protected $app;

	public function __construct(\XF\App $app)
	{
		$this->app = $app;
	}

	public function queue()
	{
		$jobManager = $this->app->jobManager();
		if (!$jobManager->getUniqueJob('xfmgTranscodeQueue'))
		{
			try
			{
				$jobManager->enqueueUnique('xfmgTranscodeQueue', 'XFMG:TranscodeQueue');
			}
			catch (\Exception $e)
			{
				// need to just ignore this and let it get picked up later;
				// not doing this could lose email on a deadlock
			}
		}

		return true;
	}

	public function run($limit)
	{
		$queue = $this->findQueue('pending');

		/**
		 * @var \XFMG\Entity\TranscodeQueue $record
		 */
		foreach ($queue->fetch($limit) AS $id => $record)
		{
			/** @var \XFMG\Service\Media\Transcoder $transcoder */
			$transcoder = $this->app->service('XFMG:Media\Transcoder', $record);
			$transcoder->beginTranscode();
		}
	}

	public function findQueue($state = null)
	{
		$finder = $this->app->finder('XFMG:TranscodeQueue')
			->order('queue_date');

		if ($state)
		{
			$finder->where('queue_state', $state);
		}

		return $finder;
	}

	public function countQueue($state = null)
	{
		return $this->findQueue($state)->total();
	}

	public function hasMore()
	{
		return (bool)$this->countQueue('pending');
	}
}