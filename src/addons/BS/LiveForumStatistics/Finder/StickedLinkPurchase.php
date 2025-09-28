<?php

namespace BS\LiveForumStatistics\Finder;

use XF\Mvc\Entity\Finder;

class StickedLinkPurchase extends Finder
{
    public function forUser(\XF\Entity\User $user)
    {
        $this->where('user_id', '=', $user->user_id);

        return $this;
    }
}