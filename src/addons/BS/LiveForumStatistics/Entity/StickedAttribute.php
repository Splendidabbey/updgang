<?php

namespace BS\LiveForumStatistics\Entity;

use XF\Entity\Phrase;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null attribute_id
 * @property string attribute_key
 * @property float cost_amount
 * @property array allowable
 * @property string type
 *
 * GETTERS
 * @property string title
 *
 * RELATIONS
 * @property Phrase MasterTitle
 */

class StickedAttribute extends Entity
{
    public function isAllowValue($value)
    {
        if (! empty($this->allowable))
        {
            return in_array($value, $this->allowable);
        }

        return true;
    }

    protected function _preSave()
    {
        $exists = $this->_em->find('BS\LiveForumStatistics:StickedAttribute', $this->attribute_key);

        if ($exists && $exists !== $this)
        {
            $this->error(\XF::phrase('lfs_an_attribute_with_this_key_already_exists'), 'attribute_key');
        }
    }

    protected function _postSave()
    {
        if ($this->isUpdate())
        {
            if ($this->isChanged('attribute_key'))
            {
                $titlePhrase = $this->getExistingRelation('MasterTitle');
                if ($titlePhrase)
                {
                    $titlePhrase->title = $this->getTitlePhraseName();
                    $titlePhrase->save();
                }
            }
        }
    }

    protected function _postDelete()
    {
        $phrase = $this->MasterTitle;
        if ($phrase && $phrase->exists())
        {
            $phrase->delete();
        }
    }

    public function getMasterTitlePhrase()
    {
        $phrase = $this->MasterTitle;
        if (! $phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getTitlePhraseName(); });
            $phrase->language_id = 0;
        }

        return $phrase;
    }

    public function getTitle()
    {
        if (! $this->attribute_key)
        {
            return '';
        }

        return \XF::phrase($this->getTitlePhraseName());
    }

    public function getTypePhrase()
    {
        return \XF::phrase('lfs_attribute_type.' . $this->type);
    }

    public function getTitlePhraseName()
    {
        return 'lfs_attribute.' . $this->attribute_id;
    }
    
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_sticked_attribute';
        $structure->shortName = 'BS\LiveForumStatistics:StickedAttribute';
        $structure->primaryKey = 'attribute_id';
        $structure->columns = [
            'attribute_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'attribute_key' => ['type' => self::STR, 'maxLength' => 30, 'required' => true],
            'cost_amount' => ['type' => self::FLOAT, 'default' => 0, 'min' => 0, 'max' => 99999999.99],
            'allowable' => ['type' => self::JSON_ARRAY, 'default' => []],
            'type' => ['type' => self::STR, 'allowedValues' => ['style', 'another']]
        ];
        $structure->getters = [
            'title' => true,
            'type_phrase' => true
        ];
        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'lfs_attribute.', '$attribute_id']
                ]
            ]
        ];

        return $structure;
    }
}