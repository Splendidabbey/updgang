<?php

namespace XFMG\Stats;

use XF\Stats\AbstractHandler;

class Media extends AbstractHandler
{
	public function getStatsTypes()
	{
		return [
			'xfmg_media' => \XF::phrase('xfmg_media_items'),
			'xfmg_media_disk' => \XF::phrase('xfmg_media_disk_usage_mb'),
			'xfmg_media_reaction' => \XF::phrase('xfmg_media_reactions'),
			'xfmg_media_rating' => \XF::phrase('xfmg_media_ratings'),
			'xfmg_media_comment' => \XF::phrase('xfmg_media_comments')
		];
	}

	public function getData($start, $end)
	{
		$db = $this->db();

		$media = $db->fetchPairs(
			$this->getBasicDataQuery('xf_mg_media_item', 'media_date', 'media_state = ?'),
			[$start, $end, 'visible']
		);

		$mediaDiskUsage = $db->fetchPairs(
			$this->getBasicDataQuery('
				xf_attachment AS a
				INNER JOIN xf_attachment_data AS ad ON
					(a.data_id = ad.data_id)',
				'ad.upload_date',
				'ad.attach_count > ? AND a.content_type = ?',
				'SUM(ad.file_size)'),
			[$start, $end, 0, 'xfmg_media']
		);

		$mediaReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'xfmg_media', 1]
		);

		$mediaRatings = $db->fetchPairs(
			$this->getBasicDataQuery('xf_mg_rating', 'rating_date', 'content_type = ?'),
			[$start, $end, 'xfmg_media']
		);

		$mediaComments = $db->fetchPairs(
			$this->getBasicDataQuery('xf_mg_comment', 'comment_date', 'content_type = ?'),
			[$start, $end, 'xfmg_media']
		);

		return [
			'xfmg_media' => $media,
			'xfmg_media_disk' => $mediaDiskUsage,
			'xfmg_media_reaction' => $mediaReactions,
			'xfmg_media_rating' => $mediaRatings,
			'xfmg_media_comment' => $mediaComments
		];
	}

	public function adjustStatValue($statsType, $counter)
	{
		if ($statsType == 'xfmg_media_disk')
		{
			return round($counter / 1048576, 2); // megabytes
		}
		return parent::adjustStatValue($statsType, $counter);
	}
}