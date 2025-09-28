<?php

namespace BS\LiveForumStatistics\Entity;

use BS\LiveForumStatistics\Entity\Concerns\HasAddOnIdOption;
use XF\Entity\AddOn;
use XF\Entity\Phrase;
use XF\Entity\Template;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string group_id
 * @property int display_order
 * @property int carousel_interval
 * @property string addon_id
 * @property boolean is_active
 *
 * GETTERS
 * @property string title
 * @property string group_class
 * @property Tab[] TabsViewable
 *
 * RELATIONS
 * @property AddOn AddOn
 * @property Phrase MasterTitle
 * @property Template MasterTemplate
 * @property Tab[] Tabs
 */

class TabGroup extends Entity
{
    use HasAddOnIdOption;

    public function canView()
    {
        return (bool)$this->TabsViewable->count();
    }

    public function render($finalRender = true, \XF\Http\Request $request = null)
    {
        $tab = $this->getFirstRenderTab($request);

        return $tab->render($finalRender, $request);
    }

    public function isFirstSelected(Tab $testTab, \XF\Http\Request $request = null)
    {
        $tab = $this->getFirstRenderTab($request);

        return ($tab->tab_id === $testTab->tab_id);
    }

    /** @return Tab */
    public function getFirstRenderTab(\XF\Http\Request $request = null)
    {
        $tab = $this->TabsViewable->first();

        if ($request)
        {
            $tabId = $request->getCookie("lfs_group_{$this->group_id}_selected_tab");

            if ($tabId && isset($this->TabsViewable[$tabId]))
            {
                $tab = $this->TabsViewable[$tabId];
            }
        }

        return $tab;
    }

    public function getTabsViewable()
    {
        $onlyTabs = $this->getOption('only_tabs');

        return $this->Tabs->filterViewable()->filter(function (Tab $tab) use ($onlyTabs)
        {
            return in_array($tab->tab_id, $onlyTabs);
        });
    }

    public function getTitle()
    {
        return \XF::phrase($this->getTitlePhraseName());
    }

    public function getGroupClass()
    {
        return "tabGroup--{$this->group_id}";
    }

    protected function _postSave()
    {
        $addonId = $this->getOptionAddOnId();

        if ($this->isInsert())
        {
            $template = $this->getMasterTemplate();
            if (! $template->exists())
            {
                $template->save();
            }
        }
        else if ($this->isUpdate())
        {
            if ($this->isChanged('group_id') || $this->isChanged('addon_id'))
            {
                $titlePhrase = $this->getExistingRelation('MasterTitle');
                if ($titlePhrase)
                {
                    $titlePhrase->addon_id = $addonId;
                    $titlePhrase->title = $this->getTitlePhraseName();
                    $titlePhrase->save();
                }

                $template = $this->getExistingRelation('MasterTemplate');
                if ($template)
                {
                    $template->addon_id = $addonId;
                    $template->title = $this->getTemplateName();
                    $template->save();
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

        $template = $this->getMasterTemplate();
        if ($template && $template->exists())
        {
            $template->delete();
        }

        foreach ($this->Tabs AS $tab)
        {
            $tab->delete();
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
            $phrase->addon_id = $this->getOptionAddOnId();
        }

        return $phrase;
    }

    public function getTitlePhraseName()
    {
        return 'lfs_group.' . $this->group_id;
    }

    public function getMasterTemplate()
    {
        $template = $this->MasterTemplate;
        if (! $template)
        {
            $template = $this->_em->create('XF:Template');
            $template->title = $this->_getDeferredValue(function() { return $this->getTemplateName(); });
            $template->type = 'public';
            $template->style_id = 0;
            $template->addon_id = $this->getOptionAddOnId();
            $template->template = '<xf:macro template="lfs_macros" name="tab_group" arg-tabGroup="{$tabGroup}" />';
        }

        return $template;
    }

    public function getTemplateName()
    {
        return 'lfs_group.' . $this->group_id;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_tab_group';
        $structure->shortName = 'BS\LiveForumStatistics:TabGroup';
        $structure->primaryKey = 'group_id';
        $structure->columns = [
            'group_id' => ['type' => self::BINARY, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'is_active' => ['type' => self::BOOL, 'default' => true],
            'carousel_interval' => ['type' => self::UINT, 'default' => 0],
            'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => '']
        ];
        $structure->getters = [
            'title' => true,
            'group_class' => true,
            'TabsViewable' => true
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
                'conditions' => 'group_id',
                'order' => 'display_order',
                'primary' => false
            ],
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'lfs_group.', '$group_id']
                ]
            ],
            'MasterTemplate' => [
                'entity' => 'XF:Template',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['style_id', '=', 0],
                    ['type', '=', 'public'],
                    ['title', '=', 'lfs_group.', '$group_id']
                ]
            ]
        ];
        $structure->options = [
            'addon_id' => false,
            'only_tabs' => []
        ];
        $structure->defaultWith = ['AddOn'];

        return $structure;
    }
}