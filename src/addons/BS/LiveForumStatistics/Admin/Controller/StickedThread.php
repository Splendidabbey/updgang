<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class StickedThread extends AbstractController
{
    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 20;

        $threadFinder = $this->getStickedThreadRepo()
            ->findStickedThreads()
            ->limitByPage($page, $perPage);

        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);
        if (strlen($filter['text']))
        {
            $threadFinder->where('title', 'LIKE', $threadFinder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%'));
        }

        $viewParams = [
            'threads' => $threadFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $threadFinder->total()
        ];

        return $this->view('BS\LiveForumStatistics:StickedThread\List', 'lfs_sticked_thread_list', $viewParams);
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\StickedThread
     */
    protected function getStickedThreadRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedThread');
    }
}