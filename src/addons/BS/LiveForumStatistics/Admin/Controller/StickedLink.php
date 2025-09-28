<?php

namespace BS\LiveForumStatistics\Admin\Controller;

use BS\LiveForumStatistics\Concerns\Controller\FilterAttributes;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class StickedLink extends AbstractController
{
    use FilterAttributes;

    public function actionIndex()
    {
        $page = $this->filterPage();
        $perPage = 20;

        $linkFinder = $this->getStickedLinkRepo()
            ->findLinksForList()
            ->limitByPage($page, $perPage);

        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);
        if (strlen($filter['text']))
        {
            $linkFinder->whereOr([
                ['title', 'LIKE', $linkFinder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%')],
                ['link', 'LIKE', $linkFinder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%')]
            ]);
        }

        $viewParams = [
            'stickedLinks' => $linkFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $linkFinder->total()
        ];

        return $this->view('BS\LiveForumStatistics:StickedLink\List', 'lfs_sticked_link_list', $viewParams);
    }

    protected function linkAddEdit(\BS\LiveForumStatistics\Entity\StickedLink $stickedLink)
    {
        $viewParams = [
            'stickedLink' => $stickedLink
        ];
        return $this->view('BS\LiveForumStatistics:StickedLink\Edit', 'lfs_sticked_link_edit', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $stickedLink = $this->assertStickedLinkExists($params->link_id);
        return $this->linkAddEdit($stickedLink);
    }

    public function actionAdd()
    {
        $stickedLink = $this->em()->create('BS\LiveForumStatistics:StickedLink');
        return $this->linkAddEdit($stickedLink);
    }

    protected function linkSaveProcess(\BS\LiveForumStatistics\Entity\StickedLink $stickedLink)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'title' => 'str',
            'link'  => 'str',
            'sticked_order' => 'uint',
            'is_active' => 'bool'
        ]);

        $extraAttrs = $this->filterAttributes();

        $form->setup(function () use ($stickedLink)
        {
            $hasEndDate = $this->filter('has_end_date', 'bool');
            $endDate = $hasEndDate ? $this->filter('end_date', 'datetime') : 0;
            $stickedLink->set('end_date', $endDate);
        });

        $input['attributes'] = $extraAttrs;

        $form->basicEntitySave($stickedLink, $input);

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->link_id)
        {
            $stickedLink = $this->assertStickedLinkExists($params->link_id);
        }
        else
        {
            $stickedLink = $this->em()->create('BS\LiveForumStatistics:StickedLink');
        }

        $this->linkSaveProcess($stickedLink)->run();

        return $this->redirect($this->buildLink('lfs/sticked-links')  . $this->buildLinkHash($stickedLink->link_id));
    }

    public function actionDelete(ParameterBag $params)
    {
        $stickedLink = $this->assertStickedLinkExists($params->link_id);

        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $stickedLink,
            $this->buildLink('lfs/sticked-links/delete', $stickedLink),
            $this->buildLink('lfs/sticked-links/edit', $stickedLink),
            $this->buildLink('lfs/sticked-links'),
            $stickedLink->title
        );
    }

    public function actionToggle()
    {
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('BS\LiveForumStatistics:StickedLink', 'is_active');
    }

    /** @return \BS\LiveForumStatistics\Entity\StickedLink */
    protected function assertStickedLinkExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:StickedLink', $id, $with, $phraseKey);
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\StickedLink
     */
    protected function getStickedLinkRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedLink');
    }
}