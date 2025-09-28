<?php

namespace DBTech\Credits\Behavior;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Behavior;

class Cacheable extends Behavior
{
	public function postSave()
	{
		$this->rebuildCache();
	}

	public function postDelete()
	{
		$this->rebuildCache();
	}

	public function rebuildCache()
	{
		Helper::repository($this->entity->structure()->shortName)->rebuildCache();
	}
}