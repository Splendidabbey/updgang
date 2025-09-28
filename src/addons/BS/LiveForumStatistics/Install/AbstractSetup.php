<?php

namespace BS\LiveForumStatistics\Install;

use BS\LiveForumStatistics\Entity\Tab;
use BS\LiveForumStatistics\Entity\TabDefinition;
use XF\AddOn\AbstractSetup as XFAbstractSetup;
use BS\LiveForumStatistics\Interfaces\StatisticsSetup;

abstract class AbstractSetup extends XFAbstractSetup implements StatisticsSetup
{
    public function importLfsDefinitions()
    {
        foreach ($this->getLfsDefinitions() as $definitionId => $class)
        {
            $this->createLfsDefinition($definitionId, $class);
        }
    }

    public function importLfsGroups()
    {
        foreach ($this->getLfsGroups() as $groupId => $displayOrder)
        {
            $this->createLfsGroup($groupId, $displayOrder);
        }
    }

    public function importLfsTabs()
    {
        foreach ($this->getLfsTabs() as $groupId => $tabs)
        {
            $definitionId = $tabs['_Definition'];
            unset($tabs['_Definition']);

            foreach ($tabs as $tabId => $tabOptions)
            {
                $this->createLfsTab($tabId, $groupId, $definitionId ?? $tabOptions['definition'], $tabOptions['options'], $tabOptions['display_order']);
            }
        }
    }

    public function deleteLfsDefinitions()
    {
        $definitions = $this->app->em()->findByIds('BS\LiveForumStatistics:TabDefinition', array_keys($this->getLfsDefinitions()));
        foreach ($definitions as $definition)
        {
            $definition->delete();
        }
    }

    public function deleteLfsGroups()
    {
        $groups = $this->app->em()->findByIds('BS\LiveForumStatistics:TabGroup', array_keys($this->getLfsGroups()));
        foreach ($groups as $group)
        {
            $group->delete();
        }
    }

    public function deleteLfsTabs()
    {
        $tabs = $this->app->em()->findByIds('BS\LiveForumStatistics:Tab', array_keys($this->getLfsTabs()));
        foreach ($tabs as $tab)
        {
            $tab->delete();
        }
    }

    public function createLfsDefinition($definitionId, $class)
    {
        /** @var TabDefinition $definition */
        $definition = $this->app->em()->create('BS\LiveForumStatistics:TabDefinition');
        $definition->definition_id = $definitionId;
        $definition->definition_class = $class;
        $definition->addon_id = $this->addOn->getAddOnId();
        $definition->save(false);
    }

    public function createLfsGroup($groupId, $displayOrder, $title = '')
    {
        /** @var Tab $group */
        $group = $this->app->em()->create('BS\LiveForumStatistics:TabGroup');
        $group->group_id = $groupId;
        $group->display_order = $displayOrder;
        $group->addon_id = $this->addOn->getAddOnId();

        if ($group->save(false))
        {
            $masterTitle = $group->getMasterTitlePhrase();

            if ($title !== '')
            {
                $masterTitle->phrase_text = $title;
                $masterTitle->save(false);
            }
        }
    }

    public function createLfsTab($tabId, $groupId, $definitionId, array $options, $displayOrder, $title = '')
    {
        /** @var Tab $tab */
        $tab = $this->app->em()->create('BS\LiveForumStatistics:Tab');
        $tab->tab_id = $tabId;
        $tab->group_id = $groupId;
        $tab->definition_id = $definitionId;
        $tab->display_order = $displayOrder;
        $tab->options = $options;
        $tab->addon_id = $this->addOn->getAddOnId();

        if ($tab->save(false))
        {
            if ($title == '' && ! $tab->MasterTitle_)
            {
                $title = $tabId;
            }

            $masterTitle = $tab->getMasterTitlePhrase(true);
            $masterTitle->phrase_text = $title;
            $masterTitle->save(false);
        }
    }

    public function getShowTabsForGroups($groups) : array
    {
        $show = [];

        $tabs = $this->getLfsTabs();

        $groups = (array)$groups;

        foreach ($groups as $group)
        {
            $groupTabs = $tabs[$group] ?? [];
            unset($groupTabs['_Definition']);

            $show = array_merge_recursive($show, array_keys($groupTabs));
        }

        return $show;
    }

    protected function getLfsDefinitions() : array
    {
        return [];
    }

    protected function getLfsGroups() : array
    {
        return [];
    }

    protected function getLfsTabs() : array
    {
        return [];
    }
}