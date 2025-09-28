<?php

namespace XFMG\Admin\Controller;

class BatchUpdateMedia extends AbstractBatchUpdate
{
	protected function getClassIdentifier()
	{
		return 'XFMG:MediaItem';
	}

	protected function getLinkPrefix()
	{
		return 'media-gallery/batch-update/media';
	}

	protected function getTemplatePrefix()
	{
		return 'xfmg_batch_update_media';
	}

	protected function getSectionContext()
	{
		return 'xfmgBatchUpdateMedia';
	}
}