<?php

namespace BS\LiveForumStatistics\Service\StickedThreadPurchase;

use BS\LiveForumStatistics\Entity\StickedThreadPurchase;
use BS\LiveForumStatistics\Repository\StickedAttribute;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /** @var StickedThreadPurchase */
    protected $purchase;

    /** @var \XF\Entity\Thread */
    protected $thread;

    /** @var StickedAttribute */
    protected $attributeRepo;

    protected $attributes;

    protected $attributesInput;

    protected $costAmount;

    protected $numberOfDays = 1;

    public function __construct(\XF\App $app, \XF\Entity\Thread $thread, $attributes)
    {
        parent::__construct($app);
        $this->setupDefaults();
        $this->setThread($thread);
        $this->attributes = $attributes;
    }

    protected function setupDefaults()
    {
        $this->purchase = $this->em()->create('BS\LiveForumStatistics:StickedThreadPurchase');
        $this->attributeRepo = $this->repository('BS\LiveForumStatistics:StickedAttribute');
        $this->costAmount = $this->app->options()->lfsStickedThreadCost;
    }

    /**
     * @return StickedThreadPurchase
     */
    public function getPurchase(): StickedThreadPurchase
    {
        return $this->purchase;
    }

    /**
     * @param StickedThreadPurchase $purchase
     */
    public function setPurchase(StickedThreadPurchase $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * @return \XF\Entity\Thread
     */
    public function getThread(): \XF\Entity\Thread
    {
        return $this->thread;
    }

    /**
     * @param \XF\Entity\Thread $thread
     */
    public function setThread(\XF\Entity\Thread $thread)
    {
        $this->purchase->thread_id = $thread->thread_id;
        $this->thread = $thread;
    }

    public function setNumberOfDays($number)
    {
        $this->numberOfDays = $number;
    }

    public function getNumberOfDays()
    {
        return $this->numberOfDays;
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
                $costAmount = $this->costAmount * $this->numberOfDays + $attributesCost;

                $this->purchase->cost_amount = $costAmount;
                $this->purchase->attributes = $attributes;
            }
        }
        else
        {
            $this->purchase->cost_amount = $this->costAmount * $this->numberOfDays;
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