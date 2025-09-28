<?php

namespace BS\LiveForumStatistics\Tab\Concerns;

trait UserOptions
{
    protected function verifyUserOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        if (! empty($options['by_user']))
        {
            if ($options['by_user'] != '{visitor}')
            {
                $userIds = $this->getUserIdsFromCommaUsernames($options['by_user']);

                if (! $userIds)
                {
                    $error = \XF::phrase('requested_user_not_found');
                    return false;
                }

                $options['by_user'] = $userIds;
            }
        }

        if (! empty($options['user_is_not']))
        {
            $options['user_is_not'] = trim($options['user_is_not']);

            if ($options['user_is_not'] != '{visitor}')
            {
                $userIds = $this->getUserIdsFromCommaUsernames($options['user_is_not']);

                if (! $userIds)
                {
                    $error = \XF::phrase('requested_user_not_found');
                    return false;
                }

                $options['user_is_not'] = $userIds;
            }
        }

        if (! empty($options['language_ids']))
        {
            $options['language_ids'] = array_filter((array)$options['language_ids'], function($v)
            {
                return is_numeric($v);
            });

            if (empty($options['language_ids']))
            {
                $options['language_ids'] = [-1];
            }
        }

        return true;
    }

    protected function applyUserOptions(\XF\Mvc\Entity\Finder $finder, array $options)
    {
        if ($options['by_user'] == '{visitor}')
        {
            $finder->where('user_id', \XF::visitor()->user_id);
        }
        else if ($options['by_user'])
        {
            $finder->where('user_id', $options['by_user']);
        }

        if ($options['user_is_not'] == '{visitor}')
        {
            $finder->where('user_id', '<>', \XF::visitor()->user_id);
        }
        else if ($options['user_is_not'])
        {
            $finder->where('user_id', '<>', $options['user_is_not']);
        }

        if (! empty($options['user_has_groups']))
        {
            $userGroupWhereOr = [
                ['User.user_group_id', '=', $options['user_has_groups']]
            ];

            foreach ($options['user_has_groups'] as $groupId)
            {
                $userGroupWhereOr[] = $finder->expression(
                    'FIND_IN_SET(' . $finder->quote($groupId) . ', %s)',
                    'User.secondary_group_ids'
                );
            }

            $finder->whereOr($userGroupWhereOr);
        }

        if (! empty($options['user_has_not_groups']))
        {
            $notUserGroupWhereOr = [
                ['User.user_group_id', '<>', $options['user_has_not_groups']]
            ];

            foreach ($options['user_has_not_groups'] as $groupId)
            {
                $notUserGroupWhereOr[] = $finder->expression(
                    'NOT FIND_IN_SET(' . $finder->quote($groupId) . ', %s)',
                    'User.secondary_group_ids'
                );
            }

            $finder->where($notUserGroupWhereOr);
        }

        if ($options['watched'])
        {
            $finder->watchedOnly();
        }
    }

    protected function userOptionsParams(&$options)
    {
        if (is_array($options['by_user']))
        {
            switch ($options['by_user'])
            {
                case '{visitor}':
                    break;

                default:
                    $options['by_user'] = implode(', ', $this->getUserNamesByIds($options['by_user']));
                    break;
            }
        }

        if (is_array($options['user_is_not']))
        {
            switch ($options['user_is_not'])
            {
                case '{visitor}':
                    break;

                default:
                    $options['user_is_not'] = implode(', ', $this->getUserNamesByIds($options['user_is_not']));
                    break;
            }
        }
    }

    protected function getUserIdsFromCommaUsernames($usernames)
    {
        return array_column($this->finder('XF:User')
            ->isValidUser()
            ->where('username', array_map('trim', explode(',', $usernames)))
            ->fetchColumns('user_id')
        , 'user_id');
    }

    protected function getUserNamesByIds($userIds)
    {
        return array_column($this->finder('XF:User')
            ->isValidUser()
            ->where('user_id', $userIds)
            ->fetchColumns('username')
        , 'username');
    }
}