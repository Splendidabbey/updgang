<?php

namespace BS\LiveForumStatistics\Entity;

use XF\Entity\ApprovalQueue;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\UserAlert;

/**
 * COLUMNS
 * @property int|null purchase_id
 * @property string title
 * @property string link
 * @property array attributes
 * @property float cost_amount
 * @property string cost_currency
 * @property int purchase_date
 * @property int number_of_days
 * @property int end_date
 * @property int link_id
 * @property int user_id
 * @property string status
 * @property string|null purchase_request_key
 *
 * GETTERS
 * @property string status_title
 * @property string purchasable_type_id
 *
 * RELATIONS
 * @property ApprovalQueue|null ApprovalQueue
 * @property \XF\Entity\User|null User
 * @property StickedLink|null Link
 * @property PurchaseRequest|null PurchaseRequest
 */

class StickedLinkPurchase extends Entity
{
    public function isExpired()
    {
        return $this->end_date < \XF::$time;
    }

    public function canPay()
    {
        return \XF::visitor()->user_id == $this->user_id && $this->status == 'awaiting_payment';
    }

    public function canApproveUnapprove()
    {
        return \XF::visitor()->hasLfsPermission('approveUnapprove');
    }

    /** @return StickedLink */
    public function getPurchasedLink()
    {
        if ($this->Link)
        {
            return $this->Link;
        }
        else
        {
            $endDate = min(
                pow(2,32) - 1,
                strtotime('+' . $this->number_of_days . ' days')
            );

            /** @var StickedLink $stickedLink */
            $stickedLink = $this->_em->create('BS\LiveForumStatistics:StickedLink');
            $stickedLink->bulkSet([
                'title' => $this->title,
                'link'  => $this->link,
                'attributes' => $this->attributes,
                'end_date' => $endDate
            ]);

            return $stickedLink;
        }
    }

    protected function _postSave()
    {
        $awaitingPaymentChange = $this->isStateChanged('status', 'awaiting_payment');
        $approvalChange = $this->isStateChanged('status', 'moderated');
        $rejectedChange = $this->isStateChanged('status', 'rejected');

        if ($approvalChange == 'enter')
        {
            $approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
            $approvalQueue->content_date = $this->purchase_date;
            $approvalQueue->save();
        }
        else if ($approvalChange == 'leave')
        {
            $this->ApprovalQueue->delete();
        }

        if ($awaitingPaymentChange == 'enter')
        {
            $this->getAlertRepo()->alert(
                $this->User,
                0, '',
                'lfs_sticked_link_purchase', $this->purchase_id,
                'awaiting_payment'
            );
        }

        if ($rejectedChange == 'enter')
        {
            $this->getAlertRepo()->alert(
                $this->User,
                0, '',
                'lfs_sticked_link_purchase', $this->purchase_id,
                'rejected'
            );
        }
    }

    protected function _postDelete()
    {
        if ($this->ApprovalQueue)
        {
            $this->ApprovalQueue->delete();
        }
    }

    public function getStatusTitle()
    {
        if (! ($this->Link && $this->Link->is_active) && ! $this->isExpired())
        {
            return \XF::phrase('lfs_purchase_status.rejected');
        }

        return \XF::phrase('lfs_purchase_status.' . $this->status);
    }

    public function getPurchasableTypeId()
    {
        return 'lfs_sticked_link_purchase';
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_sticked_link_purchase';
        $structure->shortName = 'BS\LiveForumStatistics:StickedLinkPurchase';
        $structure->primaryKey = 'purchase_id';
        $structure->contentType = 'lfs_sticked_link_purchase';
        $structure->columns = [
            'purchase_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'maxLength' => 500],
            'link' => [
                'type' => self::STR, 'require' => true,
                'match' => [
                    '#^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:\/?\#[\]@!\$&\'\(\)\*\+,;=.]+$#s',
                    'please_enter_valid_url'
                ]
            ],
            'cost_amount' => ['type' => self::FLOAT, 'default' => 0, 'min' => 0, 'max' => 99999999.99],
            'cost_currency' => ['type' => self::STR, 'default' => \XF::options()->lfsCurrency],
            'attributes' => ['type' => self::JSON_ARRAY, 'default' => []],
            'purchase_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'paid_date' => ['type' => self::UINT, 'default' => 0],
            'end_date' => ['type' => self::UINT, 'default' => 0],
            'number_of_days' => ['type' => self::UINT, 'default' => 1, 'min' => 1, 'max' => 365],
            'link_id' => ['type' => self::UINT, 'default' => 0],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'status' => ['type' => self::STR, 'allowedValues' => ['moderated', 'rejected', 'awaiting_payment', 'paid'], 'default' => 'moderated'],
            'purchase_request_key' => ['type' => self::STR, 'maxLength' => 32, 'nullable' => true],
        ];
        $structure->getters = [
            'status_title' => true,
            'purchasable_type_id' => true
        ];
        $structure->relations = [
            'ApprovalQueue' => [
                'entity' => 'XF:ApprovalQueue',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', 'lfs_sticked_link_purchase'],
                    ['content_id', '=', '$purchase_id']
                ],
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Link' => [
                'entity' => 'BS\LiveForumStatistics:StickedLink',
                'type' => self::TO_ONE,
                'conditions' => 'link_id',
                'primary' => true
            ],
            'PurchaseRequest' => [
                'entity' => 'XF:PurchaseRequest',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['request_key', '=', '$purchase_request_key']
                ]
            ]
        ];

        return $structure;
    }

    /** @return UserAlert */
    protected function getAlertRepo()
    {
        return $this->repository('XF:UserAlert');
    }
}