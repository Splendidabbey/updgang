<?php
### NOT USED FOR NOW ###
namespace AddonFlare\AwardSystem\Option;

use XF\Option\AbstractOption;
use XF\Mvc\Entity\Entity;

class UserField extends \XF\Option\AbstractOption
{
    public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams)
    {
        $editableUserFields = \XF::finder('XF:UserField')
            ->where('user_editable', ['once', 'yes'])
            ->order([['display_group', 'ASC'], ['display_order', 'ASC']])
            ->fetch()->pluckNamed('title', 'field_id');

		$choices = [];

        foreach ($editableUserFields as $fieldId => $label)
        {
            $choices[$fieldId] = [
                'value' => $fieldId,
                'label' => $label,
                '_type' => 'option',
            ];
            if ($type !== null)
            {
                $choices[$fieldId]['_type'] = $type;
            }
        }

        $choices = array_map(function($v) {
            $v['label'] = \XF::escapeString($v['label']);
            return $v;
        }, $choices);

        return [
            'choices' => $choices,
            'controlOptions' => self::getControlOptions($option, $htmlParams),
            'rowOptions' => self::getRowOptions($option, $htmlParams)
        ];
    }
}