<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Tab extends AbstractController
{
    public function actionIndex()
    {
        $tabs = $this->getTabRepo()->findTabsForList()
            ->fetch();

        $tabGroups = $this->getTabGroupRepo()
            ->findGroupsForList()
            ->fetch();

        return $this->view('BS\LiveForumStatistics:Tab\List', 'lfs_tab_list', compact('tabGroups', 'tabs'));
    }

    protected function tabAddEdit(\BS\LiveForumStatistics\Entity\Tab $tab)
    {
        $tabGroups = $this->getTabGroupRepo()
            ->findGroupsForList()
            ->fetch();

        $tabDefinitions = $this->getTabDefinitionRepo()
            ->findDefinitionsForList()
            ->fetch();

        if (! $tabGroups->count())
        {
            return $this->error(\XF::phrase('lfs_you_must_create_tab_group_to_use_for_your_new_tab'));
        }

        if (! $tabDefinitions->count())
        {
            return $this->error(\XF::phrase('lfs_you_must_create_tab_definition_to_use_for_your_new_tab'));
        }

        $viewParams = [
            'tab' => $tab,

            'tabGroups' => $tabGroups,
            'tabDefinitions' => $tabDefinitions
        ];
        return $this->view('BS\LiveForumStatistics:Tab\Edit', 'lfs_tab_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $tab = $this->assertTabExists($params->tab_id);
        return $this->tabAddEdit($tab);
    }

    public function actionAdd()
    {
        $definitionId = $this->filter('definition_id', 'str');
        $groupId = $this->filter('group_id', 'str');

        $tab = $this->em()->create('BS\LiveForumStatistics:Tab');

        if ($definitionId)
        {
            $tab->definition_id = $definitionId;
        }
        if ($groupId)
        {
            $tab->group_id = $groupId;
        }

        if ($copyTabId = $this->filter('copy', 'str'))
        {
            $copyTab = $this->finder('BS\LiveForumStatistics:Tab')->where('tab_id', $copyTabId)->fetchOne();

            if ($copyTab)
            {
                $copyTabAr = $copyTab->toArray();
                unset($copyTabAr['tab_id'], $copyTabAr['title'], $copyTabAr['options']);

                $tab->bulkSet($copyTabAr);
                $tab->setOption('title', $copyTab->title);
                $tab->setOption('template', $copyTab->MasterTemplate->template);
                $tab->options = $copyTab->options;
            }
        }

        return $this->tabAddEdit($tab);
    }

    protected function tabSaveProcess(\BS\LiveForumStatistics\Entity\Tab $tab)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'tab_id' => 'str',
            'definition_id' => 'str',
            'group_id' => 'str',
            'is_active' => 'bool',
            'link' => 'str',
            'addon_id' => 'str',
            'display_order' => 'uint'
        ]);

        $extraInput = $this->filter([
            'title' => 'str'
        ]);

        $form->validate(function(FormAction $form) use ($input, $tab)
        {
            if ($input['definition_id'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_value'), 'definition_id');
            }

            if ($input['group_id'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_value'), 'group_id');
            }

            $options = $this->filter('options', 'array');
            $request = new \XF\Http\Request($this->app->inputFilterer(), $options, [], []);
            $renderer = $tab->getRenderer();
            if ($renderer && !$renderer->verifyOptions($request, $options, $error))
            {
                $form->logError($error);
            }

            $tab->options = $options;
        });

        $isInsert = $tab->isInsert();

        $form->basicEntitySave($tab, $input);

        $form->apply(function() use ($extraInput, $input, $tab, $isInsert)
        {
            if ($extraInput['title'] === '')
            {
                $extraInput['title'] = $this->assertDefinitionExists($input['definition_id'])->title;
            }

            $title = $tab->getMasterTitlePhrase($isInsert);
            $title->phrase_text = $extraInput['title'];
            $title->save();
        });

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->tab_id)
        {
            $tab = $this->assertTabExists($params->tab_id);
        }
        else
        {
            $tab = $this->em()->create('BS\LiveForumStatistics:Tab');
        }

        $this->tabSaveProcess($tab)->run();

        return $this->redirect($this->buildLink('lfs/tabs')  . $this->buildLinkHash($tab->tab_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $tab = $this->assertTabExists($params->tab_id);

        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $tab,
            $this->buildLink('lfs/tabs/delete', $tab),
            $this->buildLink('lfs/tabs/edit', $tab),
            $this->buildLink('lfs/tabs'),
            $tab->title
        );
    }

    public function actionToggle()
    {
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('BS\LiveForumStatistics:Tab', 'is_active');
    }

    public function actionSort()
    {
        $tabGroups = $this->getTabGroupRepo()
            ->findGroupsForList()
            ->fetch();

        if ($this->isPost())
        {
            $sortData = $this->filter('tabs', 'json-array');

            $tabs = $this->getTabRepo()->findTabsForList()
                ->fetch();

            /** @var \XF\ControllerPlugin\Sort $sorter */
            $sorter = $this->plugin('XF:Sort');
            $sorter->sortFlat($sortData, $tabs, [
                'jump' => 10
            ]);

            return $this->redirect($this->buildLink('lfs/tabs'));
        }
        else
        {
            $viewParams = [
                'tabGroups' => $tabGroups
            ];
            return $this->view('BS\LiveForumStatistics:Tab\Sort', 'lfs_tab_sort', $viewParams);
        }
    }

    /** @return \BS\LiveForumStatistics\Entity\Tab */
    protected function assertTabExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:Tab', $id, $with, $phraseKey);
    }

    /** @return \BS\LiveForumStatistics\Entity\TabDefinition */
    protected function assertDefinitionExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:TabDefinition', $id, $with, $phraseKey);
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\Tab
     */
    protected function getTabRepo()
    {
        return $this->repository('BS\LiveForumStatistics:Tab');
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\TabGroup
     */
    protected function getTabGroupRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabGroup');
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\TabDefinition
     */
    protected function getTabDefinitionRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabDefinition');
    }
}