<?php

namespace BS\LiveForumStatistics\Service\Thread;

use XF\Service\AbstractService;

class Stick extends AbstractService
{
    /** @var \XF\Entity\Thread */
    protected $thread;

    protected $logStick = true;

    public function __construct(\XF\App $app, \XF\Entity\Thread $thread)
    {
        parent::__construct($app);

        $this->setThread($thread);
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
        $this->thread = $thread;
    }

    public function setOrder($order)
    {
        $this->thread->bs_lfs_sticked_order = $order;
    }

    public function setEndDate($endDate)
    {
        $this->thread->bs_lfs_sticked_end_date = $endDate;
    }

    public function setAttributes(array $attributes)
    {
        $this->thread->bs_lfs_sticked_attributes = $attributes;
    }

    /**
     * @param bool $logStick
     */
    public function setLogStick(bool $logStick)
    {
        $this->logStick = $logStick;
    }

    public function stick()
    {
        $thread = $this->getThread();

        $thread->set('bs_lfs_is_sticked', true);

        if ($thread->save(true, false) && $this->logStick)
        {
            $this->app->logger()->logModeratorAction('thread', $thread, 'lfs_stick');
        }

        return $thread;
    }

    public function unstick()
    {
        $thread = $this->getThread();

        $thread->bulkSet([
            'bs_lfs_is_sticked' => false,
            'bs_lfs_sticked_end_date' => 0,
            'bs_lfs_sticked_order' => 0,
            'bs_lfs_sticked_attributes' => [],
        ]);

        if ($thread->save(true, false) && $this->logStick)
        {
            $this->app->logger()->logModeratorAction('thread', $thread, 'lfs_unstick');
        }

        return $thread;
    }
}