<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\Attachment;
use XF\Import\Data\HasDeletionLogTrait;

class ResourceUpdate extends AbstractEmulatedData
{
	use HasDeletionLogTrait;

	protected $loggedIp;
	protected $ipUser;

	/**
	 * @var Attachment[]
	 */
	protected $attachments;

	public function getImportType()
	{
		return 'resource_update';
	}

	protected function getEntityShortName()
	{
		return 'XFRM:ResourceUpdate';
	}

	protected function requiresCustomStructure(): bool
	{
		return true;
	}

	protected function customizeStructure(\XF\Mvc\Entity\Structure $structure)
	{
		$structure->columns['team_user_id']['default'] = 0;
		unset($structure->columns['team_user_id']['required']);

		$structure->columns['team_username']['default'] = '';
		unset($structure->columns['team_username']['required']);
	}

	public function setLoggedIp($loggedIp, $userId)
	{
		$this->loggedIp = $loggedIp;
		$this->ipUser = $userId;
	}

	public function addAttachment($oldId, $attachment)
	{
		$this->attachments[$oldId] = $attachment;
	}

	public function postSave($oldId, $newId)
	{
		$this->logIp($this->loggedIp, $this->post_date, ['user_id' => $this->ipUser]);
		$this->insertStateRecord($this->message_state, $this->post_date);

		if ($this->attachments)
		{
			foreach ($this->attachments AS $oldAttachmentId => $attachment)
			{
				$attachment->content_id = $newId;
				$attachment->log(false);
				$attachment->checkExisting(false);
				$attachment->useTransaction(false);

				$attachment->save($oldAttachmentId);
			}
		}
	}
}