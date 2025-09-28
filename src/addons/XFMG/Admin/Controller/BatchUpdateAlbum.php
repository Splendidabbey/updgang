<?php

namespace XFMG\Admin\Controller;

class BatchUpdateAlbum extends AbstractBatchUpdate
{
	protected function getClassIdentifier()
	{
		return 'XFMG:Album';
	}

	protected function getLinkPrefix()
	{
		return 'media-gallery/batch-update/albums';
	}

	protected function getTemplatePrefix()
	{
		return 'xfmg_batch_update_albums';
	}

	protected function getSectionContext()
	{
		return 'xfmgBatchUpdateAlbums';
	}
}