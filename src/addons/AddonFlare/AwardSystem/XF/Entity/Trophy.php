<?php

namespace AddonFlare\AwardSystem\XF\Entity;

use XF\Mvc\Entity\Structure;

class Trophy extends XFCP_Trophy
{
    /* Not needed anymore as of version 1.4 since we don't link to trophies
    protected function _postDelete()
    {
        $awards = $this->finder('AddonFlare\AwardSystem:Award')
            ->where('award_trophy_id', $this->trophy_id)
            ->fetch();

        foreach ($awards as $award)
        {
            $award->award_trophy_id = 0;
            $award->save();
        }

        parent::_postDelete();
    }
    */
}