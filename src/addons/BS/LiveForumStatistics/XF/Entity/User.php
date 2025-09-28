<?php

namespace BS\LiveForumStatistics\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public function canViewLfs()
    {
        return $this->hasLfsPermission('view');
    }

    public function canHideLfs()
    {
        return $this->hasLfsPermission('hide');
    }

    public function canIgnoreThreadInLfs(\XF\Entity\Thread $thread = null)
    {
        if ($thread && $thread->bs_lfs_is_sticked)
        {
            return false;
        }

        return $this->hasLfsPermission('ignoreThreads');
    }

    public function canIgnoreForumInLfs()
    {
        return $this->hasLfsPermission('ignoreForums');
    }

    public function canStickUnstickThreadInLfs()
    {
        return $this->hasLfsPermission('stickUnstickThread');
    }

    public function canUseLfsStore()
    {
        return $this->canPurchaseLinkInLfs() || $this->canPurchaseThreadInLfs();
    }

    public function canPurchaseLinkInLfs()
    {
        if (! $this->hasLfsPermission('purchaseStickedLink'))
        {
            return false;
        }

        $options = $this->app()->options();

        if (! ($options->lfsStickedLinkCost && $options->lfsCurrency))
        {
            return false;
        }

        return true;
    }

    public function canPurchaseThreadInLfs(\XF\Entity\Thread $thread = null)
    {
        if (! $this->hasLfsPermission('purchaseStickedThread'))
        {
            return false;
        }

        $options = $this->app()->options();

        if (! ($options->lfsStickedThreadCost && $options->lfsCurrency))
        {
            return false;
        }

        if ($thread && (! $thread->isVisible() || $thread->bs_lfs_is_sticked || $thread->user_id != $this->user_id))
        {
            return false;
        }

        return true;
    }

    public function hasLfsPermission($permission)
    {
        return $this->hasPermission('lfs', $permission);
    }

    public function isIgnoringThreadInLfs($threadId)
    {
        if (! $this->user_id)
        {
            return false;
        }

        if ($threadId instanceof \XF\Entity\Thread)
        {
            $threadId = $threadId->thread_id;
        }

        if (! ($threadId && $this->Profile))
        {
            return false;
        }

        $ignoredThreads = $this->Profile->bs_lfs_ignored_threads;
        return is_array($ignoredThreads) && in_array($threadId, $ignoredThreads);
    }
}

if (false)
{
    class XFCP_User extends \XF\Entity\User {}
}