<?php

namespace AddonFlare\AwardSystem\Entity;

use XF\Mvc\Entity\Structure;

class UserAward extends \XF\Mvc\Entity\Entity
{
	public function canView(&$error = null)
	{
		if (
			!$this->User ||
			$this->User->user_state == 'disabled' || $this->User->is_banned
		)
		{
			return false;
		}

		return true;
	}

	public function getAwardCategoryId()
	{
		return $this->Award->award_category_id;
	}

	protected function _setupDefaults()
	{
		$this->award_id = 0;
	}

	protected function _postSave()
	{
		if ($this->isInsert() && $this->User && $this->Award && $this->Award->Category)
		{
			$category = $this->Award->Category;

			if ($category->overwrite)
			{
				$otherAwards = $this->app()->finder('AddonFlare\AwardSystem:UserAward')
					->with('Award', true)
					->where('user_id', $this->user_id)
					->where('user_award_id', '!=', $this->user_award_id)
					->where('Award.award_category_id', $category->award_category_id)
					->where('Award.display_order', '<', $this->Award->display_order)
					->order('date_received', 'DESC')
					->fetch();

				if ($otherAwards->count())
				{
					$db = $this->app()->db();
					$db->delete(
						'xf_af_as_user_award',
						'user_award_id IN ('. $db->quote($otherAwards->keys()) .')'
					);
				}
			}
		}

		$this->rebuildUserAwardTotals();

		if (
			$this->isInsert() &&
			$this->User &&
			($maxFeatured = $this->User->max_featured_awards) &&
			$this->User->Option && $this->User->Option->af_as_auto_feature)
		{
			$db = $this->db();

			$db->beginTransaction();

			// move current feature awards 1 position
			$this->db()->query("
				UPDATE xf_af_as_user_award
				SET
					display_order = display_order + 100
				WHERE
					user_id = ? AND is_featured = 1
			", [$this->user_id]);

			// add this award to the beginning
            $this->fastUpdate([
            	'is_featured' => 1,
            	'display_order' => 100,
            ]);

            $db->commit();

            $idsWithinLimit = $db->fetchAllColumn("
            	SELECT user_award_id
            	FROM xf_af_as_user_award
            	WHERE
            		user_id = ? AND is_featured = 1
            	ORDER BY display_order ASC
            	LIMIT {$maxFeatured}
            ", [$this->user_id]);

            // un-feature any awards after the max featured limit
            $db->query("
				UPDATE xf_af_as_user_award
				SET
					is_featured = 0,
					display_order = 0
				WHERE
					user_id = ?
					AND user_award_id NOT IN (".$db->quote(array_merge([0], $idsWithinLimit)).")
            ", [$this->user_id]);
		}
	}

	protected function _postDelete()
	{
		$this->rebuildUserAwardTotals();
	}

	public function rebuildUserAwardTotals($runNow = false)
	{
		if ($runNow)
		{
			return $this->getUserAwardRepo()->rebuildUserAwardTotals($this->user_id);
		}
		else
		{
			\XF::runOnce('afASAwardRebuildUserAwardTotals_' . $this->user_id, function()
			{
				$this->getUserAwardRepo()->rebuildUserAwardTotals($this->user_id);
			});
		}
	}

	public static function getStructure(Structure $structure)
	{
	    $structure->table = 'xf_af_as_user_award';
	    $structure->shortName = 'AddonFlare\AwardSystem:UserAward';
	    $structure->primaryKey = 'user_award_id';
	    $structure->columns = [
	        'user_award_id' 	  => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
	        'award_id' 			  => ['type' => self::UINT, 'required' => true],
	        'user_id' 			  => ['type' => self::UINT, 'required' => true],
	        'recommended_user_id' => ['type' => self::UINT, 'required' => true],
	        'award_reason' 		  => ['type' => self::STR, 'required' => 'af_as_no_reason_specified'],
	        'date_received' 	  => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'date_requested' 	  => ['type' => self::UINT, 'required' => true],
			'status' 			  => ['type' => self::STR, 'required' => true, 'maxLength' => 25,
								     'allowedValues' => ['pending', 'approved', 'rejected']],
			'display_order'       => ['type' => self::UINT, 'default' => 10],
			'is_featured'         => ['type' => self::BOOL, 'default' => false],
			'given_by_user_id'	  => ['type' => self::UINT, 'default' => 0],
	    ];
		$structure->getters = [
			'award_category_id' => true,
		];
		$structure->relations = [
			'User' => [
				'entity' 		=> 'XF:User',
				'type' 			=> self::TO_ONE,
				'conditions' 	=> [
					['user_id', '=', '$user_id']
				],
				'primary'	=> true
			],
			'RecommendedUser' => [
				'entity' 		=> 'XF:User',
				'type' 			=> self::TO_ONE,
				'conditions' 	=> [
					['user_id', '=', '$recommended_user_id']
				],
				'primary'	=> true
			],
			'GivenUser' => [
				'entity' 		=> 'XF:User',
				'type' 			=> self::TO_ONE,
				'conditions' 	=> [
					['user_id', '=', '$given_by_user_id']
				],
				'primary'	=> true
			],
			'Award' => [
				'type' 			=> self::TO_ONE,
				'entity' 		=> 'AddonFlare\AwardSystem:Award',
				'conditions' 	=> [
					['award_id', '=', '$award_id']
				],
				'primary'	=> true
			]
		];
		$structure->options = [
			'check_duplicate' => true
		];

		$structure->defaultWith[] = 'User';
		$structure->defaultWith[] = 'Award';

	    return $structure;
	}

	/**
	 * @return \AddonFlare\AwardSystem\Repository\Award
	 */
	protected function getAwardRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:Award');
	}
	protected function getUserAwardRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:UserAward');
	}
}