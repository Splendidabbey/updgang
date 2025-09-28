<?php

namespace AddonFlare\AwardSystem\Alert;

use XF\Mvc\Entity\Entity;

class Award extends \XF\Alert\AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        return true;
    }
}
