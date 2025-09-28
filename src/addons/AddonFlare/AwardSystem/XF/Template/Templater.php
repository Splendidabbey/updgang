<?php

namespace AddonFlare\AwardSystem\XF\Template;

use AddonFlare\AwardSystem\Listener;
use AddonFlare\AwardSystem\IDs;

class Templater extends XFCP_Templater
{
    public static $afAwards = false;
    public static function getAfAwards($key)
    {
        if (!self::$afAwards)
        {
            \XF::app()->templater()->addAfAwards($key);
            self::$afAwards = false;
        }

        return $key;
    }
    public function addAfAwards($key)
    {
        static $complete = true;
        $awards = IDs::getSetC(2, null, 0);

        $prefix = IDs::$prefix;

        $f = function() use(&$complete, $awards)
        {
            if (!$complete)
            {
                $complete = $this->{$awards}[] = IDs::getF();
            }
        };

        return (IDs::$prefix($this)) ? ($this) : $f($this);
    }

    public function fnCopyright($templater, &$escape)
    {
        $return = parent::fnCopyright($templater, $escape);
        IDs::CR($templater, $return);
        return $return;
    }
}