<?php

namespace BS\LiveForumStatistics\Purchasable;

use BS\LiveForumStatistics\Entity\StickedThreadPurchase;
use XF\Payment\CallbackState;
use XF\Purchasable\AbstractPurchasable;
use XF\Purchasable\Purchase;

class StickedThread extends AbstractPurchasable
{
    public function getTitle()
    {
        return \XF::phrase('lfs_payment_stick_thread');
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

        $threadPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedThreadPurchase', $request->filter('purchase_id', 'uint'));
        if (! ($threadPurchase && $threadPurchase->canPay()))
        {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $threadPurchase, $purchaser);
    }

    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];

        /** @var StickedThreadPurchase $threadPurchase */
        $threadPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedThreadPurchase', $extraData['purchase_id']);
        if ($threadPurchase)
        {
            $output['link'] = \XF::app()->router('public')->buildLink('threads', $threadPurchase->Thread);
            $output['title'] = $threadPurchase->title;
            $output['purchasable'] = $threadPurchase;
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
     * @param StickedThreadPurchase $purchasable
     * @param \XF\Entity\User $purchaser
     * @return mixed|Purchase
     */
    public function getPurchaseObject(\XF\Entity\PaymentProfile $paymentProfile, $purchasable, \XF\Entity\User $purchaser)
    {
        $purchase = new Purchase();

        $purchase->title = \XF::phrase('lfs_payment_stick_thread_x', ['title' => $purchasable->Thread->title]) . ' (' . $purchaser->username . ')';
        $purchase->cost = $purchasable->cost_amount;
        $purchase->currency = $purchasable->cost_currency;
        $purchase->purchaser = $purchaser;
        $purchase->paymentProfile = $paymentProfile;
        $purchase->purchasableTypeId = $this->purchasableTypeId;
        $purchase->purchasableId = $purchasable->purchase_id;
        $purchase->purchasableTitle = $purchasable->title;
        $purchase->extraData = [
            'purchase_id' => $purchasable->purchase_id
        ];

        $router = \XF::app()->router('public');

        $purchase->returnUrl = $router->buildLink('canonical:threads', $purchasable->Thread);
        $purchase->cancelUrl = $router->buildLink('canonical:threads', $purchasable->Thread);

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

        switch ($paymentResult)
        {
            case CallbackState::PAYMENT_RECEIVED:
                /** @var \BS\LiveForumStatistics\Entity\StickedThreadPurchase $threadPurchase */
                $threadPurchase = \XF::em()->find('BS\LiveForumStatistics:StickedThreadPurchase', $purchaseRequest->extra_data['purchase_id']);
                $threadPurchase->stick();

                $requestKey = $purchaseRequest->request_key;
                if (strlen($requestKey) > 32)
                {
                    $requestKey = substr($requestKey, 0, 29) . '...';
                }

                $threadPurchase->fastUpdate([
                    'purchase_request_key' => $requestKey,
                    'is_payed' => true
                ]);

                $state->logType = 'payment';
                $state->logMessage = 'Payment received.';
                break;

            case CallbackState::PAYMENT_REINSTATED:
                $state->logType = 'info';
                $state->logMessage = 'OK, no action.';
                break;
        }

        if ($purchaseRequest)
        {
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