<?php

namespace BS\LiveForumStatistics\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int link_id
 * @property int sticked_order
 * @property string title
 * @property string link
 * @property array attributes
 * @property int end_date
 * @property bool is_active
 */

class StickedLink extends Entity
{
    public function getTitle()
    {
        $title = $this->title_;

        if (preg_match('#phrase:([a-z0-9_]+?)#iU', $title, $matches))
        {
            return isset($matches[1]) ? \XF::phrase($matches[1]) : $title;
        }

        return $title;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_sticked_link';
        $structure->shortName = 'BS\LiveForumStatistics:StickedLink';
        $structure->primaryKey = 'link_id';
        $structure->contentType = 'lfs_sticked_link';
        $structure->columns = [
            'link_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'sticked_order' => ['type' => self::UINT, 'default' => 0],
            'title' => ['type' => self::STR, 'maxLength' => 500, 'required' => true],
            'link' => [
                'type' => self::STR, 'required' => true,
                'match' => [
                    '#^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:\/?\#[\]@!\$&\'\(\)\*\+,;=.]+$#s',
                    'please_enter_valid_url'
                ]
            ],
            'attributes' => ['type' => self::JSON_ARRAY, 'default' => []],
            'end_date' => ['type' => self::UINT, 'default' => 0],
            'is_active' => ['type' => self::BOOL, 'default' => true]
        ];
        $structure->getters = [
            'title' => true
        ];

        return $structure;
    }
}