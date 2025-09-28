<?php

namespace AddonFlare\AwardSystem\Option;

use XF\Util\Arr;

class LevelColors extends \XF\Option\AbstractOption
{
    public static function render(\XF\Entity\Option $option, array $htmlParams)
    {
        $choices = [];
        foreach ($option->option_value as $level => $color)
        {
            $choices[] = [
                'level' => $level,
                'color' => $color,
            ];
        }

        return self::getTemplate('admin:option_af_as_level_colors', $option, $htmlParams, [
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
                'color' => 'str',
                'remove' => 'bool',
            ]);

            if (!$inputGroup['level'] || !$inputGroup['color'] || !empty($inputGroup['remove']))
            {
                continue;
            }

            $output[$inputGroup['level']] = $inputGroup['color'];
        }

        // sort by level
        ksort($output, SORT_NUMERIC);

        // set the reference value
        $value = $output;

        $styleRepo = \XF::repository('XF:Style');
        $styleRepo->updateAllStylesLastModifiedDateLater();

        return true;
    }
}