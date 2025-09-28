<?php

namespace BS\LiveForumStatistics\Option;

use XF\Option\AbstractOption;

class PaymentProfiles extends AbstractOption
{
    public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
    {
        $profiles = \XF::repository('XF:Payment')
            ->findPaymentProfilesForList()
            ->fetch();

        return self::getTemplate('admin:option_template_lfsPaymentProfiles', $option, $htmlParams, compact('profiles'));
    }
}