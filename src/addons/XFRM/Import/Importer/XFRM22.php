<?php

namespace XFRM\Import\Importer;

use XF\Import\StepState;

class XFRM22 extends XFRM21
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Resource Manager',
			'source' => 'XenForo Resource Manager 2.2',
		];
	}

	protected function validateVersion(\XF\Db\AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne("SELECT version_id FROM xf_addon WHERE addon_id = 'XFRM'");
		if (!$versionId || intval($versionId) < 2020031)
		{
			$error = \XF::phrase('xfrm_you_may_only_import_from_xenforo_resource_manager_x', ['version' => '2.2']);
			return false;
		}

		return true;
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		$steps = $this->extendSteps($steps, [
			'title' => \XF::phrase('xfrm_resource_review_fields'),
			'depends' => ['resources']
		], 'reviewFields', 'resources');

		$steps = $this->extendSteps($steps, [
			'title' => '~~Resource review votes~~',
			'depends' => ['ratings']
		], 'reviewVotes', 'ratings');

		// TODO: update edit history

		return $steps;
	}

	// ########################### STEP: REVIEW FIELDS ###############################

	public function stepReviewFields(StepState $state)
	{
		$this->typeMap('resource_category');
		$this->typeMap('user_group');

		$fields = $this->sourceDb->fetchAllKeyed("
			SELECT field.*,
				ptitle.phrase_text AS title,
				pdesc.phrase_text AS description
			FROM xf_rm_resource_review_field AS field
			INNER JOIN xf_phrase AS ptitle ON
				(ptitle.language_id = 0 AND ptitle.title = CONCAT('xfrm_resource_review_field_title.', field.field_id))
			INNER JOIN xf_phrase AS pdesc ON
				(pdesc.language_id = 0 AND pdesc.title = CONCAT('xfrm_resource_review_field_desc.', field.field_id))
		", 'field_id');

		$existingFields = $this->db()->fetchPairs("SELECT field_id, field_id FROM xf_rm_resource_review_field");

		$fieldCategoryMap = [];
		$categoryFields = $this->sourceDb->fetchAll("
			SELECT *
			FROM xf_rm_category_review_field
		");
		foreach ($categoryFields AS $categoryField)
		{
			$newCategoryId = $this->lookupId('resource_category', $categoryField['resource_category_id']);
			if ($newCategoryId)
			{
				$fieldCategoryMap[$categoryField['field_id']][] = $newCategoryId;
			}
		}

		foreach ($fields AS $oldId => $field)
		{
			if (!empty($existingFields[$oldId]))
			{
				// don't import a field if we already have one called that - this assumes the same structure
				$this->logHandler('XFRM:ResourceReviewField', $oldId, $oldId);
			}
			else
			{
				/** @var \XFRM\Import\Data\ResourceReviewField $import */
				$import = $this->setupCustomFieldImport('XFRM:ResourceReviewField', $field);

				if (!empty($fieldCategoryMap[$oldId]))
				{
					$import->setCategories($fieldCategoryMap[$oldId]);
				}

				$import->save($oldId);
			}

			$state->imported++;
		}

		return $state->complete();
	}

	// ############################## STEP: RESOURCE RATINGS #########################

	protected function setupRatingImport(array $rating)
	{
		$import = parent::setupRatingImport($rating);
		if (!$import)
		{
			return $import;
		}

		$customFields = $this->decodeValue($rating['custom_fields'], 'serialized-json-array');
		if ($customFields)
		{
			$import->setCustomFields($this->mapCustomFields('resource_review_field', $customFields));
		}

		if (!empty($rating['author_response_team_user_id']))
		{
			$import->author_response_team_user_id = $this->lookupId('user', $rating['author_response_team_user_id'], 0);
			$import->author_response_team_username = $rating['author_response_team_username'];
		}

		return $import;
	}

	// ########################### STEP: REVIEW VOTES ###############################

	public function getStepEndReviewVotes()
	{
		return $this->getMaxContentVoteIdForContentTypes(
			['resource_rating']
		);
	}

	public function stepReviewVotes(StepState $state, array $stepConfig, $maxTime)
	{
		return $this->getContentVoteStepStateForContentTypes(
			['resource_rating'],
			$state, $stepConfig, $maxTime
		);
	}
}