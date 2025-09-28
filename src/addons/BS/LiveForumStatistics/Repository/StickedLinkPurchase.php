<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class StickedLinkPurchase extends Repository
{
    public function getTotalSticks()
    {
        $stickedLinkCount = $this->getStickedLinkRepo()
            ->findActiveLinksForList()
            ->where('end_date', '!=', 0)
            ->total();

        $stickedThreadCount = $this->getStickedThreadRepo()
            ->findStickedThreads()
            ->where('bs_lfs_sticked_end_date', '!=', 0)
            ->total();

        return $stickedLinkCount + $stickedThreadCount;
    }

    public function getLeastStickEndDate()
    {
        $linkEndDate = $this->getStickedLinkRepo()->getFreeEndDate();
        $threadEndDate = $this->getStickedThreadRepo()->getFreeEndDate();

        if ($linkEndDate === 0)
        {
            return $threadEndDate;
        }

        if ($threadEndDate === 0)
        {
            return $linkEndDate;
        }

        return min($linkEndDate, $threadEndDate);
    }

    public function findPurchasesForUser(\XF\Entity\User $user)
    {
        return $this->finder('BS\LiveForumStatistics:StickedLinkPurchase')
            ->forUser($user)
            ->setDefaultOrder('purchase_date', 'desc');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedThread */
    protected function getStickedThreadRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedThread');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedLink */
    protected function getStickedLinkRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedLink');
    }
}