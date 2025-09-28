<?php

namespace BS\LiveForumStatistics\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class StickedLinkPurchase extends AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        return true;
    }
}