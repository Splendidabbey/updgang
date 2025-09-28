<?php

namespace BS\LiveForumStatistics\XF\Finder;

class Thread extends XFCP_Thread
{
    public function skipIgnoredThreadsInLfs(\XF\Entity\User $user = null, $tabId = '')
    {
        if (! $user)
        {
            $user = \XF::visitor();
        }

        if (! $user->user_id)
        {
            return $this;
        }

        $profile = $user->Profile;

        if (! $profile)
        {
            return $this;
        }

        if ($profile->bs_lfs_ignored_threads)
        {
            $this->where('thread_id', '<>', $profile->bs_lfs_ignored_threads);
        }

        if ($tabId && ($ignoredForums = $profile->bs_lfs_ignored_forums[$tabId] ?? null))
        {
            $this->where('node_id', '<>', $ignoredForums);
        }

        return $this;
    }
}

if (false)
{
    class XFCP_Thread extends \XF\Finder\Thread {}
}