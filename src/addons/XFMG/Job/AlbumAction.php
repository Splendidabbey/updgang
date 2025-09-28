<?php

namespace XFMG\Job;

class AlbumAction extends AbstractBatchUpdateAction
{
	protected function getColumn()
	{
		return 'album_id';
	}

	protected function getClassIdentifier()
	{
		return 'XFMG:Album';
	}

	protected function applyInternalItemChange(\XF\Mvc\Entity\Entity $album)
	{
		if ($this->getActionValue('approve'))
		{
			$album->album_state = 'visible';
		}
		if ($this->getActionValue('unapprove'))
		{
			$album->album_state = 'moderated';
		}
		if ($this->getActionValue('soft_delete'))
		{
			$album->album_state = 'deleted';
		}
	}

	protected function getTypePhrase()
	{
		return \XF::phrase('xfmg_albums');
	}
}