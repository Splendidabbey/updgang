<?php

namespace BS\LiveForumStatistics\Cron;

use BS\LiveForumStatistics\Repository\StickedLink;
use BS\LiveForumStatistics\Repository\StickedThread;

class Sticks
{
    public static function disableLinks()
    {
        self::getStickedLinkRepo()->disableExpiredLinks();
    }

    public static function clearThreads()
    {
        self::getStickedThreadRepo()->unstickExpiredThreads();
    }

    public static function clearThreadPurchases()
    {
        self::getStickedThreadRepo()->clearExpiredThreadPurchases();
    }

    /**
     * @return StickedLink
     */
    protected static function getStickedLinkRepo()
    {
        return \XF::repository('BS\LiveForumStatistics:StickedLink');
    }

    /**
     * @return StickedThread
     */
    protected static function getStickedThreadRepo()
    {
        return \XF::repository('BS\LiveForumStatistics:StickedThread');
    }
}