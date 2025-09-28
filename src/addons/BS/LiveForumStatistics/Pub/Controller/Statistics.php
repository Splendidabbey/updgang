<?php

namespace BS\LiveForumStatistics\Pub\Controller;

use BS\LiveForumStatistics\Entity\Tab;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Statistics extends AbstractController
{
    public function actionTab()
    {
        $tab = $this->assertTabViewable($this->filter('tab_id', 'str'));

        list($templateName, $viewParams) = $tab->render(false, $this->request);

        $hasItems = $viewParams['hasItems'] ?? true;

        $reply = $this->view('BS\LiveForumStatistics:Tab\Render', $templateName, $viewParams);

        $reply->setJsonParam('hasItems', $hasItems);

        return $reply;
    }

    public function actionTabSetting(ParameterBag $params)
    {
        $tab = $this->assertTabViewable($params->tab_id);

        if (! $tab->canSetting())
        {
            return $this->noPermission();
        }

        return $tab->renderSetting($this);
    }

    public function actionTabSave(ParameterBag $params)
    {
        $tab = $this->assertTabViewable($params->tab_id);

        if (! $tab->canSetting())
        {
            return $this->noPermission();
        }

        return $tab->saveSetting($this);
    }

    /** @return Tab */
    protected function assertTabViewable($id, $with = null, $phraseKey = null)
    {
        $tab = $this->assertRecordExists('BS\LiveForumStatistics:Tab', $id, $with, $phraseKey);

        if (! $tab->canView())
        {
            throw $this->exception($this->noPermission());
        }

        return $tab;
    }

    /** @return \BS\LiveForumStatistics\Repository\Tab */
    protected function getTabRepo()
    {
        return $this->repository('BS\LiveForumStatistics:Tab');
    }
}