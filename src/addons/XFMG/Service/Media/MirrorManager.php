<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;
use XFMG\XF\Entity\Attachment;

class MirrorManager extends AbstractService
{
	public function attachmentInserted(Attachment $attachment, \XF\FileWrapper $file)
	{
		$this->insertAttachmentExif($attachment, $file->getExif() ?: []);
	}

	public function attachmentAssociated(Attachment $attachment)
	{
		if (!$this->canCreateMirror($attachment))
		{
			return;
		}

		$mirrorCreator = $this->setupMirrorCreator($attachment);
		if (!$mirrorCreator || !$mirrorCreator->isMirrorable())
		{
			return;
		}

		$mirrorCreator->save();
		$mirrorCreator->sendNotifications();
	}

	public function insertAttachmentExif(Attachment $attachment, array $exif)
	{
		if ($exif)
		{
			$this->db()->insert('xf_mg_attachment_exif', [
				'attachment_id' => $attachment->attachment_id,
				'attach_date' => $attachment->attach_date,
				'exif_data' => json_encode($exif, JSON_PARTIAL_OUTPUT_ON_ERROR)
			], false, 'exif_data = VALUES(exif_data)');
		}
	}

	protected function canCreateMirror(Attachment $attachment): bool
	{
		if ($attachment->xfmg_is_mirror_handler)
		{
			// we're already managing this attachment
			return false;
		}

		$data = $attachment->Data;
		if (!$data || $data->xfmg_mirror_media_id)
		{
			// there's already an existing mirror, so assume another attachment is managing it
			return false;
		}

		if (!$attachment->xfmg_media_type)
		{
			return false;
		}

		// if we find a media item for this attachment, never create a second one
		$attachmentReferences = $this->repository('XF:Attachment')
			->findAttachmentsByDataId($attachment->data_id)
			->fetch();
		foreach ($attachmentReferences AS $attachmentReference)
		{
			/** @var Attachment $attachmentReference */
			if ($attachmentReference->attachment_id == $attachment->attachment_id)
			{
				continue;
			}

			if ($attachmentReference->content_type == 'xfmg_media')
			{
				return false;
			}
		}

		return true;
	}

	public function syncMirrorState(Attachment $attachment)
	{
		if (!$attachment->xfmg_media_type)
		{
			// not something we can mirror
			return;
		}

		$data = $attachment->Data;
		if (!$data)
		{
			return;
		}

		if ($data->xfmg_mirror_media_id && !$attachment->xfmg_is_mirror_handler)
		{
			// this attachment's data is already mirrored but controlled by something else so give that priority
			return;
		}

		$category = $attachment->getXfmgMirrorContainer();
		if (!$category)
		{
			if ($data->xfmg_mirror_media_id)
			{
				$this->unlinkAttachmentFromMirror($attachment, false);
			}
			// otherwise do nothing
		}
		else
		{
			if (!$data->xfmg_mirror_media_id)
			{
				$mirrorCreator = $this->setupMirrorCreator($attachment, $category);
				if ($mirrorCreator && $mirrorCreator->isMirrorable())
				{
					// Only mirror the media if it's going to be visible to prevent a large amount of unapproved
					// media, for example. This also serves to only create media for visible content (assuming
					// this is accounted for when creating the media).
					if ($mirrorCreator->getMediaItem()->media_state == 'visible')
					{
						$mirrorCreator->save();
					}
					// note: notifications are not sent in this case intentionally
				}
			}
			else
			{
				/** @var \XFMG\Entity\MediaItem $mirror */
				$mirror = $data->XfmgMirrorMedia;
				if ($mirror && $mirror->category_id != $category->category_id)
				{
					$mirror->category_id = $category->category_id;
					$mirror->save();
				}
			}
		}
	}

	public function syncMirrorStateForContent(string $contentType, array $contentIds)
	{
		$attachments = $this->finder('XF:Attachment')
			->where([
				'content_type' => $contentType,
				'content_id' => $contentIds
			])
			->with('Data', true)
			->with('Data.XfmgMirrorMedia')
			->fetch();
		foreach ($attachments AS $attachment)
		{
			$this->syncMirrorState($attachment);
		}
	}

	public function attachmentDeleted(Attachment $attachment)
	{
		$this->unlinkAttachmentFromMirror($attachment, true);
	}

	/**
	 * @param Attachment                 $attachment
	 * @param \XFMG\Entity\Category|null $category
	 *
	 * @return MirrorCreator|null
	 */
	protected function setupMirrorCreator(Attachment $attachment, \XFMG\Entity\Category $category = null)
	{
		if (!$this->canCreateMirror($attachment))
		{
			return null;
		}

		if (!$category)
		{
			$category = $attachment->getXfmgMirrorContainer();
		}

		if (!$category)
		{
			return null;
		}

		/** @var \XFMG\Service\Media\MirrorCreator $mirrorCreator */
		$mirrorCreator = \XF::app()->service('XFMG:Media\MirrorCreator', $attachment);
		$mirrorCreator->setCategory($category);

		$exif = $this->db()->fetchOne('SELECT exif_data FROM xf_mg_attachment_exif WHERE attachment_id = ?', $attachment->attachment_id);
		if ($exif)
		{
			$mirrorCreator->setExif(json_decode($exif, true) ?: []);
		}

		switch ($this->getAttachmentVisibilityState($attachment))
		{
			case 'visible':
				// do nothing - let the category-based rule apply
				break;

			case 'moderated':
				$mirrorCreator->setMediaState('moderated');
				break;

			case 'deleted':
			default:
				// don't create media where the container is deleted
				return null;
		}

		$success = $this->applyTypeBasedCreatorChanges($attachment, $mirrorCreator);
		if (!$success)
		{
			return null;
		}

		return $mirrorCreator;
	}

	protected function applyTypeBasedCreatorChanges(
		Attachment $attachment,
		\XFMG\Service\Media\MirrorCreator $mirrorCreator
	): bool
	{
		return true;
	}

	protected function getAttachmentVisibilityState(Attachment $attachment): string
	{
		if ($attachment->content_type == 'post')
		{
			/** @var \XF\Entity\Post $post */
			$post = $attachment->Container;

			if (!$post)
			{
				// if the post has been removed, treat the attachment visibility as deleted
				return 'deleted';
			}

			$state = $post->message_state;
			if ($post->message_state == 'visible')
			{
				// the post/attachment is visible, so the thread's state is effectively the attachment state
				$state = $post->Thread->discussion_state;
			}

			return $state;
		}

		return 'visible';
	}

	/**
	 * @param Attachment $attachment
	 * @param \XFMG\Entity\Category $newCategory Returns the new category (always returned if an attachment is returned)
	 *
	 * @return Attachment|null
	 */
	protected function getNextPossibleMirrorHandler(Attachment $attachment, &$newCategory = null)
	{
		$attachmentReferences = $this->repository('XF:Attachment')
			->findAttachmentsByDataId($attachment->data_id)
			->fetch();

		foreach ($attachmentReferences AS $attachmentReference)
		{
			/** @var Attachment $attachmentReference */
			if ($attachmentReference->attachment_id == $attachment->attachment_id)
			{
				continue;
			}

			if ($attachmentReference->content_type == 'xfmg_media')
			{
				// this can never be an auto media manager
				continue;
			}

			if ($this->getAttachmentVisibilityState($attachmentReference) !== 'visible')
			{
				// only a visible attachment can be a handler after the fact
				continue;
			}

			$category = $attachmentReference->getXfmgMirrorContainer();
			if ($category)
			{
				$newCategory = $category;
				return $attachmentReference;
			}
		}

		return null;
	}

	protected function unlinkAttachmentFromMirror(Attachment $attachment, bool $isAttachmentDelete)
	{
		if (!$attachment->xfmg_is_mirror_handler)
		{
			return;
		}

		/** @var \XFMG\Entity\MediaItem $mirrorMedia */
		$mirrorMedia = $attachment->XfmgMirrorMedia;

		$newAttachment = $this->getNextPossibleMirrorHandler($attachment, $newCategory);
		if ($newAttachment)
		{
			if (!$isAttachmentDelete)
			{
				$attachment->fastUpdate('xfmg_is_mirror_handler', 0);
			}
			$newAttachment->fastUpdate('xfmg_is_mirror_handler', 1);

			if ($mirrorMedia && $mirrorMedia->category_id != $newCategory->category_id)
			{
				$mirrorMedia->category_id = $newCategory->category_id;
				$mirrorMedia->save(true, false);
			}
		}
		else
		{
			// we didn't find anything that can take over, so we need to delete the media
			if (!$isAttachmentDelete)
			{
				$attachment->fastUpdate('xfmg_is_mirror_handler', 0);
			}

			if ($mirrorMedia)
			{
				$mirrorMedia->setOption('log_moderator', false);
				$mirrorMedia->delete(true, false);
				// TODO: should this soft delete? Maybe if there are comments? Might change the is_mirror_handler change
			}
		}
	}

	public function attachmentContainerHidden(string $contentType, array $contentIds)
	{
		$mirroredAttachments = $this->repository('XFMG:Media')->getMirroredAttachmentsForContent(
			$contentType,
			$contentIds
		);
		foreach ($mirroredAttachments AS $attachment)
		{
			/** @var \XFMG\Entity\MediaItem $media */
			$media = $attachment->Data->XfmgMirrorMedia;
			if ($media)
			{
				$media->softDelete();
				// note that we don't automatically undelete these, to avoid a situation where explicitly
				// deleted/rejected mirrors are undeleted
			}
		}
	}
}