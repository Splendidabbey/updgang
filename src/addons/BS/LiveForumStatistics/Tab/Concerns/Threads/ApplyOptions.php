<?php

namespace BS\LiveForumStatistics\Tab\Concerns\Threads;

trait ApplyOptions
{
    protected function applyThreadOptions(\XF\Finder\Thread $finder, array $options)
    {
        $nodeIds = $options['exclude_node_ids'];

        if ($nodeIds && ! in_array(0, $nodeIds))
        {
            $finder->where('node_id', '<>', $nodeIds);
        }

        if ($options['prefix_ids'] && reset($options['prefix_ids']) !== -1)
        {
            $finder->where('prefix_id', $options['prefix_ids']);
        }

        if ($options['exclude_prefix_ids'])
        {
            $finder->where('prefix_id', '<>', $options['exclude_prefix_ids']);
        }

        if ($options['discussion_open'] != '')
        {
            $finder->where('discussion_open', '=', !(bool)$options['discussion_open']);
        }

        if ($options['sticky'] != '')
        {
            $finder->where('sticky', '=', (bool)$options['sticky']);
        }

        $cutOff = $options['cut_off'];

        if ($cutOff && $cutOff[1] > 0)
        {
            $finder->where('post_date', $cutOff[0], \XF::$time - $cutOff[1] * 86400);
        }

        if ($options['thread_ids'])
        {
            $finder->where('thread_id', $options['thread_ids']);
        }

        if ($options['not_thread_ids'])
        {
            $finder->where('thread_id', '<>', $options['not_thread_ids']);
        }
    }
}