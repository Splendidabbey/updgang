<?php

namespace BS\LiveForumStatistics\Repository;

use BS\LiveForumStatistics\Service\Thread\Stick;
use XF\Mvc\Entity\Repository;

class StickedThread extends Repository
{
    public function getFreeEndDate()
    {
        $endDate = array_column($this->findStickedThreads()
            ->where('bs_lfs_sticked_end_date', '!=', 0)
            ->order('bs_lfs_sticked_end_date')
            ->limit(1)
            ->fetchColumns('bs_lfs_sticked_end_date')
        , 'bs_lfs_sticked_end_date');

        return (int)reset($endDate);
    }

    public function findStickedThreadsForUser(\XF\Entity\User $user)
    {
        return $this->findStickedThreads()
            ->where('user_id', '=', $user->user_id);
    }

    public function findStickedThreads()
    {
        return $this->finder('XF:Thread')
            ->where('bs_lfs_is_sticked', '=', true);
    }

    public function unstickExpiredThreads()
    {
        $threads = $this->finder('XF:Thread')
            ->where([
                ['bs_lfs_is_sticked', '=', true],
                ['bs_lfs_sticked_end_date', '!=', 0],
                ['bs_lfs_sticked_end_date', '<', \XF::$time],
            ])
            ->fetch();

        foreach ($threads as $thread)
        {
            /** @var Stick $sticker */
            $sticker = $this->app()->service('BS\LiveForumStatistics:Thread\Stick', $thread);
            $sticker->unstick();
        }
    }

    public function clearExpiredThreadPurchases()
    {
        $this->db()->delete('xf_bs_lfs_sticked_thread_purchase', 'is_payed = ? AND create_date > ?', [false, \XF::$time - 7 * 86400]);
    }
}