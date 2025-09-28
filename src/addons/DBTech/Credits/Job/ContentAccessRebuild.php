<?php

namespace DBTech\Credits\Job;

use DBTech\Credits\Helper;
use XF\Job\AbstractJob;

/**
 * Class ContentAccessRebuild
 *
 * @package DBTech\Credits\Job
 */
class ContentAccessRebuild extends AbstractJob
{
	/** @var array  */
	protected $defaultData = [
		'type' => null,
		'rebuild_types' => null,
		'start' => 0,
		'batch' => 500,
		'delay' => 0,
		'truncate' => false,
	];
	protected string $builtType = '';
	protected int $builtLast = 0;


	/**
	 * @param int $maxRunTime
	 *
	 * @return \XF\Job\JobResult
	 * @throws \Exception
	 */
	public function run($maxRunTime): \XF\Job\JobResult
	{
		$contentAccessRepo = Helper::repository(\DBTech\Credits\Repository\ContentAccess::class);

		if (!is_array($this->data['rebuild_types']) && $this->data['truncate'])
		{
			$this->app->db()->emptyTable('xf_dbtech_credits_content_access_purchase');
			$this->data['truncate'] = false;
		}

		if (!is_array($this->data['rebuild_types']))
		{
			$this->data['rebuild_types'] = $this->data['type']
				? [$this->data['type']]
				: $contentAccessRepo->getRebuildableContentTypes()
			;
			$this->data['type'] = null;
		}

		if (!$this->data['type'])
		{
			$this->data['type'] = array_shift($this->data['rebuild_types']);
			if (!$this->data['type'])
			{
				return $this->complete();
			}

			$this->data['start'] = 0;
		}

		$type = $this->data['type'];
		$start = $this->data['start'];

		$handler = $contentAccessRepo->getHandler($type);

		$this->builtType = $this->data['type'];

		$last = $handler->rebuildRange($start, $this->data['batch']);
		if (!$last)
		{
			// done this type
			$this->data['type'] = null;
			return $this->resume();
		}

		$this->builtLast = $last;

		$this->data['start'] = $last;
		return $this->resume();
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('dbtech_credits_content_access_purchase_records');
		if ($this->builtType && $this->builtLast)
		{
			$handler = Helper::repository(\DBTech\Credits\Repository\ContentAccess::class)
				->getHandler($this->builtType)
			;

			return sprintf('%s... %s (%s %s)', $actionPhrase, $typePhrase, $handler->getTitle(), $this->builtLast);
		}
		else
		{
			return sprintf('%s... %s', $actionPhrase, $typePhrase);
		}
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return true;
	}
}