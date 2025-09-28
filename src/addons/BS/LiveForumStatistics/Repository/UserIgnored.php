<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class UserIgnored extends Repository
{
    public function getIgnoredUserThreadCache($userId)
    {
        return $this->db()->fetchAllColumn('
			SELECT ignored.thread_id
			FROM xf_bs_lfs_user_thread_ignored AS ignored
			WHERE ignored.user_id = ?
		', $userId);
    }

    public function rebuildIgnoredThreadCache($userId)
    {
        $cache = $this->getIgnoredUserThreadCache($userId);

        $profile = $this->em->find('XF:UserProfile', $userId);
        if ($profile)
        {
            $profile->fastUpdate('bs_lfs_ignored_threads', $cache);
        }

        return $cache;
    }

    public function replaceIgnoredUserForum($userId, $forumIds, $tabId)
    {
        $db = $this->db();

        $db->delete('xf_bs_lfs_user_forum_ignored', 'user_id = ? AND tab_id = ?', [$userId, $tabId]);

        $rows = [];

        foreach ($forumIds as $forumId)
        {
            $rows[] = [
                'user_id'  => $userId,
                'node_id'  => $forumId,
                'tab_id'   => $tabId
            ];
        }

        if (! empty($rows))
        {
            $db->insertBulk('xf_bs_lfs_user_forum_ignored', $rows);
        }

        $this->rebuildIgnoredForumCache($userId);
    }

    public function getIgnoredUserForumCache($userId)
    {
        $cache = [];

        $ignoredForums = $this->finder('BS\LiveForumStatistics:UserForumIgnored')
            ->where('user_id', '=', $userId)
            ->fetch();

        /** @var \BS\LiveForumStatistics\Entity\UserForumIgnored $ignoredForum */
        foreach ($ignoredForums as $ignoredForum)
        {
            $cache[$ignoredForum->tab_id][] = $ignoredForum->node_id;
        }

        return $cache;
    }

    public function rebuildIgnoredForumCache($userId)
    {
        $cache = $this->getIgnoredUserForumCache($userId);

        $profile = $this->em->find('XF:UserProfile', $userId);
        if ($profile)
        {
            $profile->fastUpdate('bs_lfs_ignored_forums', $cache);
        }

        return $cache;
    }
}