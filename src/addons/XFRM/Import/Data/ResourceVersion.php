<?php

namespace XFRM\Import\Data;

use XF\Import\Data\AbstractEmulatedData;
use XF\Import\Data\Attachment;
use XF\Import\Data\HasDeletionLogTrait;

class ResourceVersion extends AbstractEmulatedData
{
	use HasDeletionLogTrait;

	/**
	 * @var Attachment[]
	 */
	protected $attachments;

	public function getImportType()
	{
		return 'resource_version';
	}

	protected function getEntityShortName()
	{
		return 'XFRM:ResourceVersion';
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

	public function addAttachment($oldId, $attachment)
	{
		$this->attachments[$oldId] = $attachment;
	}

	public function postSave($oldId, $newId)
	{
		$this->insertStateRecord($this->version_state, $this->release_date);

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