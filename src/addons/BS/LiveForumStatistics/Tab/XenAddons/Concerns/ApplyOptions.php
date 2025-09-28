<?php

namespace BS\LiveForumStatistics\Tab\XenAddons\Concerns;

trait ApplyOptions
{
    protected function applyItemOptions(\XF\Mvc\Entity\Finder $finder, array $options)
    {
        $categoryIds = $options['exclude_category_ids'];

        if ($categoryIds && ! in_array(0, $categoryIds))
        {
            $finder->where('category_id', '<>', $categoryIds);
        }

        if ($options['prefix_ids'] && reset($options['prefix_ids']) !== -1)
        {
            $finder->where('prefix_id', $options['prefix_ids']);
        }

        if ($options['exclude_prefix_ids'])
        {
            $finder->where('prefix_id', '<>', $options['exclude_prefix_ids']);
        }

        if ($options['comments_open'] != '')
        {
            $finder->where('comments_open', '=', (bool)$options['comments_open']);
        }

        if ($options['featured'] != '')
        {
            $finder->where('Featured.feature_date', ((bool)$options['featured'] ? '!=' : '='), null);
        }

        $cutOff = $options['cut_off'];

        if ($cutOff && $cutOff[1] > 0)
        {
            $finder->where($this->createDateColumn, $cutOff[0], \XF::$time - $cutOff[1] * 86400);
        }

        $itemPrefix = $this->itemPrefix;

        $itemIdColumn  = $itemPrefix . '_id';
        $itemIdsKey    = $itemPrefix . '_ids';
        $itemNotIdsKey = 'not_' . $itemPrefix . '_ids';

        if ($options[$itemIdsKey])
        {
            $finder->where($itemIdColumn, $options[$itemIdsKey]);
        }

        if ($options[$itemNotIdsKey])
        {
            $finder->where($itemIdColumn, '<>', $options[$itemNotIdsKey]);
        }
    }
}