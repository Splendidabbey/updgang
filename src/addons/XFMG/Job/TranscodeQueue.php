<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;

class TranscodeQueue extends AbstractJob
{
	public function run($maxRunTime)
	{
		$app = $this->app;
		$queueClass = $app->extendClass('XFMG\Ffmpeg\Queue');

		/** @var \XFMG\Ffmpeg\Queue $queue */
		$queue = new $queueClass($app);

		$limit = $app->options()->xfmgFfmpeg['limit'];
		$count = $queue->countQueue('processing');

		if ($count >= $limit)
		{
			$result = $this->resume();
			$result->continueDate = time() + 30;

			return $result;
		}

		$queue->run($limit - $count);

		if ($queue->hasMore())
		{
			$result = $this->resume();
			$result->continueDate = time() + 30;

			return $result;
		}
		else
		{
			return $this->complete(); // no more work to do
		}
	}

	public function getStatusMessage()
	{
		return '';
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}