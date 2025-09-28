<?php

namespace AddonFlare\AwardSystem\XF\Repository;

class Trophy extends XFCP_Trophy
{
    /**
     * @param \XF\Entity\User $user
     * @param \XF\Entity\UserTrophy[] $userTrophies
     * @param \XF\Entity\Trophy[] $trophies
     * @return int
     */
    /* Not needed anymore as of version 1.4 since we don't link to trophies
    public function updateTrophiesForUser(\XF\Entity\User $user, $userTrophies = null, $trophies = null)
    {
        $awarded = parent::updateTrophiesForUser($user, $userTrophies, $trophies);

        $this->em->clearEntityCache('XF:UserTrophy');

        $userTrophies = $this->findUserTrophies($user->user_id)
            // only the ones that were just awarded, otherwise it gives awards even if they were deleted
            ->where('award_date', \XF::$time)
            ->fetch();

        if ($userTrophies->count())
        {
            $awards = $this->finder('AddonFlare\AwardSystem:Award')
                ->where('award_trophy_id', $userTrophies->pluckNamed('trophy_id'))
                ->fetch()->groupBy('award_trophy_id');

            $userAwards = $this->db()->fetchAllKeyed('
                SELECT
                    userAward.award_id, userAward.user_award_id,
                    award.display_order, award.award_category_id,
                    awardCat.overwrite
                FROM xf_af_as_user_award userAward
                INNER JOIN xf_af_as_award award ON (award.award_id = userAward.award_id)
                LEFT JOIN xf_af_as_award_category awardCat ON (awardCat.award_category_id = award.award_category_id)
                WHERE
                    user_id = ?
                    AND status = ?
            ', 'user_award_id', [$user->user_id, 'approved']);

            $userAwardsByAwardId = $overwrittenUserAwards = [];
            foreach ($userAwards as $userAwardId => $userAward)
            {
                $userAwardsByAwardId[$userAward['award_id']] = $userAward;
                if (!empty($userAward['overwrite']))
                {
                    if (!isset($overwrittenUserAwards[$userAward['award_category_id']]))
                    {
                        $overwrittenUserAwards[$userAward['award_category_id']] = [];
                    }

                    $overwrittenUserAwards[$userAward['award_category_id']][] = $userAward['display_order'];
                }
            }

            $checkIfHigherDisplayOrderExists = function($categoryId, $displayOrder) use ($overwrittenUserAwards)
            {
                $existingCategoryDisplayOrders = $overwrittenUserAwards[$categoryId] ?? [];

                foreach ($existingCategoryDisplayOrders as $categoryDisplayOrder)
                {
                    if ($categoryDisplayOrder > $displayOrder)
                    {
                        return true;
                    }
                }

                return false;
            };

            $db = $this->app()->db();
            $alertRepo = $this->repository('XF:UserAlert');
            $userAwardRepo = $this->repository('AddonFlare\AwardSystem:UserAward');

            foreach ($userTrophies AS $trophy)
            {
                $trophyAlertedUserIdsToDelete = [];

                if (isset($awards[$trophy->trophy_id]))
                {
                    $userTrophyAwards = $awards[$trophy->trophy_id];

                    foreach ($userTrophyAwards AS $award)
                    {
                        if (
                            ($award->allow_multiple || !isset($userAwardsByAwardId[$award->award_id])) &&
                            !$checkIfHigherDisplayOrderExists($award->award_category_id, $award->display_order)
                        )
                        {
                            $userAwardRepo->awardAwardToUser($user, $award);
                            $trophyAlertedUserIdsToDelete[] = $user->user_id;

                            if (!$award->allow_multiple)
                            {
                                // delete any pending requests for this award for the recipients that were just awarded and don't support multiple
                                $db->delete('xf_af_as_user_award', "
                                    user_id = ?
                                    AND award_id = ?
                                    AND status = ?
                                ", [$user->user_id, $award->award_id, 'pending']);
                            }
                        }
                    }
                }

                // Suppress the trophy alert when linked to an award (2 alerts for the same thing cause spam).
                if ($trophyAlertedUserIdsToDelete)
                {
                    $alertRepo->fastDeleteAlertsToUserWithDate($trophyAlertedUserIdsToDelete, \XF::$time, 'trophy', $trophy->trophy_id, 'award');
                }
            }
        }

        return $awarded;
    }
    */
}