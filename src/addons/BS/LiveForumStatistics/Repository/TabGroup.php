<?php

namespace BS\LiveForumStatistics\Repository;

use XF\Mvc\Entity\Repository;

class TabGroup extends Repository
{
    public function getGroupsForWidget(array $groupIds = [], array $tabIds = [])
    {
        $groupFinder = $this->findGroupsForList()
            ->where('is_active', '=', true);

        if ($groupIds)
        {
            $groupFinder->where('group_id', '=', $groupIds);
        }

        $groups = $groupFinder->fetch();

        /** @var \BS\LiveForumStatistics\Entity\TabGroup $group */
        foreach ($groups as $group)
        {
            $group->setOption('only_tabs', $tabIds);
            $group->clearCache('TabsViewable');
        }

        return $groups->filterViewable();
    }

    public function findGroupsForList()
    {
        return $this->finder('BS\LiveForumStatistics:TabGroup')
            ->setDefaultOrder('display_order');
    }

    public function findActiveGroupsForList()
    {
        return $this->findGroupsForList()->where('is_active', '=', true);
    }
}