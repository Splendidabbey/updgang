<?php

namespace BS\LiveForumStatistics\XF\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

class Account extends XFCP_Account
{
    protected function preferencesSaveProcess(\XF\Entity\User $visitor)
    {
        $form = parent::preferencesSaveProcess($visitor);

        if ($visitor->canHideLfs())
        {
            $userOptions = $visitor->getRelationOrDefault('Option');
            $form->setupEntityInput($userOptions, [
                'bs_lfs_disable' => $this->filter('option.bs_lfs_disable', 'bool')
            ]);
        }

        return $form;
    }
}

if (false)
{
    class XFCP_Account extends \XF\Pub\Controller\Account {}
}