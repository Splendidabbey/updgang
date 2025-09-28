<?php

namespace XFMG\Import\Data;

trait HasWatchTrait
{
	protected $watchers = [];

	public function addWatcher($userId, array $params)
	{
		$this->watchers[$userId] = $params;
	}

	protected function insertWatchers($newId)
	{
		if ($this->watchers)
		{
			/** @var \XFMG\Import\DataHelper\Watch $watchHelper */
			$watchHelper = $this->dataManager->helper('XFMG:Watch');
			$watchHelper->importWatchBulk($newId, $this->getImportType(), $this->watchers);
		}
	}
}