<?php

namespace BS\LiveForumStatistics;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function entityPostDeleteForum(Entity $forum)
    {
        $forum->db()->delete('xf_bs_lfs_user_forum_ignored', 'node_id = ?', $forum->node_id);
    }

    public static function entityPostDeleteThread(Entity $thread)
    {
        $thread->db()->delete('xf_bs_lfs_sticked_thread_purchase', 'thread_id = ?', $thread->thread_id);
    }

    public static function entityStructureThread(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns += [
            'bs_lfs_is_sticked' => ['type' => Entity::BOOL, 'default' => false],
            'bs_lfs_sticked_order' => ['type' => Entity::UINT, 'default' => 0],
            'bs_lfs_sticked_end_date' => ['type' => Entity::UINT, 'default' => 0],
            'bs_lfs_sticked_attributes' => ['type' => Entity::JSON_ARRAY, 'nullable' => true]
        ];
    }

    public static function entityStructureUserOption(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns += [
            'bs_lfs_disable' => ['type' => Entity::BOOL, 'default' => false]
        ];
    }

    public static function entityStructureUserProfile(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns += [
            'bs_lfs_ignored_threads' => ['type' => Entity::JSON_ARRAY, 'default' => [], 'changeLog' => false],
            'bs_lfs_ignored_forums' => ['type' => Entity::JSON_ARRAY, 'default' => [], 'changeLog' => false]
        ];
    }

    // AMS old versions compatibility
    public static function entityStructureArticleItem(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        if (! isset($structure->withAliases['full']))
        {
            $structure->withAliases['full'] = [
                'User',
                'Featured',
                'CoverImage',
                function()
                {
                    $userId = \XF::visitor()->user_id;
                    if ($userId)
                    {
                        return ['Read|' . $userId, 'Watch|' . $userId];
                    }

                    return null;
                }
            ];
        }
    }
}