<?php

namespace AddonFlare\AwardSystem\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Award extends Repository
{
	/**
	 * @return Finder
	 */
	public function findAwardsForList()
	{
		$finder = $this->finder('AddonFlare\AwardSystem:Award')
			->with('Trophy')
			->order('display_order');

		return $finder;
	}

    public function getAwardCache()
    {
        $awards = $this->findAwardsForList()
            ->fetch();

        $cache = [];

        foreach ($awards as $award)
        {
            $cache[$award->award_id] = [
                'award_id'   => $award->award_id,
                'inline_css' => $award->inline_css,
            ];
        }

        return $cache;
    }

    public function rebuildAwardCache()
    {
        $cache = $this->getAwardCache();

        $simpleCache = $this->app()->simpleCache();
        $simpleCache['AddonFlare/AwardSystem']['awards'] = $cache;

        return $cache;
    }

    public function getAwardsFromCache()
    {
        $simpleCache = $this->app()->simpleCache();

        $awards = $simpleCache['AddonFlare/AwardSystem']['awards'];

        if ($awards === null)
        {
            $awards = $this->rebuildAwardCache();
        }

        return $awards;
    }

	public function getAwardListData($categoryIds = null, $withVisitorAwards = true, $checkShowInList = true)
	{
		$awardsFinder = $this->findAwardsForList();

		if (isset($categoryIds))
		{
			$awardsFinder->where('award_category_id', $categoryIds);
		}

		if ($withVisitorAwards)
		{
			$awardsFinder->with('UserAward|' . \XF::visitor()->user_id);
		}

		if ($checkShowInList)
		{
			$awardsFinder->where('show_in_list', 1);
		}

		$awards = $awardsFinder->fetch();

		$awardCategories = $this->getAwardCategoryRepo()
			->findAwardCategoriesForList(true, $categoryIds);

        if ($awardIds = $awards->keys())
        {
            $db = $this->db();

            $quoted = $db->quote($awardIds);
            $totalAwarded = $db->fetchPairs("
                SELECT award_id, COUNT(*)
                FROM xf_af_as_user_award FORCE INDEX (`award_id_status`)
                WHERE
                    award_id IN ($quoted)
                    AND `status` = ?
                GROUP BY award_id
                ORDER BY NULL
            ", ['approved']);

            // set these getters to avoid individual queries by each award
            foreach ($totalAwarded as $awardId => $total)
            {
            	$awards[$awardId]->setTotalAwarded($total);
            }
        }

		return [
			'awardCategories' => $awardCategories,
			'totalAwards' => $awards->count(),
			'awards' => $awards->groupBy('award_category_id'),
			'awardsUngrouped' => $awards,
		];
	}

	/**
	 * @return AwardCategory
	 */
	protected function getAwardCategoryRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:AwardCategory');
	}

    protected function getUserAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:UserAward');
    }
}