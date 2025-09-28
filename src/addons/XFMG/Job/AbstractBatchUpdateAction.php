<?php

namespace XFMG\Job;

use XF\Job\AbstractJob;

abstract class AbstractBatchUpdateAction extends AbstractJob
{
	protected $defaultData = [
		'start' => 0,
		'count' => 0,
		'total' => null,
		'criteria' => null,
		'ids' => null,
		'actions' => []
	];

	public function run($maxRunTime)
	{
		if (is_array($this->data['criteria']) && is_array($this->data['ids']))
		{
			throw new \LogicException("Cannot have both criteria and ids values; one must be null");
		}

		$startTime = microtime(true);

		$ids = $this->prepareIds();
		if (!$ids)
		{
			return $this->complete();
		}

		$db = $this->app->db();
		$db->beginTransaction();

		$limitTime = ($maxRunTime > 0);
		foreach ($ids AS $key => $id)
		{
			$this->data['count']++;
			$this->data['start'] = $id;
			unset($ids[$key]);

			/** @var \XFMG\Entity\Album|\XFMG\Entity\MediaItem $item */
			$item = $this->findById($id);
			if ($item)
			{
				if ($this->getActionValue('delete'))
				{
					$item->delete(false, false);
					continue; // no further action required
				}

				$this->applyInternalItemChange($item);
				$item->save(false, false);

				$this->applyExternalItemChange($item);
			}

			if ($limitTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}

		if (is_array($this->data['ids']))
		{
			$this->data['ids'] = $ids;
		}

		$db->commit();

		return $this->resume();
	}

	protected function findById($id)
	{
		return $this->app->em()->find($this->getClassIdentifier(), $id);
	}

	protected function getActionValue($action)
	{
		$value = null;
		if (!empty($this->data['actions'][$action]))
		{
			$value = $this->data['actions'][$action];
		}
		return $value;
	}

	protected function prepareIds()
	{
		if (is_array($this->data['criteria']))
		{
			$column = $this->getColumn();
			
			$searcher = $this->app->searcher($this->getClassIdentifier(), $this->data['criteria']);
			$results = $searcher->getFinder()
				->where($column, '>', $this->data['start'])
				->order($column)
				->limit(1000)
				->fetchColumns($column);
			$ids = array_column($results, $column);
		}
		else if (is_array($this->data['ids']))
		{
			$ids = $this->data['ids'];
		}
		else
		{
			$ids = [];
		}
		sort($ids, SORT_NUMERIC);
		return $ids;
	}

	abstract protected function getColumn();

	abstract protected function getClassIdentifier();

	abstract protected function applyInternalItemChange(\XF\Mvc\Entity\Entity $entity);

	protected function applyExternalItemChange(\XF\Mvc\Entity\Entity $entity)
	{
	}

	abstract protected function getTypePhrase();

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('updating');
		$typePhrase = $this->getTypePhrase();

		if ($this->data['total'] !== null)
		{
			return sprintf('%s... %s (%d/%d)', $actionPhrase, $typePhrase, $this->data['count'], $this->data['total']);
		}
		else
		{
			return sprintf('%s... %s (%d)', $actionPhrase, $typePhrase, $this->data['start']);
		}
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