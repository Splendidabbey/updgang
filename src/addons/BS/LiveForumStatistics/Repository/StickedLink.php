<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class StickedLink extends Repository
{
    public function getFreeEndDate()
    {
        $endDate = array_column($this->findActiveLinksForList()
            ->where('end_date', '!=', 0)
            ->order('end_date')
            ->limit(1)
            ->fetchColumns('end_date')
        , 'end_date');

        return (int)reset($endDate);
    }

    public function disableExpiredLinks()
    {
        $this->db()->update('xf_bs_lfs_sticked_link', [
            'is_active' => 0
        ], 'end_date != 0 AND end_date < ?', \XF::$time);
    }

    public function findActiveLinksForList()
    {
        return $this->findLinksForList()
            ->where('is_active', '=', true);
    }

    public function findLinksForList()
    {
        return $this->finder('BS\LiveForumStatistics:StickedLink')
            ->setDefaultOrder('sticked_order', 'ASC');
    }
}