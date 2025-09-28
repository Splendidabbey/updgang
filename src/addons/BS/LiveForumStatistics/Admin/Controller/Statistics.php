<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Statistics extends AbstractController
{
    public function actionIndex()
    {
        return $this->view('BS\LiveForumStatistics:Index', 'lfs');
    }
}