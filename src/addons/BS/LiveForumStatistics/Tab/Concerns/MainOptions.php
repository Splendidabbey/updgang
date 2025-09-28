<?php

namespace BS\LiveForumStatistics\Tab\Concerns;

trait MainOptions
{
    protected function verifyMainOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options['order'] = array_filter(array_unique(array_values($options['order']), SORT_REGULAR),
            function ($value)
            {
                return ! empty($value[0]);
            }
        );

        $limit = isset($options['limit']) ? (int)$options['limit'] : $this->defaultOptions['limit'];

        if ($limit < 1)
        {
            $limit = 1;
        }

        $options['limit'] = $limit;

        return true;
    }
}