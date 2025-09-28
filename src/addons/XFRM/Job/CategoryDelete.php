<?php

namespace XFRM\Job;

use XF\Job\AbstractJob;

class CategoryDelete extends AbstractJob
{
	protected $defaultData = [
		'resource_category_id' => null,
		'count' => 0,
		'total' => null
	];

	public function run($maxRunTime)
	{
		$s = microtime(true);

		if (!$this->data['resource_category_id'])
		{
			throw new \InvalidArgumentException('Cannot delete resources without a resource_category_id.');
		}

		$finder = $this->app->finder('XFRM:ResourceItem')
			->where('resource_category_id', $this->data['resource_category_id']);

		if ($this->data['total'] === null)
		{
			$this->data['total'] = $finder->total();
			if (!$this->data['total'])
			{
				return $this->complete();
			}
		}

		$ids = $finder->pluckFrom('resource_id')->fetch(1000)->toArray();
		if (!$ids)
		{
			return $this->complete();
		}

		$continue = count($ids) < 1000 ? false : true;

		foreach ($ids AS $id)
		{
			$this->data['count']++;

			$resource = $this->app->find('XFRM:ResourceItem', $id);
			if (!$resource)
			{
				continue;
			}
			$resource->delete(false);

			if ($maxRunTime && microtime(true) - $s > $maxRunTime)
			{
				$continue = true;
				break;
			}
		}

		if ($continue)
		{
			return $this->resume();
		}
		else
		{
			return $this->complete();
		}
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('deleting');
		$typePhrase = \XF::phrase('xfrm_resources');
		return sprintf('%s... %s (%s/%s)', $actionPhrase, $typePhrase,
			\XF::language()->numberFormat($this->data['count']), \XF::language()->numberFormat($this->data['total'])
		);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}