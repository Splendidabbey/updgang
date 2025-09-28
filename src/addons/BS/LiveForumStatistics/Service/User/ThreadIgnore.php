<?php

namespace BS\LiveForumStatistics\Service\User;

use XF\Service\AbstractService;

class ThreadIgnore extends AbstractService
{
    protected $ignoredBy;

    protected $ignoredThread;

    public function __construct(\XF\App $app, \XF\Entity\Thread $ignoredThread, \XF\Entity\User $ignoredBy = null)
    {
        parent::__construct($app);

        $this->ignoredThread = $ignoredThread;
        $this->ignoredBy = $ignoredBy ?: \XF::visitor();
    }

    public function ignore()
    {
        $threadIgnored = $this->em()->create('BS\LiveForumStatistics:UserThreadIgnored');
        $threadIgnored->user_id = $this->ignoredBy->user_id;
        $threadIgnored->thread_id = $this->ignoredThread->thread_id;
        $threadIgnored->save(false);

        try
        {
            $threadIgnored->save(false);
        }
        catch (\XF\Db\DuplicateKeyException $e)
        {
            $dupe = $this->em()->findOne('BS\LiveForumStatistics:UserThreadIgnored', [
                'user_id' => $this->ignoredBy->user_id,
                'thread_id' => $this->ignoredThread->thread_id
            ]);
            if ($dupe)
            {
                $threadIgnored = $dupe;
            }
        }

        return $threadIgnored;
    }

    public function unignore()
    {
        $threadIgnored = $this->em()->findOne('BS\LiveForumStatistics:UserThreadIgnored', [
            'user_id' => $this->ignoredBy->user_id,
            'thread_id' => $this->ignoredThread->thread_id
        ]);

        if ($threadIgnored)
        {
            $threadIgnored->delete(false);
        }

        return $threadIgnored;
    }
}