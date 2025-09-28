<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class ResourcePrefixGroup extends AbstractEmulatedData
{
	protected $title = '';

	public function getImportType()
	{
		return 'resource_prefix_group';
	}

	public function getEntityShortName()
	{
		return 'XFRM:ResourcePrefixGroup';
	}

	public function setTitle($title)
	{
		$this->title = $title;
	}

	protected function postSave($oldId, $newId)
	{
		/** @var \XFRM\Entity\ResourcePrefixGroup $group */
		$group = $this->em()->find('XFRM:ResourcePrefixGroup', $newId);
		if ($group)
		{
			$this->insertMasterPhrase($group->getPhraseName(), $this->title);

			$this->em()->detachEntity($group);
		}
	}
}