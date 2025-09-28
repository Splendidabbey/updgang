<?php

namespace BS\LiveForumStatistics\Tab\Concerns;

trait ItemOptions
{
    protected function verifyItemOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        if ($request->filter('post_date_filter', 'bool'))
        {
            $cutOff = $options['cut_off'];
            $cutOff[1] = (int)$cutOff[0];

            if (! in_array($cutOff[0], ['<', '>']) || $cutOff[1] < 1)
            {
                $options['cut_off'] = $this->defaultOptions['cut_off'];
            }
        }

        $itemPrefix = $this->itemPrefix;
        
        $itemIdsKey    = $itemPrefix . '_ids';
        $itemNotIdsKey = 'not_' . $itemPrefix . '_ids';

        if (! empty($options[$itemIdsKey]))
        {
            $options[$itemIdsKey] = array_map('trim', explode(',', $options[$itemIdsKey]));
        }

        if (! empty($options[$itemNotIdsKey]))
        {
            $options[$itemNotIdsKey] = array_map('trim', explode(',', $options[$itemNotIdsKey]));
        }

        return true;
    }
}