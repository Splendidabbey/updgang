<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class ResourcePrefix extends AbstractEmulatedData
{
	protected $title = '';
	protected $description = '';
	protected $help = '';

	protected $categoryIds = [];

	public function getImportType()
	{
		return 'resource_prefix';
	}

	public function getEntityShortName()
	{
		return 'XFRM:ResourcePrefix';
	}

	public function setTitle(string $title)
	{
		$this->title = $title;
	}

	public function setDescription(string $description)
	{
		$this->description = $description;
	}

	public function setHelp(string $help)
	{
		$this->help = $help;
	}

	public function setCategories(array $categoryIds)
	{
		$this->categoryIds = $categoryIds;
	}

	protected function postSave($oldId, $newId)
	{
		/** @var \XFRM\Entity\ResourcePrefix $prefix */
		$prefix = $this->em()->find('XFRM:ResourcePrefix', $newId);
		if ($prefix)
		{
			$this->insertMasterPhrase($prefix->getPhraseName(), $this->title);
			$this->insertMasterPhrase($prefix->getDescriptionPhraseName(), $this->description);
			$this->insertMasterPhrase($prefix->getUsageHelpPhraseName(), $this->help);

			$this->em()->detachEntity($prefix);
		}

		if ($this->categoryIds)
		{
			$insert = [];
			foreach ($this->categoryIds AS $categoryId)
			{
				$insert[] = [
					'resource_category_id' => $categoryId,
					'prefix_id' => $newId
				];
			}

			$this->db()->insertBulk('xf_rm_category_prefix', $insert, false, false, 'IGNORE');
		}

		/** @var \XFRM\Repository\ResourcePrefix $repo */
		$repo = $this->repository('XFRM:ResourcePrefix');

		\XF::runOnce('rebuildResourcePrefixImport', function() use ($repo)
		{
			$repo->rebuildPrefixMaterializedOrder();
			$repo->rebuildPrefixCache();
		});
	}
}