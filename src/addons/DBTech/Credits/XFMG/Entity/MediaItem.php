<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFMG\Entity;

use DBTech\Credits\Helper;

/**
 * @extends \XFMG\Entity\MediaItem
 */
class MediaItem extends XFCP_MediaItem
{
	/**
	 * @throws \Exception
	 */
	protected function _preSave()
	{
		// Do parent stuff
		parent::_preSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isUpdate())
		{
			$visibilityChange = $this->isStateChanged('media_state', 'visible');
			if ($visibilityChange == 'leave')
			{
				// Undo the event

				$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

				if ($this->Attachment)
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->testUndo([
							'multiplier' => $this->Attachment->getFileSize(),
							'extension'  => $this->Attachment->getExtension(),
						], $this->User)
					;
				}
				else
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->testUndo([], $this->User)
					;
				}
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event

				$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

				if ($this->Attachment)
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->testApply([
							'multiplier' => $this->Attachment->getFileSize(),
							'extension'  => $this->Attachment->getExtension(),
						], $this->User)
					;
				}
				else
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->testApply([], $this->User)
					;
				}
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		// Do parent stuff
		parent::_postSave();

		if (!$this->user_id)
		{
			return;
		}

		if ($this->isUpdate())
		{
			$visibilityChange = $this->isStateChanged('media_state', 'visible');
			if ($visibilityChange == 'leave')
			{
				// Undo the event

				$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

				if ($this->Attachment)
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->undo($this->media_id, [
							'multiplier' => $this->Attachment->getFileSize(),
							'extension'  => $this->Attachment->getExtension(),
							'content_type' => 'xfmg_media',
							'content_id'   => $this->media_id
						], $this->User)
					;
				}
				else
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->undo($this->media_id, [
							'content_type' => 'xfmg_media',
							'content_id'   => $this->media_id
						], $this->User)
					;
				}
			}
			elseif ($visibilityChange == 'enter')
			{
				// Reapply the event

				$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

				if ($this->Attachment)
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->apply($this->media_id, [
							'multiplier' => $this->Attachment->getFileSize(),
							'extension'  => $this->Attachment->getExtension(),
							'content_type' => 'xfmg_media',
							'content_id'   => $this->media_id
						], $this->User)
					;
				}
				else
				{
					$eventTriggerRepo->getHandler('galleryupload')
						->apply($this->media_id, [
							'content_type' => 'xfmg_media',
							'content_id'   => $this->media_id
						], $this->User)
					;
				}
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _preDelete()
	{
		// Do parent stuff
		parent::_preDelete();

		if (!$this->user_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if ($this->Attachment)
		{
			$eventTriggerRepo->getHandler('galleryupload')
				->testUndo([
					'multiplier' => $this->Attachment->getFileSize(),
					'extension'  => $this->Attachment->getExtension(),
				], $this->User)
			;
		}
		else
		{
			$eventTriggerRepo->getHandler('galleryupload')
				->testUndo([], $this->User)
			;
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		// Do parent stuff
		parent::_postDelete();

		if (!$this->user_id)
		{
			return;
		}

		$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

		if ($this->Attachment)
		{
			$eventTriggerRepo->getHandler('galleryupload')
				->undo($this->media_id, [
					'multiplier' => $this->Attachment->getFileSize(),
					'extension'  => $this->Attachment->getExtension(),
					'content_type' => 'xfmg_media',
					'content_id' => $this->media_id
				], $this->User)
			;
		}
		else
		{
			$eventTriggerRepo->getHandler('galleryupload')
				->undo($this->media_id, [
					'content_type' => 'xfmg_media',
					'content_id' => $this->media_id
				], $this->User)
			;
		}
	}
}