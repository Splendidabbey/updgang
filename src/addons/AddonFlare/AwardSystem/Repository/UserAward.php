<?php

namespace AddonFlare\AwardSystem\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;

class UserAward extends Repository
{
    public function updateAwardsForUser(\XF\Entity\User $user, $userAwards = null, $awards = null)
    {
        if ($userAwards === null)
        {
            $userAwards = $this->findUserAwardsForList($user->user_id, 'approved')->fetch();
        }
        else if (is_array($userAwards))
        {
            // make sure it's a collection
            $userAwards = $this->em->getBasicCollection($userAwards);
        }

        if ($awards === null)
        {
            $awards = $this->findAwardsForList()->fetch();
        }

        $userAwardsByAwardId = $overwrittenUserAwards = [];
        foreach ($userAwards as $userAwardId => $userAward)
        {
            $userAwardsByAwardId[$userAward->award_id] = $userAward;
            if ($userAward->Award->Category && $userAward->Award->Category->overwrite)
            {
                if (!isset($overwrittenUserAwards[$userAward->award_category_id]))
                {
                    $overwrittenUserAwards[$userAward->award_category_id] = [];
                }

                $overwrittenUserAwards[$userAward->award_category_id][] = $userAward->Award->display_order;
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

        $awarded = 0;

        // key by award_id
        $userAwards = $userAwards->pluck(function(\XF\Mvc\Entity\Entity $entity)
        {
            return [$entity->award_id, $entity];
        });

        $db = $this->db();

        foreach ($awards AS $award)
        {
            // skip it it exists
            if (
                (isset($userAwards[$award->award_id])) ||
                $checkIfHigherDisplayOrderExists($award->award_category_id, $award->display_order)
            )
            {
                continue;
            }

            $userCriteria = $this->app()->criteria('XF:User', $award->user_criteria);
            $userCriteria->setMatchOnEmpty(false);
            if ($userCriteria->isMatched($user))
            {
                $this->awardAwardToUser($user, $award);
                $awarded++;

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

        return $awarded;
    }

    public function findUserAwardsForList($userId = 0, $status = '')
    {
        $finder = $this->finder('AddonFlare\AwardSystem:UserAward');

        if ($userId)
        {
            $finder->where('user_id', $userId);
        }

        if ($status)
        {
            $finder->where('status', $status);
        }

        return $finder;
    }

    public function getPendingUserAwardsTotal()
    {
        return $this->findUserAwardsForList(0, 'pending')->total();
    }

    public function findFeatureableAwards($userIds)
    {
        return $this->findUserAwardsForList($userIds, 'approved')
            ->with('Award', true)
            ->where('Award.can_feature', 1)
            ->order([['date_received', 'DESC'], ['user_award_id', 'DESC']]); // sorts by latest received
    }

    public function findFeaturedAwards($userIds)
    {
        return $this->findFeatureableAwards($userIds)
            ->where('is_featured', 1)
            ->resetOrder()
            ->order('display_order', 'ASC');
    }

    public function getStatusActions($status)
    {
        $actions = [];
        switch ($status)
        {
            case 'approved':
            {
                // can only delete
                break;
            }
            case 'rejected':
            {
                $actions[] = 'approved';
                break;
            }

            default:
            {
                $actions[] = 'approved';
                $actions[] = 'rejected';
                break;
            }
        }

        if (\XF::visitor()->is_admin)
        {
            $actions[] = 'delete';
        }

        return $actions;
    }

    public function getValidatedRecipients(\AddonFlare\AwardSystem\Entity\Award $award, $recipients, &$error = null)
    {
        $error = null;

        if (is_string($recipients))
        {
            $recipients = preg_split('#\s*,\s*#', $recipients, -1, PREG_SPLIT_NO_EMPTY);
        }
        else if ($recipients instanceof \XF\Entity\User)
        {
            $recipients = [$recipients];
        }

        if (!$recipients)
        {
            return [];
        }

        if ($recipients instanceof AbstractCollection)
        {
            $first = $recipients->first();
        }
        else
        {
            $first = reset($recipients);
        }

        if ($first instanceof \XF\Entity\User)
        {
            $type = 'user';
        }
        else
        {
            $type = 'name';
        }

        foreach ($recipients AS $k => $recipient)
        {
            if ($type == 'user' && !($recipient instanceof \XF\Entity\User))
            {
                throw new \InvalidArgumentException("Recipient at key $k must be a user entity");
            }
        }

        if ($type == 'name')
        {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($recipients, $notFound, ['Privacy']);

            if ($notFound)
            {
                $error = \XF::phraseDeferred('the_following_recipients_could_not_be_found_x',
                    ['names' => implode(', ', $notFound->pluckNamed)]
                );
            }
        }
        else
        {
            $users = $recipients;
        }

        if (!($users instanceof AbstractCollection))
        {
            $users = new ArrayCollection($users);
        }

        if (!$award->allow_multiple && $users->count())
        {
            $db = $this->app()->db();
            $useridsWithAward = $db->fetchPairs("
                SELECT user_id, user_id
                FROM xf_af_as_user_award
                WHERE
                    award_id = ?
                    AND user_id IN (". $db->quote($users->pluckNamed('user_id')) .")
                    AND status = ?
            ", [$award->award_id, 'approved']);

            $invalidRecipients = [];

            foreach ($users as $userId => $user)
            {
                if (isset($useridsWithAward[$userId]))
                {
                    $invalidRecipients[$userId] = $user;
                }
            }

            if ($invalidRecipients)
            {
                $invalidRecipients = new ArrayCollection($invalidRecipients);
                $error = \XF::phraseDeferred('af_as_the_following_users_x_already_have_this_award',
                    ['names' => implode(', ', $invalidRecipients->pluckNamed('username'))]
                );
            }
        }

        return $users;
    }

    public function addUserAwardsToContent($content, $userIdsKey = 'user_id')
    {
        $relationKey = 'AwardSystemUserAwards'; // we're adding to the User entities, hard set for now

        // 2.2 fix: content (posts) is passed as array, make sure it's a collection so we can use pluckNamed
        if (!($content instanceof AbstractCollection))
        {
            $content = new ArrayCollection($content);
        }

        $userIds = $content->pluckNamed($userIdsKey);

        if ($userIds)
        {
            $userAwards = $this->findFeaturedAwards($userIds)
                ->fetch()
                ->groupBy('user_id');

             foreach ($content AS $item)
             {
                // log and skip possible errors
                if (!($item instanceof \XF\Mvc\Entity\Entity))
                {
                    \XF::logError("Open support ticket at addonflare.com so we can investigate: type: " . gettype($item) . is_object($item ? ' (' . get_class($item) . ')' : ''));
                    continue;
                }
                if (!$item->isValidRelation('User'))
                {
                    \XF::logError('Invalid content relation key "User"');
                    continue;
                }
                if (!$item->User)
                {
                    // skip deleted users to avoid error
                    continue;
                }
                if (!$item->User->isValidRelation($relationKey))
                {
                    \XF::logError("Invalid user relation key '{$relationKey}'");
                    continue;
                }

                $userId = $item->user_id;

                $contentUserAwards = isset($userAwards[$userId])
                    ? $this->em->getBasicCollection($userAwards[$userId])
                    : $this->em->getEmptyCollection();

                // \XF::dump($contentUserAwards);

                $item->User->hydrateRelation($relationKey, $contentUserAwards);
             }
        }
    }

    public function getslicedCollection(AbstractCollection $userAwards, $offset, $length = null)
    {
        return $userAwards->slice($offset, $length);
    }

    // not used anymore, we now strictly use the display_order field in xf_af_as_user_award
    public function sortUserAwards(AbstractCollection $userAwards, $newestFirst = true)
    {
        $userAwardsArr = $userAwards->toArray();

        uasort($userAwardsArr, function ($a, $b) use ($newestFirst)
        {
            if ($a->display_order != $b->display_order)
            {
                return $a->display_order < $b->display_order ? -1 : 1;
                // return $a->display_order <=> $b->display_order; // make compatible with non PHP 7 :(
            }

            if ($a->date_received == $b->date_received)
            {
                return 0;
            }

            if ($newestFirst)
            {
                // newestFirst means descening order for "date_received"
                return $a->date_received < $b->date_received ? 1 : -1;
            }
            else
            {
                // ascending order (oldest first)
                return $a->date_received < $b->date_received ? -1 : 1;
            }
        });

        $userAwards = new ArrayCollection($userAwardsArr);

        return $userAwards;
    }

    public function awardAwardToUser(\XF\Entity\User $user, \AddonFlare\AwardSystem\Entity\Award $award)
    {
        $userAward = $this->em->create('AddonFlare\AwardSystem:UserAward');
        $time = \XF::$time;
        $userAward->bulkset([
            'user_id'               => $user->user_id,
            'award_id'              => $award->award_id,
            'recommended_user_id'   => $user->user_id,
            'award_reason'          => \XF::phrase('af_as_automaticaly_awarded'), //
            'date_received'         => $time,
            'date_requested'        => $time,
            'status'                => 'approved',
        ]);
        $userAward->save();

        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->alertFromUser($user, $user, 'af_as_award', $award->award_id, 'award');
    }

    public function rebuildUserAwardTotals($userId)
    {
        $db = $this->db();

        $fetchTotals = function($userId) use ($db)
        {
            $totals = $db->fetchRow('
                SELECT COUNT(*) AS award_total, SUM(award.award_points) AS award_points, user.af_as_award_points AS old_award_points
                FROM xf_af_as_user_award user_award
                INNER JOIN xf_af_as_award award ON (award.award_id = user_award.award_id)
                INNER JOIN xf_user user ON (user.user_id = user_award.user_id)
                WHERE
                    user_award.user_id = ?
                    AND user_award.status = ?
            ', [$userId, 'approved']);

            // make sure values aren't null
            $totals = array_map('intval', $totals);

            return $totals;
        };

        if ($userId instanceof \XF\Entity\User)
        {
            $user = $userId;
            $userId = $user->user_id;

            $totals = $fetchTotals($userId);

            $user->fastUpdate([
                'af_as_award_total'  => $totals['award_total'],
                'af_as_award_points' => $totals['award_points'],
            ]);
        }
        else
        {
            $userId = intval($userId);

            $totals = $fetchTotals($userId);

            $db->update('xf_user', [
                'af_as_award_total'  => $totals['award_total'],
                'af_as_award_points' => $totals['award_points'],
            ], 'user_id = ?', $userId);
        }

        // don't use options cache because it doesn't refresh until the end of the script
        $option = $this->em->find('XF:Option', 'af_as_levels_maxpoints');

        $hasNewMaxPoints = false;

        if ($totals['award_points'] > $option->option_value)
        {
            $option->option_value = $totals['award_points'];
            $option->save();

            $hasNewMaxPoints = true;
        }

        $oldLevel = $this->getLevelFromPoints($totals['old_award_points']);
        $newLevel = $this->getLevelFromPoints($totals['award_points'], $hasNewMaxPoints ? false : true);

        if ($newLevel > $oldLevel && $this->options()->af_as_levels_enabled)
        {
            // send alert
            $alertRepo = $this->repository('XF:UserAlert');
            if (!isset($user))
            {
                $user = $this->em->find('XF:User', $userId);
            }
            $extra = [
                'level' => $newLevel,
            ];
            $alertRepo->alertFromUser($user, $user, 'user', $user->user_id, 'af_as_level_up', $extra);
        }

        return $totals;
    }

    public function getLevelFromPoints($points, $useLevelCache = true)
    {
        $levels = $this->getLevelsArr($useLevelCache);

        // \XF::dump($levels);

        return $this->getLevelRecursive($levels, $points);
    }

    public function getLevelsArr($useCache = true)
    {
        static $levels = null;

        if (isset($levels) && $useCache)
        {
            return $levels;
        }

        $levels = [];

        $options = $this->options();

        // whenever a user's points goes above this highest record, rebuild the setting value cache
        $maxPts = $options->af_as_levels_maxpoints;

        $ptsPerLevel = $options->af_as_levels_pointsper;

        $currentPts = $currentLevel = $currentModifier = 0;

        $increaseByXPoints = intval($options->af_as_levels_levelup['increase_by']);
        $everyXLevels = intval($options->af_as_levels_levelup['levels']);

        $maxLevel = $options->af_as_levels_maxlevel;

        while ($currentPts <= $maxPts)
        {
            $levels[] = [
                'l'  => $currentLevel,
                'p' => $currentPts,
            ];

            $currentPts += $ptsPerLevel;
            $currentLevel++;

            if ($maxLevel && $currentLevel > $maxLevel)
            {
                break;
            }

            // start this with the level after the one that hits increaseByXLevels
            if ($everyXLevels && $currentLevel && ($currentLevel % $everyXLevels == 0))
            {
                $ptsPerLevel += $increaseByXPoints;
            }
        }

        // add one more level from the current highest one, so we can figure out how many points are needed to reach the next level for the users that are already at the current highest
        if (!($maxLevel && $currentLevel > $maxLevel))
        {
            $levels[] = [
                'l'  => $currentLevel,
                'p' => $currentPts,
            ];
        }

        return $levels;
    }

    public function getLevelRecursive($levels, $points)
    {
        $count = count($levels);

        if ($count <= 5)
        {
            $level = 0;
            foreach ($levels as $_level)
            {
                if ($points >= $_level['p'])
                {
                    $level = $_level['l'];
                }
                else
                {
                    break;
                }
            }
            return $level;
        }

        $midpointCount = (int) floor($count / 2);
        $midpointIndex = $midpointCount - 1;
        $midpointValue = $levels[$midpointIndex]['p'];
        // var_dump($midpointIndex, $midpointValue); echo "\n\n";

        if ($points > $midpointValue)
        {
            // move to the right of it (including the midpoint level becase we're at least at that level)
            $sliced = array_slice($levels, $midpointIndex, null);
        }
        else if ($points < $midpointValue)
        {
            // move to the left of it, don't include the midpoint level because we have less points than it's mininum value
            $sliced = array_slice($levels, 0, $midpointIndex);
        }
        else
        {
            // midpoint == points
            return $levels[$midpointIndex]['l'];
        }

        // $sliced = array_slice($levels, -3);
        // print_r($sliced);

        return $this->getLevelRecursive($sliced, $points);
    }
}