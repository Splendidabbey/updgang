<?php

namespace BS\LiveForumStatistics\Entity;

use BS\LiveForumStatistics\Tab\AbstractTab;
use BS\LiveForumStatistics\App;
use BS\LiveForumStatistics\Entity\Concerns\HasAddOnIdOption;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Pub\Controller\AbstractController;

/**
 * COLUMNS
 * @property string tab_id
 * @property string group_id
 * @property string definition_id
 * @property bool is_active
 * @property int display_order
 * @property string|null link
 * @property array options
 * @property string addon_id
 *
 * GETTERS
 * @property mixed title
 * @property \BS\LiveForumStatistics\Tab\AbstractTab renderer
 * @property mixed tab_class
 *
 * RELATIONS
 * @property \XF\Entity\AddOn AddOn
 * @property \BS\LiveForumStatistics\Entity\TabGroup Group
 * @property \BS\LiveForumStatistics\Entity\TabDefinition Definition
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Entity\Template MasterTemplate
 */

class Tab extends Entity
{
    use HasAddOnIdOption;

    public function canView()
    {
        $registry = $this->app()->registry();

        if (null === $registry->get('lfsTabDefinitions')[$this->definition_id])
        {
            return false;
        }

        if ($this->addon_id && ! $this->AddOn->active)
        {
            return false;
        }

        if ($this->Group->addon_id && ! $this->Group->AddOn->active)
        {
            return false;
        }

        return ($this->is_active && $this->renderer->canView());
    }

    public function canSetting()
    {
        return $this->renderer->canSetting();
    }

    public function render($finalRender = true, \XF\Http\Request $request = null)
    {
        return $this->renderer->render($finalRender, $request);
    }

    public function renderOptions()
    {
        return $this->renderer->renderOptions();
    }

    public function renderSetting(AbstractController $controller)
    {
        return $this->renderer->renderSetting($controller, $this);
    }

    public function saveSetting(AbstractController $controller)
    {
        return $this->renderer->saveSetting($controller, $this);
    }

    protected function _postSave()
    {
        $this->renderer->setTabConfig($this->toTabConfig());

        $addonId = $this->getOptionAddOnId();

        if ($this->isInsert())
        {
            $template = $this->getMasterTemplate();
            if (! $template->exists())
            {
                $template->template = $this->getOption('template') ?: $this->renderer->getDefaultTemplate($this->getOption('preset'));
                $template->save();
            }
        }
        else if ($this->isUpdate())
        {
            if ($this->isChanged('tab_id') || $this->isChanged('addon_id'))
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
        $phrase = $this->MasterTitle_;
        if ($phrase && $phrase->exists())
        {
            $phrase->delete();
        }

        $template = $this->MasterTemplate;
        if ($template && $template->exists())
        {
            $template->delete();
        }

        $this->db()->delete('xf_bs_lfs_user_forum_ignored', 'tab_id = ?', $this->tab_id);
    }

    public function getMasterTitlePhrase($isInsert = false)
    {
        $phrase = $isInsert ? $this->MasterTitle_ : $this->MasterTitle;
        if (! $phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() { return $this->getTitlePhraseName(); });
            $phrase->language_id = 0;
            $phrase->addon_id = $this->getOptionAddOnId();
        }

        return $phrase;
    }

    public function getMasterTitle()
    {
        if (! \XF::$developmentMode && $this->isUpdate())
        {
            $languageId = \XF::visitor()->language_id;

            $phrase = $this->em()->findOne('XF:Phrase', [
                ['language_id', '=', $languageId],
                ['title', '=', 'lfs_tab.' . $this->tab_id]
            ]);

            if (! $phrase)
            {
                $phrase = $this->_em->create('XF:Phrase');
                $phrase->title = $this->getTitlePhraseName();
                $phrase->language_id = $languageId;
                $phrase->addon_id = $this->getOptionAddOnId();
            }

            return $phrase;
        }

        return $this->MasterTitle_;
    }

    public function getTitle()
    {
        if (! $this->tab_id)
        {
            return '';
        }

        return \XF::phrase($this->getTitlePhraseName());
    }

    public function getTitlePhraseName()
    {
        return 'lfs_tab.' . $this->tab_id;
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
            $template->template = '';
        }

        return $template;
    }

    public function getTemplateName()
    {
        return 'lfs_tab.' . $this->tab_id;
    }

    public function getTabClass()
    {
        return "tab--{$this->tab_id}";
    }

    /** @return AbstractTab */
    public function getRenderer()
    {
        $definitionClass = false;
        $registry = $this->app()->registry();

        if (null !== $registry->get('lfsTabDefinitions')[$this->definition_id])
        {
            $definitionClass = $registry->get('lfsTabDefinitions')[$this->definition_id]['definition_class'];
        }

        if (! $definitionClass)
        {
            throw new \LogicException("Tab definition of {$this->title} is disabled");
        }

        return App::getTabRenderer($definitionClass, $this->toTabConfig());
    }

    protected function toTabConfig()
    {
        return array_merge($this->toArray(), [
            'tab_class' => $this->tab_class
        ]);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_tab';
        $structure->shortName = 'BS\LiveForumStatistics:Tab';
        $structure->primaryKey = 'tab_id';
        $structure->columns = [
            'tab_id' => ['type' => self::BINARY, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'group_id' => ['type' => self::BINARY, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'definition_id' => ['type' => self::BINARY, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'is_active' => ['type' => self::BOOL, 'default' => true],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'link' => ['type' => self::STR, 'nullable' => true],
            'options' => ['type' => self::JSON_ARRAY, 'default' => []],
            'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => '']
        ];
        $structure->getters = [
            'title' => true,
            'renderer' => false,
            'tab_class' => true,
            'MasterTitle' => true
        ];
        $structure->relations = [
            'AddOn' => [
                'entity' => 'XF:AddOn',
                'type' => self::TO_ONE,
                'conditions' => 'addon_id',
                'primary' => false
            ],
            'Group' => [
                'entity' => 'BS\LiveForumStatistics:TabGroup',
                'type' => self::TO_ONE,
                'conditions' => 'group_id'
            ],
            'Definition' => [
                'entity' => 'BS\LiveForumStatistics:TabDefinition',
                'type' => self::TO_ONE,
                'conditions' => 'definition_id'
            ],
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'lfs_tab.', '$tab_id']
                ]
            ],
            'MasterTemplate' => [
                'entity' => 'XF:Template',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['style_id', '=', 0],
                    ['type', '=', 'public'],
                    ['title', '=', 'lfs_tab.', '$tab_id']
                ]
            ]
        ];
        $structure->options = [
            'title' => false,
            'template' => false,
            'addon_id' => false,
            'preset' => false
        ];
        $structure->defaultWith = ['AddOn', 'Group', 'Group.AddOn'];

        return $structure;
    }
}