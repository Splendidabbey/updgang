<?php

namespace AddonFlare\AwardSystem\Job;

use XF\Util\Arr;

class FixAwardOverwrite extends \XF\Job\AbstractRebuildJob
{
    protected $userAwardRepo;
    protected $awardIdsWithOverwrite;

    protected function setupData(array $data)
    {
        $db = $this->app->db();

        $this->userAwardRepo = \XF::repository('AddonFlare\AwardSystem:UserAward');

        $this->awardIdsWithOverwrite = $db->fetchAllColumn("
            SELECT award.award_id
            FROM xf_af_as_award award
            INNER JOIN xf_af_as_award_category awardCat ON (awardCat.award_category_id = award.award_category_id)
            WHERE
                awardCat.overwrite = 1
        ");

        return parent::setupData($data);
    }

    protected function getNextIds($start, $batch)
    {
        if (!$this->awardIdsWithOverwrite)
        {
            // no awards to process, nothing to do
            return [];
        }

        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            "
                SELECT user_id
                FROM xf_af_as_user_award
                WHERE
                    user_id > ?
                    AND award_id IN (" . $db->quote($this->awardIdsWithOverwrite) . ")
                GROUP BY user_id
                ORDER BY user_id
            ", $batch
        ), [$start]);
    }

    protected function rebuildById($userId)
    {
        $db = $this->app->db();

        $userAwards = $db->fetchAllKeyed("
            SELECT
                userAward.award_id, userAward.user_award_id,
                award.display_order, award.award_category_id
            FROM xf_af_as_user_award userAward
            INNER JOIN xf_af_as_award award ON (award.award_id = userAward.award_id)
            WHERE
                userAward.user_id = ?
                AND userAward.award_id IN (" . $db->quote($this->awardIdsWithOverwrite) . ")
                AND userAward.status = ?
        ", 'user_award_id', [$userId, 'approved']);

        $userAwardsGroupedByCategory = Arr::arrayGroup($userAwards, 'award_category_id');

        $userAwardsToDelete = [];

        foreach ($userAwardsGroupedByCategory as $categoryUserAwards)
        {
            // ASC sort by display_order
            $sorted = Arr::columnSort($categoryUserAwards, 'display_order');
            // reverse because we want in DESC order
            $sorted = array_reverse($sorted, true);

            // \XF::dolog($sorted);

            // take the first element off since we want to keep it
            array_shift($sorted);

            // the remaining awards should be removed (if any)
            if ($toDelete = array_column($sorted, 'user_award_id'))
            {
                $userAwardsToDelete = array_merge($userAwardsToDelete, $toDelete);
            }
        }

        // \XF::dolog([
        //     'userId' => $userId,
        //     'toDelete' => $userAwardsToDelete
        // ]);

        if ($userAwardsToDelete)
        {
            $db->delete('xf_af_as_user_award',
                "user_award_id IN (" . $db->quote($userAwardsToDelete) . ")"
            );

            // rebuild cache totals for user
            $this->userAwardRepo->rebuildUserAwardTotals($userId);
        }
    }

    protected function getStatusType()
    {
        return \XF::phrase('users');
    }
}