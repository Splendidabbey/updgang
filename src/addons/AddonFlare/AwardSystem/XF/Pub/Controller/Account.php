<?php

namespace AddonFlare\AwardSystem\XF\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

class Account extends XFCP_Account
{
    protected function savePrivacyProcess(\XF\Entity\User $visitor)
    {
        $form = parent::savePrivacyProcess($visitor);

        $input = $this->filter([
            'privacy' => [
                'af_as_allow_view_profile' => 'str'
            ]
        ]);

        $userPrivacy = $visitor->getRelationOrDefault('Privacy');
        $form->setupEntityInput($userPrivacy, $input['privacy']);

        return $form;
    }
}