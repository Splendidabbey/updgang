<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use BS\LiveForumStatistics\Repository\TabGroup;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;

class Group extends AbstractController
{
    public function actionIndex()
    {
        $tabGroups = $this->getTabGroupRepo()
            ->findGroupsForList()
            ->fetch();

        return $this->view('BS\LiveForumStatistics:Group\List', 'lfs_group_list', compact('tabGroups'));
    }

    protected function groupAddEdit(\BS\LiveForumStatistics\Entity\TabGroup $tabGroup)
    {
        return $this->view('BS\LiveForumStatistics:Group\Edit', 'lfs_group_edit', compact('tabGroup'));
    }

    public function actionEdit(ParameterBag $params)
    {
        $tabGroup = $this->assertGroupExists($params->group_id);
        return $this->groupAddEdit($tabGroup);
    }

    public function actionAdd()
    {
        $tabGroup = $this->em()->create('BS\LiveForumStatistics:TabGroup');
        return $this->groupAddEdit($tabGroup);
    }

    protected function groupSaveProcess(\BS\LiveForumStatistics\Entity\TabGroup $tabGroup)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'group_id' => 'str',
            'carousel_interval' => 'int',
            'display_order' => 'uint',
            'is_active' => 'bool',
            'addon_id' => 'str'
        ]);

        $form->basicEntitySave($tabGroup, $input);

        $extraInput = $this->filter([
            'title' => 'str'
        ]);

        $form->validate(function(FormAction $form) use ($extraInput, $tabGroup)
        {
            if ($extraInput['title'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->apply(function() use ($extraInput, $tabGroup)
        {
            $title = $tabGroup->getMasterTitlePhrase();
            $title->phrase_text = $extraInput['title'];
            $title->save();
        });

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->group_id)
        {
            $tabGroup = $this->assertGroupExists($params->group_id);
        }
        else
        {
            $tabGroup = $this->em()->create('BS\LiveForumStatistics:TabGroup');
        }

        $this->groupSaveProcess($tabGroup)->run();

        return $this->redirect($this->buildLink('lfs/groups')  . $this->buildLinkHash($tabGroup->group_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $tabGroup = $this->assertGroupExists($params->group_id);

        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $tabGroup,
            $this->buildLink('lfs/groups/delete', $tabGroup),
            $this->buildLink('lfs/groups/edit', $tabGroup),
            $this->buildLink('lfs/groups'),
            $tabGroup->title
        );
    }

    public function actionToggle()
    {
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('BS\LiveForumStatistics:TabGroup', 'is_active');
    }

    public function actionSort()
    {
        $tabGroups = $this->getTabGroupRepo()
            ->findGroupsForList()
            ->fetch();

        if ($this->isPost())
        {
            $sortData = $this->filter('groups', 'json-array');

            /** @var \XF\ControllerPlugin\Sort $sorter */
            $sorter = $this->plugin('XF:Sort');
            $sorter->sortFlat($sortData, $tabGroups, [
                'jump' => 10
            ]);

            return $this->redirect($this->buildLink('lfs/groups'));
        }
        else
        {
            $viewParams = [
                'tabGroups' => $tabGroups
            ];
            return $this->view('BS\LiveForumStatistics:Group\Sort', 'lfs_group_sort', $viewParams);
        }
    }

    /** @return \BS\LiveForumStatistics\Entity\TabGroup */
    protected function assertGroupExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:TabGroup', $id, $with, $phraseKey);
    }

    /**
     * @return TabGroup
     */
    protected function getTabGroupRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabGroup');
    }
}