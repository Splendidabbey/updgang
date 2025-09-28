<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class StickedAttribute extends Repository
{
    public function findAttributesForList()
    {
        return $this->finder('BS\LiveForumStatistics:StickedAttribute');
    }

    public function getFinalAttributesAndCost($attributes, $input, &$error = null)
    {
        $attrs = ['style' => ''];
        $cost = 0;

        /** @var \BS\LiveForumStatistics\Entity\StickedAttribute $attribute */
        foreach ($attributes as $attribute)
        {
            $value = $input[$attribute->attribute_id] ?? null;
            if ($value)
            {
                if (! $attribute->isAllowValue($value))
                {
                    $error = \XF::phrase('lfs_attribute_x_has_an_invalid_value', ['attribute' => $attribute->title]);
                    break;
                }

                if ($attribute->type == 'style')
                {
                    $attrs['style'] .= $this->prepareStyleAttributeValue($value);
                }
                else
                {
                    $attrs[$attribute->attribute_key] = $value;
                }

                if ($attribute->cost_amount)
                {
                    $cost += $attribute->cost_amount;
                }
            }
        }

        return [$attrs, $cost];
    }

    protected function prepareStyleAttributeValue($value)
    {
        return substr($value, -1) !== ';'
            ? $value . ';'
            : $value;
    }
}