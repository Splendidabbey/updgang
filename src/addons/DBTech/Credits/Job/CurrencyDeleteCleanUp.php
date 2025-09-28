<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Helper;
use XF\Job\AbstractJob;

/**
 * Class CurrencyDeleteCleanUp
 *
 * @package DBTech\Credits\Job
 */
class CurrencyDeleteCleanUp extends AbstractJob
{
	/** @var array  */
	protected $defaultData = [
		'currencyId' => null,
		'title' => null,

		'currentStep' => 0,
		'lastOffset' => null,

		'start' => 0
	];

	/**
	 * @param int $maxRunTime
	 *
	 * @return \XF\Job\JobResult
	 */
	public function run($maxRunTime): \XF\Job\JobResult
	{
		$this->data['start']++;

		if (!$this->data['currencyId'] || !$this->data['title'])
		{
			return $this->complete();
		}

		$deleter = Helper::service(
			\DBTech\Credits\Service\Currency\DeleteCleanUp::class,
			$this->data['currencyId'],
			$this->data['title']
		);
		$deleter->restoreState($this->data['currentStep'], $this->data['lastOffset']);

		$result = $deleter->cleanUp($maxRunTime);
		if ($result->isCompleted())
		{
			return $this->complete();
		}

		$continueData = $result->getContinueData();
		$this->data['currentStep'] = $continueData['currentStep'];
		$this->data['lastOffset'] = $continueData['lastOffset'];

		return $this->resume();
	}

	/**
	 * @return string
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('deleting');
		$typePhrase = $this->data['title'];
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return false;
	}
}