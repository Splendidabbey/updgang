<?php

namespace AddonFlare\AwardSystem\Job;

class UserAwardTotalRebuild extends \XF\Job\AbstractRebuildJob
{
    protected $userAwardRepo;

    protected $defaultData = [
        'awardIds' => [], // default to all
    ];

    protected function setupData(array $data)
    {
        $this->userAwardRepo = \XF::repository('AddonFlare\AwardSystem:UserAward');

        return parent::setupData($data);
    }

    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        $awardIdsCond = '';
        if ($this->data['awardIds'])
        {
            $awardIdsCond = 'AND award_id IN (' . $db->quote($this->data['awardIds']) . ')';
        }

        return $db->fetchAllColumn($db->limit(
            "
                SELECT user_id
                FROM xf_af_as_user_award
                WHERE
                    user_id > ?
                $awardIdsCond
                GROUP BY user_id
                ORDER BY user_id
            ", $batch
        ), [$start]);
    }

    protected function rebuildById($id)
    {
        $this->userAwardRepo->rebuildUserAwardTotals($id);
    }

    protected function getStatusType()
    {
        return \XF::phrase('users');
    }
}