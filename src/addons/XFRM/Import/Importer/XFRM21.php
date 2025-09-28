<?php

namespace XFRM\Import\Importer;

use XF\Import\StepState;

class XFRM21 extends XFRM2
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Resource Manager',
			'source' => 'XenForo Resource Manager 2.1',
		];
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFRM'");
		if (!$versionId || intval($versionId) < 2010031 || intval($versionId) >= 2020031)
		{
			$error = \XF::phrase('xfrm_you_may_only_import_from_xenforo_resource_manager_x', ['version' => '2.1']);
			return false;
		}

		return true;
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		$steps = $this->extendSteps($steps, [
			'title' => \XF::phrase('reaction_content'),
			'depends' => []
		], 'reactionContent', 'likes');

		$steps['bookmarks'] = [
			'title' => \XF::phrase('bookmarks'),
			'depends' => []
		];

		unset($steps['likes']);

		return $steps;
	}

	// ########################### STEP: REACTION CONTENT ###############################

	public function getStepEndReactionContent()
	{
		return $this->getMaxReactionContentIdForContentTypes('resource_update');
	}

	public function stepReactionContent(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getReactionContentStepStateForContentTypes(
			'resource_update', $state, $stepConfig, $maxTime
		);
	}

	// ########################### STEP: BOOKMARKS ###############################

	public function getStepEndBookmarks()
	{
		return $this->getMaxBookmarkIdForContentTypes(['resource', 'resource_update']);
	}

	public function stepBookmarks(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getBookmarksStepStateForContentTypes(
			['resource', 'resource_update'], $state, $stepConfig, $maxTime
		);
	}
}