<?php

namespace AddonFlare\AwardSystem\XF\Pub\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Conversation extends XFCP_Conversation
{
    public function actionView(ParameterBag $params)
    {
        $reply = parent::actionView($params);

        if ($reply instanceof \XF\Mvc\Reply\View && ($messages = $reply->getParam('messages')))
        {
            $awardRepo = $this->repository('AddonFlare\AwardSystem:UserAward');
            $awardRepo->addUserAwardsToContent($messages);
        }

        return $reply;
    }
}