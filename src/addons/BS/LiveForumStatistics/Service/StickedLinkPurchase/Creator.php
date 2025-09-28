<?php

namespace BS\LiveForumStatistics\Service\StickedLinkPurchase;

use BS\LiveForumStatistics\Entity\StickedLinkPurchase;
use BS\LiveForumStatistics\Repository\StickedAttribute;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /** @var StickedLinkPurchase */
    protected $purchase;

    /** @var StickedAttribute */
    protected $attributeRepo;

    protected $attributes;

    protected $attributesInput;

    protected $costAmount;

    public function __construct(\XF\App $app, $attributes)
    {
        parent::__construct($app);
        $this->setupDefaults();
        $this->attributes = $attributes;
    }

    protected function setupDefaults()
    {
        $this->purchase = $this->em()->create('BS\LiveForumStatistics:StickedLinkPurchase');
        $this->attributeRepo = $this->repository('BS\LiveForumStatistics:StickedAttribute');
        $this->costAmount = $this->app->options()->lfsStickedLinkCost;
    }

    /**
     * @return StickedLinkPurchase
     */
    public function getPurchase(): StickedLinkPurchase
    {
        return $this->purchase;
    }

    /**
     * @param StickedLinkPurchase $purchase
     */
    public function setPurchase(StickedLinkPurchase $purchase)
    {
        $this->purchase = $purchase;
    }

    public function setTitle($title)
    {
        $this->purchase->title = $title;
    }

    public function setLink($link)
    {
        $this->purchase->link = $link;
    }

    public function setNumberOfDays($number)
    {
        $this->purchase->number_of_days = $number;
    }

    public function getAttributesInput()
    {
        return $this->attributesInput;
    }

    /**
     * @param array $attributesInput
     */
    public function setAttributesInput($attributesInput)
    {
        $this->attributesInput = $attributesInput;
    }

    protected function finalSetup()
    {
        if (! empty($this->attributesInput))
        {
            list($attributes, $attributesCost) = $this->attributeRepo->getFinalAttributesAndCost($this->attributes, $this->attributesInput, $error);

            if ($error)
            {
                $this->purchase->error($error, 'attributes');
            }
            else
            {
                $costAmount = $this->costAmount * $this->purchase->number_of_days + $attributesCost;

                $this->purchase->cost_amount = $costAmount;
                $this->purchase->attributes = $attributes;
            }
        }
        else
        {
            $this->purchase->cost_amount = $this->costAmount * $this->purchase->number_of_days;
        }
    }

    protected function _validate()
    {
        $this->finalSetup();

        $purchase = $this->purchase;

        $purchase->preSave();

        return $purchase->getErrors();
    }

    protected function _save()
    {
        $purchase = $this->purchase;

        $purchase->save(true, false);

        return $purchase;
    }
}