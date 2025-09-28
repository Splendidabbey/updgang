<?php

namespace BS\LiveForumStatistics\Tab\Concerns;

trait TabPagination
{
    protected function renderPagination(\XF\Mvc\Entity\Finder $finder, &$viewParams = [], \XF\Http\Request $request = null, $limit = 15, $extraLimit = 0)
    {
        $page = $request ? max(1, $request->filter('page', 'uint')) : 1;

        $finder->limitByPage($page, $limit, $extraLimit);

        $viewParams['page'] = $page;
        $viewParams['perPage'] = $limit;
        $viewParams['total'] = $finder->total();
    }
}