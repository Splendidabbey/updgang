<?php

namespace BS\LiveForumStatistics\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;

class StickedLinkPurchase extends AbstractHandler
{
    protected function canViewContent(Entity $content, &$error = null)
    {
        return true;
    }

    protected function canActionContent(Entity $content, &$error = null)
    {
        return $content->canApproveUnapprove();
    }

    public function actionApprove(\BS\LiveForumStatistics\Entity\StickedLinkPurchase $stickedLinkPurchase)
    {
        $this->quickUpdate($stickedLinkPurchase, 'status', 'awaiting_payment');

        if (\XF::options()->lfsSendEmailStickedLink)
        {
            \XF::app()->mailer()->newMail()
                ->setTemplate('lfs_sticked_link_approved', [
                    'link' => $stickedLinkPurchase,
                    'user' => $stickedLinkPurchase->User
                ])
                ->setToUser($stickedLinkPurchase->User)
                ->send();
        }
    }

    public function actionDelete(\BS\LiveForumStatistics\Entity\StickedLinkPurchase $stickedLinkPurchase)
    {
        $this->quickUpdate($stickedLinkPurchase, 'status', 'rejected');
    }
}