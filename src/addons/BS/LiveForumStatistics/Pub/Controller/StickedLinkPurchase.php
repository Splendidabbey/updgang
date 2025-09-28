<?php

namespace BS\LiveForumStatistics\Pub\Controller;

use XF\Entity\PaymentProfile;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class StickedLinkPurchase extends AbstractController
{
    public function actionPay(ParameterBag $params)
    {
        $purchase = $this->assertStickedLinkPurchaseExists($params->purchase_id);

        if (! $purchase->canPay())
        {
            return $this->noPermission();
        }

        $availableProfiles = $this->options()->lfsPaymentProfiles;

        /** @var PaymentProfile[] $paymentProfiles */
        $paymentProfiles = $this->repository('XF:Payment')
            ->findPaymentProfilesForList()
            ->fetch()
            ->filter(function ($profile) use ($purchase, $availableProfiles)
            {
                return $profile->verifyCurrency($purchase->cost_currency)
                    && (in_array($profile->payment_profile_id, $availableProfiles) || in_array(-1, $availableProfiles));
            });

        if (! $paymentProfiles->count())
        {
            return $this->noPermission();
        }

        return $this->view('BS\LiveForumStatistics:StickedLinkPurchase\Pay', 'lfs_sticked_link_purchase_pay', compact('purchase', 'paymentProfiles'));
    }

    public function actionPurchase()
    {
        return $this->view('BS\LiveForumStatistics:StickedLinkPurchase\Purchase', 'lfs_sticked_link_purchase_purchase');
    }

    /** @return \BS\LiveForumStatistics\Entity\StickedLinkPurchase */
    protected function assertStickedLinkPurchaseExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\LiveForumStatistics:StickedLinkPurchase', $id, $with, $phraseKey);
    }
}