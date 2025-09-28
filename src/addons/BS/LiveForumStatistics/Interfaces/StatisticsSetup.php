<?php

namespace BS\LiveForumStatistics\Interfaces;

interface StatisticsSetup
{
    public function importLfsDefinitions();

    public function importLfsGroups();

    public function importLfsTabs();

    public function createLfsDefinition($definitionId, $class);

    public function createLfsGroup($groupId, $displayOrder, $title = '');

    public function createLfsTab($tabId, $groupId, $definitionId, array $options, $displayOrder, $title = '');

    public function getShowTabsForGroups($groups) : array;
}