<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class Tab extends Repository
{
    public function findTabsForList()
    {
        return $this->finder('BS\LiveForumStatistics:Tab');
    }
}