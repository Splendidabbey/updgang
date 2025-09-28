<?php

namespace AddonFlare\AwardSystem\XF\Repository;

class UserAlert extends XFCP_UserAlert
{
    // necessary because deleteAlertsInternal() is protected so we have to extend the UserAlert repo
    public function fastDeleteAlertsToUserWithDate($toUserId, $date, $contentType, $contentId, $action)
    {
        $finder = $this->finder('XF:UserAlert')
            ->where([
                'event_date' => $date,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'action' => $action,
                'alerted_user_id' => $toUserId
            ]);

        $this->deleteAlertsInternal($finder);
    }
}