<?php

namespace AddonFlare\AwardSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UserPrivacy extends XFCP_UserPrivacy
{
    protected function _setupDefaults()
    {
        parent::_setupDefaults();

        // might make this a setting later...
        $this->af_as_allow_view_profile = 'everyone';
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['af_as_allow_view_profile'] = [
            'type' => self::STR,
            'default' => 'everyone',
            'allowedValues' => ['everyone', 'members', 'followed', 'none'],
            'verify' => 'verifyPrivacyChoice'
        ];

        return $structure;
    }
}