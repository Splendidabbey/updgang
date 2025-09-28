<?php

namespace BS\LiveForumStatistics\Purchasable;

use XF\Payment\CallbackState;
use XF\Purchasable\AbstractPurchasable;
use XF\Purchasable\Purchase;

class StickedLinkPurchase extends AbstractPurchasable
{
    public function getTitle()
    {
        return \XF::phrase('lfs_payment_stick_link');
    }

    public function getPurchaseFromRequest(\XF\Http\Request $request, \XF\Entity\User $purchaser, &$error = null)
    {
        $availableProfiles = \XF::options()->lfsPaymentProfiles;

        $profileId = $request->filter('payment_profile_id', 'uint');
        $paymentProfile = \XF::em()->find('XF:PaymentProfile', $profileId);
        if (! ($paymentProfile && $paymentProfile->active)
            || !(in_array($paymentProfile->payment_profile_id, $availableProfiles) || in_array(-1, $availableProfiles))
        )
        {
            $error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');
            return false;
        }

        $linkPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedLinkPurchase', $request->filter('purchase_id', 'uint'));
        if (! ($linkPurchase && $linkPurchase->canPay()))
        {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $linkPurchase, $purchaser);
    }

    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];

        /** @var \BS\LiveForumStatistics\Entity\StickedLinkPurchase $linkPurchase */
        $linkPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedLinkPurchase', $extraData['purchase_id']);
        if ($linkPurchase)
        {
            $output['link'] = $linkPurchase->link;
            $output['title'] = $linkPurchase->title;
            $output['purchasable'] = $linkPurchase;
        }
        return $output;
    }

    public function getPurchaseFromExtraData(array $extraData, \XF\Entity\PaymentProfile $paymentProfile, \XF\Entity\User $purchaser, &$error = null)
    {
        $purchase = $this->getPurchasableFromExtraData($extraData);
        if (! ($purchase['purchasable'] && $purchase['purchasable']->canPay()))
        {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $purchase['purchasable'], $purchaser);
    }

    /**
     * @param \XF\Entity\PaymentProfile $paymentProfile
     * @param \BS\LiveForumStatistics\Entity\StickedLinkPurchase $purchasable
     * @param \XF\Entity\User $purchaser
     * @return mixed|Purchase
     */
    public function getPurchaseObject(\XF\Entity\PaymentProfile $paymentProfile, $purchasable, \XF\Entity\User $purchaser)
    {
        $purchase = new Purchase();

        $purchase->title = \XF::phrase('lfs_payment_stick_link_x', ['title' => $purchasable->title]) . ' (' . $purchaser->username . ')';
        $purchase->cost = $purchasable->cost_amount;
        $purchase->currency = $purchasable->cost_currency;
        $purchase->purchaser = $purchaser;
        $purchase->lengthAmount = $purchasable->number_of_days;
        $purchase->lengthUnit = 'day';
        $purchase->paymentProfile = $paymentProfile;
        $purchase->purchasableTypeId = $this->purchasableTypeId;
        $purchase->purchasableId = $purchasable->purchase_id;
        $purchase->purchasableTitle = $purchasable->title;
        $purchase->extraData = [
            'purchase_id' => $purchasable->purchase_id
        ];

        $router = \XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:lfs/store/sticked-links/purchase');
        $purchase->cancelUrl = $router->buildLink('canonical:lfs/store');

        return $purchase;
    }

    public function completePurchase(CallbackState $state)
    {
        if ($state->legacy)
        {
            return;
        }

        $purchaseRequest = $state->getPurchaseRequest();

        $paymentResult = $state->paymentResult;

        $stickedLink = null;

        switch ($paymentResult)
        {
            case CallbackState::PAYMENT_RECEIVED:
                /** @var \BS\LiveForumStatistics\Entity\StickedLinkPurchase $linkPurchase */
                $linkPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedLinkPurchase', $purchaseRequest->extra_data['purchase_id']);

                $stickedLink = $linkPurchase->getPurchasedLink();
                $stickedLink->save();

                $requestKey = $purchaseRequest->request_key;
                if (strlen($requestKey) > 32)
                {
                    $requestKey = substr($requestKey, 0, 29) . '...';
                }

                $linkPurchase->fastUpdate([
                    'purchase_request_key' => $requestKey,
                    'link_id'   => $stickedLink->link_id,
                    'paid_date' => \XF::$time,
                    'end_date'  => $stickedLink->end_date,
                    'status'    => 'paid'
                ]);

                $state->logType = 'payment';
                $state->logMessage = 'Payment received.';
                break;

            case CallbackState::PAYMENT_REINSTATED:
                $state->logType = 'info';
                $state->logMessage = 'OK, no action.';
                break;
        }

        if ($stickedLink && $purchaseRequest)
        {
            $extraData = $purchaseRequest->extra_data;
            $extraData['link_id'] = $stickedLink->link_id;
            $purchaseRequest->extra_data = $extraData;
            $purchaseRequest->save();
        }
    }

    public function reversePurchase(CallbackState $state)
    {
        // Doesn't support this.
    }

    public function getPurchasablesByProfileId($profileId)
    {
        return [];
    }
}