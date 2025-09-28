<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class TabDefinition extends Repository
{
    public function findDefinitionsForList()
    {
        return $this->finder('BS\LiveForumStatistics:TabDefinition');
    }

    public function findActiveDefinitions()
    {
        return $this->finder('BS\LiveForumStatistics:TabDefinition')
            ->where('AddOn.active', 1);
    }

    public function getDefinitionsCacheData()
    {
        $definitions = $this->findActiveDefinitions()->fetch();

        $cache = [];

        foreach ($definitions AS $definitionId => $definition)
        {
            $definition = $definition->toArray();
            unset($definition['definition_id'], $definition['addon_id']);

            $cache[$definitionId] = $definition;
        }

        return $cache;
    }

    public function rebuildDefinitionsCache()
    {
        $cache = $this->getDefinitionsCacheData();
        \XF::registry()->set('lfsTabDefinitions', $cache);
        return $cache;
    }
}