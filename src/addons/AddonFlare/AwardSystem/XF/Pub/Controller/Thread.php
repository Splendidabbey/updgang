<?php

namespace AddonFlare\AwardSystem\XF\Pub\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Thread extends XFCP_Thread
{
    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);

        if ($reply instanceof \XF\Mvc\Reply\View && ($posts = $reply->getParam('posts')))
        {
            $awardRepo = $this->repository('AddonFlare\AwardSystem:UserAward');
            $awardRepo->addUserAwardsToContent($posts);
        }

        return $reply;
    }
}