<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class Rating extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'xfmg_rating';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:Rating';
	}

	protected function postSave($oldId, $newId)
	{
		$content = null;

		if ($this->content_type == 'xfmg_album')
		{
			/** @var \XFMG\Entity\Album $content */
			$content = $this->em()->find('XFMG:Album', $this->content_id);
		}
		else if ($this->content_type == 'xfmg_media')
		{
			/** @var \XFMG\Entity\MediaItem $content */
			$content = $this->em()->find('XFMG:MediaItem', $this->content_id);
		}

		if (!$content)
		{
			return;
		}

		$content->rating_sum += $this->rating;
		$content->rating_count += 1;

		$content->updateRatingAverage();
		$content->save(false, false);

		$this->em()->detachEntity($content);
	}
}