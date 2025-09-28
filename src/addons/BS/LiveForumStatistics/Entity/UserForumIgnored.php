<?php

namespace BS\LiveForumStatistics\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_id
 * @property int node_id
 * @property string tab_id
 *
 * RELATIONS
 * @property \XF\Entity\Forum Forum
 */

class UserForumIgnored extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_user_forum_ignored';
        $structure->shortName = 'BS\LiveForumStatistics:UserForumIgnored';
        $structure->primaryKey = ['node_id', 'user_id', 'tab_id'];
        $structure->columns = [
            'user_id' => ['type' => self::UINT,   'required' => true],
            'node_id' => ['type' => self::UINT,   'required' => true],
            'tab_id'  => ['type' => self::BINARY, 'required' => true]
        ];
        $structure->relations = [
            'Forum' => [
                'entity' => 'XF:Forum',
                'type' => self::TO_ONE,
                'conditions' => 'node_id',
                'primary' => false
            ]
        ];

        return $structure;
    }
}