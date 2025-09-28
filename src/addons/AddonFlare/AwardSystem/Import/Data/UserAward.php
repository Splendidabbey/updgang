<?php

namespace AddonFlare\AwardSystem\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class UserAward extends AbstractEmulatedData
{
    public function getImportType()
    {
        return 'user_award';
    }

    public function getEntityShortName()
    {
        return 'AddonFlare\AwardSystem:UserAward';
    }

    protected function preSave($oldId)
    {
        // // TODO: check if the user already has that award, if they do, skip them
        $exists = $this->db()->fetchOne('
            SELECT user_award_id
            FROM xf_af_as_user_award
            WHERE
                award_id = ?
                AND user_id = ?
                AND status = ?
        ', [$this->award_id, $this->user_id, 'approved']);

        return !$exists;
    }

    protected function postSave($oldId, $newId)
    {

    }
}