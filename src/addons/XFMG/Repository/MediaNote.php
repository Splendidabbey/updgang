<?php

namespace XFMG\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class MediaNote extends Repository
{
	/**
	 * @param int $mediaId
	 *
	 * @return Finder
	 */
	public function findNotesForMedia($mediaId)
	{
		return $this->finder('XFMG:MediaNote')
			->where([
				'media_id' => $mediaId
			])->setDefaultOrder('note_id');
	}

	public function pruneUnapprovedTags($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = \XF::$time - 86400 * 7;
		}

		$this->db()->delete(
			'xf_mg_media_note',
			'tag_state_date < ? AND note_type = ? AND tag_state IN(\'pending\', \'rejected\')',
			[$cutOff, 'user_tag']
		);
	}
}