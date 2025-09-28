<?php

namespace BS\LiveForumStatistics\Tab\Concerns;

trait ViewPermissions
{
    public function canViewByUser()
    {
        $options = $this->options;

        $visitor = \XF::visitor();

        if ($options['by_user'] === '{visitor}')
        {
            if (! $visitor->user_id)
            {
                return false;
            }
        }
        else if ($options['by_user'])
        {
            if ($visitor->isIgnoring($options['by_user']))
            {
                return false;
            }
        }

        return true;
    }

    public function canViewByLanguage()
    {
        $options = $this->options;

        $visitor = \XF::visitor();

        $languageIds = $options['language_ids'] ?? [-1];

        if (! (in_array(-1, $languageIds) || in_array($visitor->language_id, $languageIds)))
        {
            return false;
        }

        return true;
    }
}