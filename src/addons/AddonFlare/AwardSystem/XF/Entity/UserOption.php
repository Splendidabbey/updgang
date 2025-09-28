<?php

namespace AddonFlare\AwardSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UserOption extends XFCP_UserOption
{
    protected function _setupDefaults()
    {
        parent::_setupDefaults();

        $options = \XF::options();

        // make sure the option already exists
        if (isset($options->af_as_registrationDefaults['af_as_auto_feature']))
        {
            $defaults = $options->af_as_registrationDefaults;
            $this->af_as_auto_feature = $defaults['af_as_auto_feature'] ? true : false;
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['af_as_auto_feature'] = ['type' => self::BOOL, 'default' => true];

        return $structure;
    }
}