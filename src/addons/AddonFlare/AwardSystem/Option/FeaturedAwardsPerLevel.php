<?php

namespace AddonFlare\AwardSystem\Option;

use XF\Util\Arr;

class FeaturedAwardsPerLevel extends \XF\Option\AbstractOption
{
    public static function render(\XF\Entity\Option $option, array $htmlParams)
    {
        $choices = [];
        foreach ($option->option_value as $level => $featured)
        {
            $choices[] = [
                'level' => $level,
                'featured' => $featured,
            ];
        }

        return self::getTemplate('admin:option_af_as_max_featured_awards_per_level', $option, $htmlParams, [
            'choices'       => $choices,
            'nextCounter'   => count($choices) + 1,
        ]);
    }

    public static function verifyOption(array &$value, \XF\Entity\Option $option)
    {
        $output = [];

        $inputGroups = $value;

        // remove empty usernames or the ones that have checked remove
        foreach ($inputGroups as $inputGroupKey => $inputGroup)
        {
            $inputGroup = \XF::app()->inputFilterer()->filterArray($inputGroup, [
                'level' => 'uint',
                'featured' => 'uint',
                'remove' => 'bool',
            ]);

            if (!$inputGroup['level'] || !$inputGroup['featured'] || !empty($inputGroup['remove']))
            {
                continue;
            }

            $output[$inputGroup['level']] = $inputGroup['featured'];
        }

        // sort by level
        ksort($output, SORT_NUMERIC);

        // set the reference value
        $value = $output;

        return true;
    }
}