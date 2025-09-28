<?php

namespace BS\LiveForumStatistics\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class FindThreads extends XFCP_FindThreads
{
    public function actionIgnoredInLfs()
    {
        $visitor = \XF::visitor();

        if (! (method_exists($visitor, 'canIgnoreThreadInLfs') && $visitor->canIgnoreThreadInLfs()))
        {
            return $this->noPermission();
        }

        $threadFinder = $this->getThreadRepo()->findLatestThreads()
            ->where('thread_id', $visitor->Profile->bs_lfs_ignored_threads);

        return $this->getThreadResults($threadFinder, 'ignored_in_lfs');
    }
}