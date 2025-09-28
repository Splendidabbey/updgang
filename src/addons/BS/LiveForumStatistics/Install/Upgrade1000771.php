<?php

namespace BS\LiveForumStatistics\Install;

trait Upgrade1000771
{
    protected function rebuildUserForumIgnored($position, array $stepData)
    {
        $perPage = 50;

        $db = \XF::db();
        $db->beginTransaction();

        if (! isset($stepData['max']))
        {
            $stepData['max'] = $db->fetchOne('
                SELECT MAX(ignored.user_id)
                FROM xf_bs_lfs_user_forum_ignored as ignored
                GROUP BY ignored.user_id
                ORDER BY ignored.user_id
            ');
        }

        $userIds = $db->fetchAllColumn($db->limit('
                SELECT ignored.user_id
                FROM xf_bs_lfs_user_forum_ignored as ignored
                WHERE ignored.user_id > ?
                GROUP BY ignored.user_id
                ORDER BY ignored.user_id
            ', $perPage)
        , $position);

        if (! $userIds)
        {
            $db->commit();
            return true;
        }

        /** @var \BS\LiveForumStatistics\Repository\UserIgnored $ignoreRepo */
        $ignoreRepo = $this->app->repository('BS\LiveForumStatistics:UserIgnored');

        $next = 0;

        foreach ($userIds as $userId)
        {
            $next = $userId;

            $cache = $ignoreRepo->getIgnoredUserForumCache($userId);

            $db->update('xf_user_profile', [
                'bs_lfs_ignored_forums' => json_encode($cache)
            ], 'user_id = ?', $userId);
        }

        $db->commit();

        return [
            $next,
            "{$next} / {$stepData['max']}",
            $stepData
        ];
    }
}