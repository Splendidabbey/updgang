<?php

namespace XFMG\Import\Importer;

use XF\Import\StepState;

class XFMG21 extends XFMG2
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'XenForo Media Gallery 2.1',
		];
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFMG'");
		if (!$versionId || intval($versionId) < 902010031 || intval($versionId) >= 902020031)
		{
			$error = \XF::phrase('xfmg_you_may_only_import_from_xenforo_media_gallery_x', ['version' => '2.1']);
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
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems', 'comments'
		]);

		if (!$contentTypes)
		{
			return 0;
		}

		return $this->getMaxReactionContentIdForContentTypes($contentTypes);
	}

	public function stepReactionContent(StepState $state, array $stepConfig, $maxTime)
	{
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems', 'comments'
		]);

		if (!$contentTypes)
		{
			return $state->complete();
		}

		return $this->getReactionContentStepStateForContentTypes(
			$contentTypes, $state, $stepConfig, $maxTime
		);
	}

	// ########################### STEP: BOOKMARKS ###############################

	public function getStepEndBookmarks()
	{
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems'
		]);

		if (!$contentTypes)
		{
			return 0;
		}

		return $this->getMaxBookmarkIdForContentTypes($contentTypes);
	}

	public function stepBookmarks(StepState $state, array $stepConfig, $maxTime)
	{
		$contentTypes = $this->getContentTypesFromRunSteps([
			'albums', 'mediaItems'
		]);

		if (!$contentTypes)
		{
			return $state->complete();
		}

		return $this->getBookmarksStepStateForContentTypes(
			$contentTypes, $state, $stepConfig, $maxTime
		);
	}
}