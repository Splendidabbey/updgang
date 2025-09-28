<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use BS\LiveForumStatistics\Repository\TabDefinition;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Definition extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        if (! preg_match('/options/si', $action))
        {
            $this->assertDebugMode();
            $this->assertDevelopmentMode();
        }
    }

    public function actionIndex()
    {
        $tabDefinitions = $this->getTabDefinitionRepo()->findDefinitionsForList();
        $total = $tabDefinitions->total();

        $viewParams = [
            'tabDefinitions' => $tabDefinitions->fetch(),
            'total' => $total
        ];
        return $this->view('BS\LiveForumStatistics:Definition\List', 'lfs_definition_list', $viewParams);
    }

    protected function definitionAddEdit(\BS\LiveForumStatistics\Entity\TabDefinition $tabDefinition)
    {
        $viewParams = [
            'tabDefinition' => $tabDefinition
        ];
        return $this->view('BS\LiveForumStatistics:TabDefinition\Edit', 'lfs_definition_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $tabDefinition = $this->assertDefinitionExists($params->definition_id);
        return $this->definitionAddEdit($tabDefinition);
    }

    public function actionAdd()
    {
        $tabDefinition = $this->em()->create('BS\LiveForumStatistics:TabDefinition');
        return $this->definitionAddEdit($tabDefinition);
    }

    protected function definitionSaveProcess(\BS\LiveForumStatistics\Entity\TabDefinition $tabDefinition)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'definition_id' => 'str',
            'definition_class' => 'str',
            'addon_id' => 'str'
        ]);

        $form->basicEntitySave($tabDefinition, $input);

        $extraInput = $this->filter([
            'title' => 'str'
        ]);
        $form->validate(function(FormAction $form) use ($extraInput)
        {
            if ($extraInput['title'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });
        $form->apply(function() use ($extraInput, $tabDefinition)
        {
            $title = $tabDefinition->getMasterTitlePhrase();
            $title->phrase_text = $extraInput['title'];
            $title->save();

            $template = $tabDefinition->getOptionsTemplate();
            $template->save();
        });

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->definition_id)
        {
            $tabDefinition = $this->assertDefinitionExists($params->definition_id);
        }
        else
        {
            $tabDefinition = $this->em()->create('BS\LiveForumStatistics:TabDefinition');
        }

        $this->definitionSaveProcess($tabDefinition)->run();

        return $this->redirect($this->buildLink('lfs/definitions')  . $this->buildLinkHash($tabDefinition->definition_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $tabDefinition = $this->assertDefinitionExists($params->definition_id);

        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $tabDefinition,
            $this->buildLink('lfs/definitions/delete', $tabDefinition),
            $this->buildLink('lfs/definitions/edit', $tabDefinition),
            $this->buildLink('lfs/definitions'),
            $tabDefinition->title
        );
    }
    
    public function actionOptions(ParameterBag $params)
    {
        $tabDefinition = $this->assertDefinitionAvailable($this->filter('definition_id', 'str'));

        if ($template = $tabDefinition->getOptionsTemplate())
        {
            if ($tabId = $this->filter('tab_id', 'str'))
            {
                $tab = $this->assertTabExists($tabId);
            }
            else
            {
                $tab = $this->em()->create('BS\LiveForumStatistics:Tab');
            }

            $preset = $this->filter('preset', 'str');

            $definitionParams = $tab->exists()
                ? $tab->renderer->getParamsForOptions($preset)
                : $tabDefinition->getParamsForOptions($preset);

            $viewParams = array_merge($definitionParams, [
                'tab' => $tab,
                'definition' => $tabDefinition
            ]);

            return $this->view('BS\LiveForumStatistics:Definition\Options', $template->title, $viewParams);
        }

        return $this->view('BS\LiveForumStatistics:Definition\Options');
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

    /** @return \BS\LiveForumStatistics\Entity\TabDefinition */
    protected function assertDefinitionAvailable($id, $with = null, $phraseKey = null)
    {
        $definition = $this->assertRecordExists('BS\LiveForumStatistics:TabDefinition', $id, $with, $phraseKey);

        if (! $definition->isAvailable())
        {
            throw $this->exception($this->noPermission());
        }

        return $definition;
    }

    /**
     * @return TabDefinition
     */
    protected function getTabDefinitionRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabDefinition');
    }
}