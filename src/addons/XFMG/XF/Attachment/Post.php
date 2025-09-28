<?php

namespace XFMG\XF\Attachment;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class Post extends XFCP_Post
{
	public function onNewAttachment(Attachment $attachment, \XF\FileWrapper $file)
	{
		parent::onNewAttachment($attachment, $file);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var \XFMG\Service\Media\MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentInserted($attachment, $file);
	}

	public function onAssociation(Attachment $attachment, Entity $container = null)
	{
		parent::onAssociation($attachment, $container);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var \XFMG\Service\Media\MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentAssociated($attachment);
	}

	public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
	{
		parent::onAttachmentDelete($attachment, $container);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var \XFMG\Service\Media\MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentDeleted($attachment);
	}
}