<?php

namespace XFRM\Service\ResourceUpdate;

use XFRM\Entity\ResourceUpdate;

class Edit extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var ResourceUpdate
	 */
	protected $update;

	/**
	 * @var \XFRM\Service\ResourceUpdate\Preparer
	 */
	protected $updatePreparer;

	protected $oldMessage;

	/**
	 * @var int
	 */
	protected $logDelay;

	/**
	 * @var bool
	 */
	protected $logEdit = true;

	/**
	 * @var bool
	 */
	protected $logHistory = true;

	protected $performValidations = true;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(\XF\App $app, ResourceUpdate $update)
	{
		parent::__construct($app);

		$this->update = $update;
		$this->updatePreparer = $this->service('XFRM:ResourceUpdate\Preparer', $update);
	}

	public function getUpdate()
	{
		return $this->update;
	}

	public function getUpdatePreparer()
	{
		return $this->updatePreparer;
	}

	/**
	 * @param int $logDelay
	 */
	public function logDelay($logDelay)
	{
		$this->logDelay = $logDelay;
	}

	/**
	 * @param bool $logEdit
	 */
	public function logEdit($logEdit)
	{
		$this->logEdit = $logEdit;
	}

	/**
	 * @param bool $logHistory
	 */
	public function logHistory($logHistory)
	{
		$this->logHistory = $logHistory;
	}

	public function setPerformValidations($perform)
	{
		$this->performValidations = (bool)$perform;
	}

	public function getPerformValidations()
	{
		return $this->performValidations;
	}

	public function setIsAutomated()
	{
		$this->setPerformValidations(false);
	}

	public function setMessage($message, $format = true)
	{
		$setupHistory = !$this->update->isChanged('message');
		$oldMessage = $this->update->message;

		$result = $this->updatePreparer->setMessage($message, $format, $this->performValidations);

		if ($setupHistory && $result && $this->update->isChanged('message'))
		{
			$this->setupEditHistory($oldMessage);
		}

		return $result;
	}

	protected function setupEditHistory($oldMessage)
	{
		$this->update->edit_count++;

		$options = $this->app->options();
		if ($options->editLogDisplay['enabled'] && $this->logEdit)
		{
			$delay = is_null($this->logDelay)
				? $options->editLogDisplay['delay'] * 60
				: $this->logDelay;

			if ($this->update->post_date + $delay <= \XF::$time)
			{
				$this->update->last_edit_date = \XF::$time;
				$this->update->last_edit_user_id = \XF::visitor()->user_id;
			}
		}

		if ($options->editHistory['enabled'] && $this->logHistory)
		{
			$this->oldMessage = $oldMessage;
		}
	}

	public function setTitle($title)
	{
		$this->update->title = $title;
	}

	public function setAttachmentHash($hash)
	{
		$this->updatePreparer->setAttachmentHash($hash);
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool)$alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function checkForSpam()
	{
		if ($this->update->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->updatePreparer->checkForSpam();
		}
	}

	protected function finalSetup()
	{

	}

	protected function _validate()
	{
		$this->finalSetup();

		$this->update->preSave();
		return $this->update->getErrors();
	}

	protected function _save()
	{
		$update = $this->update;
		$visitor = \XF::visitor();

		$db = $this->db();
		$db->beginTransaction();

		$update->save(true, false);

		$this->updatePreparer->afterUpdate();

		if ($this->oldMessage)
		{
			/** @var \XF\Repository\EditHistory $repo */
			$editHistoryRepo = $this->repository('XF:EditHistory');
			$editHistoryRepo->insertEditHistory(
				'resource_update',
				$update,
				$visitor,
				$this->oldMessage,
				$this->app->request()->getIp()
			);
		}

		if ($update->message_state == 'visible' && $this->alert && $update->Resource->user_id != $visitor->user_id)
		{
			/** @var \XFRM\Repository\ResourceUpdate $updateRepo */
			$updateRepo = $this->repository('XFRM:ResourceUpdate');
			$updateRepo->sendModeratorActionAlert($this->update, 'edit', $this->alertReason);
		}

		$db->commit();

		return $update;
	}
}