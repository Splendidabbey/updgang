<?php

namespace BS\LiveForumStatistics\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null purchase_id
 * @property int thread_id
 * @property array attributes
 * @property int end_date
 * @property int create_date
 * @property float cost_amount
 * @property string cost_currency
 * @property bool is_payed
 * @property string|null purchase_request_key
 *
 * GETTERS
 * @property string title
 * @property string purchasable_type_id
 *
 * RELATIONS
 * @property \XF\Entity\Thread Thread
 * @property \XF\Entity\PurchaseRequest|null PurchaseRequest
 */

class StickedThreadPurchase extends Entity
{
    public function canPay()
    {
        return \XF::visitor()->canPurchaseThreadInLfs($this->Thread);
    }

    public function getTitle()
    {
        return \XF::phrase('lfs_stick_thread_x', [
            'title' => $this->Thread->title
        ]);
    }

    public function stick()
    {
        $this->Thread->fastUpdate([
            'bs_lfs_is_sticked' => true,
            'bs_lfs_sticked_end_date' => $this->end_date,
            'bs_lfs_sticked_attributes' => $this->attributes
        ]);
    }

    public function getPurchasableTypeId()
    {
        return 'lfs_sticked_thread';
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_lfs_sticked_thread_purchase';
        $structure->shortName = 'BS\LiveForumStatistics:StickedThreadPurchase';
        $structure->primaryKey = 'purchase_id';
        $structure->columns = [
            'purchase_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'thread_id' => ['type' => self::UINT, 'default' => 0],
            'cost_amount' => ['type' => self::FLOAT, 'default' => 0, 'min' => 0, 'max' => 99999999.99],
            'cost_currency' => ['type' => self::STR, 'default' => \XF::options()->lfsCurrency],
            'attributes' => ['type' => self::JSON_ARRAY, 'default' => []],
            'create_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'end_date' => ['type' => self::UINT, 'default' => 0],
            'is_payed' => ['type' => self::BOOL, 'default' => false],
            'purchase_request_key' => ['type' => self::STR, 'maxLength' => 32, 'nullable' => true],
        ];
        $structure->getters = [
            'title' => true,
            'purchasable_type_id' => true
        ];
        $structure->relations = [
            'Thread' => [
                'entity' => 'XF:Thread',
                'type' => self::TO_ONE,
                'conditions' => 'thread_id',
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
}