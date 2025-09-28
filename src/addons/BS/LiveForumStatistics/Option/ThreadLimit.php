<?php

namespace BS\LiveForumStatistics\Option;

use XF\Option\AbstractOption;

class ThreadLimit extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $limits = [];

        if (! empty($option->option_value['custom_limits']))
        {
            foreach ($option->option_value['custom_limits'] AS $limit)
            {
                $limits[] = $limit;
            }
        }

        return self::getTemplate('admin:option_template_lfsThreadLimit', $option, $htmlParams, [
            'limits' => $limits,
            'nextCounter' => count($limits)
        ]);
    }

    public static function verifyOption(array &$value)
    {
        if (! empty($value['enable_custom_limit']))
        {
            $value['enable_custom_limit'] = (bool)$value['enable_custom_limit'];

            if ($value['enable_custom_limit'])
            {
                $value['custom_limits'] = array_map(function($val)
                {
                    return $val ? (int)$val : null;
                }, $value['custom_limits']);

                $value['custom_limits'] = array_filter($value['custom_limits'], function($val)
                {
                    return !empty($val) && $val > 0 && $val <= 500;
                });

                $value['enable_custom_limit'] = !empty($value['custom_limits']);
            }
        }

        return true;
    }
}