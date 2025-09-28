<?php

namespace BS\LiveForumStatistics\XF\Pub\Controller;

use BS\LiveForumStatistics\Concerns\Controller\FilterAttributes;
use BS\LiveForumStatistics\Service\Thread\Stick;
use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    use FilterAttributes;

    public function actionStickingInLfs(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);
        if (! \XF::visitor()->canStickUnstickThreadInLfs())
        {
            return $this->noPermission();
        }

        if ($this->isPost())
        {
            /** @var Stick $sticker */
            $sticker = $this->service('BS\LiveForumStatistics:Thread\Stick', $thread);

            if ($this->filter('unstick', 'bool'))
            {
                $thread = $sticker->unstick();
            }
            else
            {
                $hasEndDate = $this->filter('has_end_date', 'bool');
                $endDate = $hasEndDate ? $this->filter('end_date', 'datetime') : 0;

                $extraAttrs = $this->filterAttributes();

                $sticker->setOrder($this->filter('sticked_order', 'uint'));
                $sticker->setEndDate($endDate);
                $sticker->setAttributes($extraAttrs);

                $thread = $sticker->stick();
            }

            if ($thread->hasErrors())
            {
                return $this->error($thread->getErrors());
            }

            return $this->redirect($this->buildLink('threads', $thread));
        }

        return $this->view('BS\LiveForumStatistics:Thread', 'lfs_sticking_thread_edit', compact('thread'));
    }

    /**
     * @return \BS\LiveForumStatistics\Service\StickedThreadPurchase\Creator
     */
    protected function setupStickedThreadPurchaseCreate(\XF\Entity\Thread $thread, $attributes)
    {
        /** @var \BS\LiveForumStatistics\Service\StickedThreadPurchase\Creator  $creator */
        $creator = $this->service('BS\LiveForumStatistics:StickedThreadPurchase\Creator', $thread, $attributes);

        if ($attributes->count())
        {
            $creator->setAttributesInput($this->filter('attributes', 'array-str'));
        }

        $creator->setNumberOfDays($this->filter('number_of_days', 'uint'));

        return $creator;
    }

    public function actionPurchaseInLfs(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);
        $visitor = \XF::visitor();

        if (! $visitor->canPurchaseThreadInLfs($thread))
        {
            return $this->noPermission();
        }

        $options = $this->options();

        $stickedLinkPurchaseRepo = $this->getStickedLinkPurchaseRepo();

        if (($stickedLimit = $options->lfsStickedLimit) && $stickedLinkPurchaseRepo->getTotalSticks() > $stickedLimit)
        {
            $date = \XF::language()->dateTime($stickedLinkPurchaseRepo->getLeastStickEndDate());
            return $this->error(\XF::phrase('lfs_too_many_links_are_currently_sticked', compact('date')));
        }

        $availableProfiles = $options->lfsPaymentProfiles;

        /** @var PaymentProfile[] $paymentProfiles */
        $paymentProfiles = $this->repository('XF:Payment')
            ->findPaymentProfilesForList()
            ->fetch()
            ->filter(function ($profile) use ($options, $availableProfiles)
            {
                return $profile->verifyCurrency($options->lfsCurrency)
                    && (in_array($profile->payment_profile_id, $availableProfiles) || in_array(-1, $availableProfiles));
            });

        if (! $paymentProfiles->count())
        {
            return $this->noPermission();
        }

        $attributeRepo = $this->getStickedAttributeRepo();

        $attributes = $attributeRepo
            ->findAttributesForList()
            ->fetch();

        if ($this->isPost())
        {
            $creator = $this->setupStickedThreadPurchaseCreate($thread, $attributes);
            if (! $creator->validate($errors))
            {
                return $this->error($errors);
            }

            $purchase = $creator->save();

            return $this->view('BS\LiveForumStatistics:Thread\Pay', 'lfs_store_stick_thread_pay', compact('purchase', 'paymentProfiles'));
        }

        return $this->view('BS\LiveForumStatistics:Thread\Purchase', 'lfs_store_stick_thread', compact('thread', 'attributes'));
    }

    public function actionIgnoreInLfs(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);
        $visitor = \XF::visitor();

        $wasIgnoring = $visitor->isIgnoringThreadInLfs($thread);

        if (! ($wasIgnoring || $visitor->canIgnoreThreadInLfs($thread)))
        {
            return $this->noPermission();
        }

        $redirect = $this->getDynamicRedirect(null, false);

        if ($this->isPost())
        {
            $ignoreService = $this->service('BS\LiveForumStatistics:User\ThreadIgnore', $thread);

            if ($wasIgnoring)
            {
                $threadIgnored = $ignoreService->unignore();
            }
            else
            {
                $threadIgnored = $ignoreService->ignore();
            }

            if ($threadIgnored->hasErrors())
            {
                return $this->error($threadIgnored->getErrors());
            }

            $reply = $this->redirect($redirect);
            $reply->setJsonParam('switchKey', $wasIgnoring ? 'ignore' : 'unignore');
            return $reply;
        }
        else
        {
            $viewParams = [
                'thread' => $thread,
                'redirect' => $redirect,
                'isIgnoring' => $wasIgnoring
            ];
            return $this->view('BS\LiveForumStatistics:Thread\Ignore', 'lfs_thread_ignore', $viewParams);
        }
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedLinkPurchase */
    protected function getStickedLinkPurchaseRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedLinkPurchase');
    }

    /** @return \BS\LiveForumStatistics\Repository\StickedAttribute */
    protected function getStickedAttributeRepo()
    {
        return $this->repository('BS\LiveForumStatistics:StickedAttribute');
    }
}

if (false)
{
    class XFCP_Thread extends \XF\Pub\Controller\Thread {}
}