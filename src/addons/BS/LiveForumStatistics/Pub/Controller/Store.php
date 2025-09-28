<?php

namespace BS\LiveForumStatistics\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Store extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertRegistrationRequired();
    }

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        $stickedLinkPurchases = $this->getStickedLinkPurchaseRepo()
            ->findPurchasesForUser($visitor)
            ->limit(10)
            ->fetch();

        $stickedThreads = $this->getStickedThreadRepo()
            ->findStickedThreadsForUser($visitor)
            ->limit(10)
            ->fetch();

        return $this->view('BS\LiveForumStatistics:Store\Index', 'lfs_store', compact('stickedLinkPurchases', 'stickedThreads'));
    }

    /**
     * @return \BS\LiveForumStatistics\Service\StickedLinkPurchase\Creator
     */
    protected function setupStickedLinkPurchaseCreate($attributes)
    {
        /** @var \BS\LiveForumStatistics\Service\StickedLinkPurchase\Creator  $creator */
        $creator = $this->service('BS\LiveForumStatistics:StickedLinkPurchase\Creator', $attributes);

        $creator->setTitle($this->filter('title', 'str'));
        $creator->setLink($this->filter('link', 'str'));

        if ($attributes->count())
        {
            $creator->setAttributesInput($this->filter('attributes', 'array-str'));
        }

        $creator->setNumberOfDays($this->filter('number_of_days', 'uint'));

        return $creator;
    }

    public function actionStickLink()
    {
        if (! \XF::visitor()->canPurchaseLinkInLfs())
        {
            return $this->noPermission();
        }

        $stickedLinkPurchaseRepo = $this->getStickedLinkPurchaseRepo();

        if (($stickedLimit = $this->options()->lfsStickedLimit) && $stickedLinkPurchaseRepo->getTotalSticks() > $stickedLimit)
        {
            $date = \XF::language()->dateTime($stickedLinkPurchaseRepo->getLeastStickEndDate());
            return $this->error(\XF::phrase('lfs_too_many_links_are_currently_sticked', compact('date')));
        }

        $attributeRepo = $this->getStickedAttributeRepo();

        $attributes = $attributeRepo
            ->findAttributesForList()
            ->fetch();

        if ($this->isPost())
        {
            if ($this->options()->lfsStickedLinkRules && ! $this->filter('accept', 'bool'))
            {
                return $this->error(\XF::phrase('lfs_you_must_agree_to_the_rules_for_the_purchase_of_a_sticked_link'));
            }

            $creator = $this->setupStickedLinkPurchaseCreate($attributes);
            if (! $creator->validate($errors))
            {
                return $this->error($errors);
            }

            $creator->save();

            return $this->redirect($this->getDynamicRedirect(null, false));
        }

        return $this->view('BS\LiveForumStatistics:Store\StickLink', 'lfs_store_stick_link', compact('attributes'));
    }

    public function actionStickedLinkRules()
    {
        if (! $this->options()->lfsStickedLinkRules)
        {
            return $this->noPermission();
        }

        return $this->view('BS\LiveForumStatistics:Store\StickedLinkRules', 'lfs_store_sticked_link_rules');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedLinkPurchase */
    protected function getStickedLinkPurchaseRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedLinkPurchase');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedThread */
    protected function getStickedThreadRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedThread');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedAttribute */
    protected function getStickedAttributeRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedAttribute');
    }
}