<?php

namespace DBTech\Credits\ApprovalQueue;

use DBTech\Credits\Helper;
use XF\ApprovalQueue\AbstractHandler;
use XF\Entity\ApprovalQueue;
use XF\Mvc\Entity\Entity;

/**
 * Class Transaction
 *
 * @package DBTech\Credits\ApprovalQueue
 */
class Transaction extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canViewContent(Entity $content, &$error = null): bool
	{
		return true;
	}

	/**
	 * @param Entity $content
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canActionContent(Entity $content, &$error = null): bool
	{
		/** @var $content \DBTech\Credits\Entity\Transaction */
		return $content->canApproveUnapprove($error);
	}

	/**
	 * @param ApprovalQueue $unapprovedItem
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getTemplateData(ApprovalQueue $unapprovedItem): array
	{
		$data = parent::getTemplateData($unapprovedItem);

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);
		$eventTrigger = $eventTriggerRepo->getHandler($unapprovedItem->Content->event_trigger_id);

		$data['eventTrigger'] = $eventTrigger;

		return $data;
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return [
			'Event',
			'Currency',
			'TargetUser',
			'SourceUser'
		];
	}

	/**
	 * @return array
	 */
	public function getDefaultActions(): array
	{
		return [
			'' => \XF::phrase('do_nothing'),
			'approve' => \XF::phrase('approve'),
			'reject' => \XF::phrase('reject')
		];
	}

	/**
	 * @param \DBTech\Credits\Entity\Transaction $transaction
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function actionApprove(\DBTech\Credits\Entity\Transaction $transaction)
	{
		$notify = $this->getInput('notify', $transaction->transaction_id);

		$approver = Helper::service(\DBTech\Credits\Service\Transaction\Approve::class, $transaction);
		$approver->setNotify($notify);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	/**
	 * @param \DBTech\Credits\Entity\Transaction $transaction
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function actionReject(\DBTech\Credits\Entity\Transaction $transaction)
	{
		$notify = $this->getInput('notify', $transaction->transaction_id);
		$reason = $this->getInput('reason', $transaction->transaction_id);

		$approver = Helper::service(\DBTech\Credits\Service\Transaction\Approve::class, $transaction);
		$approver->setNotify($notify);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->setReason($reason);
		$approver->reject();
	}
}