<?php

namespace BS\LiveForumStatistics\Entity;

use BS\LiveForumStatistics\App;
use BS\LiveForumStatistics\Entity\Concerns\HasAddOnIdOption;
use BS\LiveForumStatistics\Tab\AbstractTab;
use XF\Entity\AddOn;
use XF\Entity\Phrase;
use XF\Entity\Template;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string definition_id
 * @property string definition_class
 * @property string addon_id
 *
 * GETTERS
 * @property string title
 * @property AbstractTab renderer
 *
 * RELATIONS
 * @property AddOn AddOn
 * @property Tab[] Tabs
 * @property Phrase MasterTitle
 * @property Template OptionsTemplate
 */

class TabDefinition extends Entity
{
    use HasAddOnIdOption;

    public function isAvailable()
    {
        return $this->renderer->isAvailable();
    }

    protected function _preSave()
    {
        if (strpos($this->definition_class, ':') !== false)
        {
            $this->definition_class = \XF::stringToClass($this->definition_class, '%s\Tab\%s');
        }

        if (! class_exists($this->definition_class))
        {
            $this->error(\XF::phrase('invalid_class_x', ['class' => $this->definition_class]), 'definition_class');
        }
    }

    protected function _postSave()
    {
        $addonId = $this->getOptionAddOnId();

        if ($this->isUpdate())
        {
            if ($this->isChanged('definition_id') || $this->isChanged('addon_id'))
            {
                $titlePhrase = $this->getExistingRelation('MasterTitle');
                if ($titlePhrase)
                {
                    $titlePhrase->addon_id = $addonId;
                    $titlePhrase->title = $this->getTitlePhraseName();
                    $titlePhrase->save();
                }

                $template = $this->getExistingRelation('OptionsTemplate');
                if ($template)
                {
                    $template->addon_id = $addonId;
                    $template->title = $this->getTemplateName();
                    $template->save();
                }
            }
        }

        $this->rebuildDefinitionsCache();
    }

    protected function _postDelete()
    {
        $phrase = $this->MasterTitle;
        if ($phrase && $phrase->exists())
        {
            $phrase->delete();
        }

        $template = $this->OptionsTemplate;
        if ($template && $template->exists())
        {
            $template->delete();
        }

        foreach ($this->Tabs AS $tab)
        {
            $tab->Group->clearCache('Tabs');

            if ($tab->Group->Tabs->count() <= 1)
            {
                $tab->Group->delete();
            }

            $tab->delete();
        }

        $this->rebuildDefinitionsCache();
    }

    protected function rebuildDefinitionsCache()
    {
        \XF::runOnce('lfsTabDefinitionCache', function()
        {
            $this->getTabDefinitionRepo()->rebuildDefinitionsCache();
        });
    }

    public function getTitlePhraseName()
    {
        return 'lfs_tab_def.' . $this->definition_id;
    }

    public function getTitle()
    {
        return \XF::phrase($this->getTitlePhraseName());
    }

    public function getParamsForOptions($preset = null)
    {
        return $this->renderer->getParamsForOptions($preset);
    }

    public function getMasterTitlePhrase()
    {
        $phrase = $this->MasterTitle;
        if (! $phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getTitlePhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = $this->getOptionAddOnId();
        }

        return $phrase;
    }

    public function getOptionsTemplate()
    {
        $template = $this->OptionsTemplate;
        if (! $template)
        {
            $template = $this->_em->create('XF:Template');
            $template->title = $this->_getDeferredValue(function() { return $this->getTemplateName(); });
            $template->type = 'admin';
            $template->style_id = 0;
            $template->addon_id = $this->getOptionAddOnId();
            $template->template = '';
        }

        return $template;
    }

    public function getTemplateName()
    {
        return 'lfs_tab_def_options.' . $this->definition_id;
    }

    public function getRenderer()
    {
        return App::getTabRenderer($this->definition_class);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_tab_definition';
        $structure->shortName = 'BS\LiveForumStatistics:TabDefinition';
        $structure->primaryKey = 'definition_id';
        $structure->columns = [
            'definition_id' => ['type' => self::BINARY, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'definition_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => '']
        ];
        $structure->getters = [
            'title' => true,
            'renderer' => true
        ];
        $structure->relations = [
            'AddOn' => [
                'entity' => 'XF:AddOn',
                'type' => self::TO_ONE,
                'conditions' => 'addon_id',
                'primary' => false
            ],
            'Tabs' => [
                'entity' => 'BS\LiveForumStatistics:Tab',
                'type' => self::TO_MANY,
                'conditions' => 'definition_id',
                'primary' => false
            ],
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'lfs_tab_def.', '$definition_id']
                ]
            ],
            'OptionsTemplate' => [
                'entity' => 'XF:Template',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['style_id', '=', 0],
                    ['type', '=', 'admin'],
                    ['title', '=', 'lfs_tab_def_options.', '$definition_id']
                ]
            ]
        ];
        $structure->options = [
            'addon_id' => false
        ];

        return $structure;
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\TabDefinition
     */
    protected function getTabDefinitionRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabDefinition');
    }
}