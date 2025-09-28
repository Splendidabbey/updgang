<?php

namespace BS\LiveForumStatistics\Entity;

use BS\LiveForumStatistics\Repository\UserIgnored;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int thread_id
 *
 * RELATIONS
 * @property \XF\Entity\Thread Thread
 */

class UserThreadIgnored extends Entity
{
    protected function _preSave()
    {
        if ($this->isInsert())
        {
            if ($this->Thread->bs_lfs_is_sticked)
            {
                $this->error(\XF::phrase('lfs_you_cannot_ignore_sticked_threads'));
            }

            $exists = $this->em()->findOne('BS\LiveForumStatistics:UserThreadIgnored', [
                'user_id' => $this->user_id,
                'thread_id' => $this->thread_id
            ]);
            if ($exists)
            {
                $this->error(\XF::phrase('lfs_you_already_ignoring_this_thread'));
            }

            $ignoredFinder = $this->finder('BS\LiveForumStatistics:UserThreadIgnored');
            $total = $ignoredFinder
                ->where('user_id', $this->user_id)
                ->total();

            $ignoredLimit = 1000;
            if ($total >= $ignoredLimit)
            {
                $this->error(\XF::phrase('lfs_you_may_only_ignore_x_threads', ['count' => $ignoredLimit]));
            }
        }
    }

    protected function _postSave()
    {
        $this->rebuildIgnoredThreadCache();
    }

    protected function _postDelete()
    {
        $this->rebuildIgnoredThreadCache();
    }

    protected function rebuildIgnoredThreadCache()
    {
        $this->getIgnoredRepo()->rebuildIgnoredThreadCache($this->user_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_user_thread_ignored';
        $structure->shortName = 'BS\LiveForumStatistics:UserThreadIgnored';
        $structure->primaryKey = ['user_id', 'thread_id'];
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'thread_id' => ['type' => self::UINT, 'required' => true]
        ];
        $structure->relations = [
            'Thread' => [
                'entity' => 'XF:Thread',
                'type' => self::TO_ONE,
                'conditions' => 'thread_id',
                'primary' => false
            ]
        ];

        return $structure;
    }

    /** @return UserIgnored */
    protected function getIgnoredRepo()
    {
        return $this->repository('BS\LiveForumStatistics:UserIgnored');
    }
}