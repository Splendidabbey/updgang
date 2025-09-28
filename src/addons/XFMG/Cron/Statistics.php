<?php

namespace XFMG\Cron;

class Statistics
{
	public static function cacheGalleryStatistics()
	{
		$cache = \XF::app()->simpleCache()->XFMG;
		$db = \XF::db();

		$categoryCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_mg_category
		');

		$albumCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_mg_album 
			WHERE album_state = \'visible\''
		);

		$uploadCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_mg_media_item AS mi
			LEFT JOIN xf_mg_album AS a ON
				(mi.album_id = a.album_id)
			WHERE mi.media_state = \'visible\'
			AND mi.media_type IN(\'audio\', \'image\', \'video\')
			AND IF(mi.album_id > 0, a.album_state = \'visible\', 1=1)
		');

		$embedCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_mg_media_item AS mi
			LEFT JOIN xf_mg_album AS a ON
				(mi.album_id = a.album_id)
			WHERE mi.media_state = \'visible\'
			AND mi.media_type IN(\'embed\')
			AND IF(mi.album_id > 0, a.album_state = \'visible\', 1=1)
		');

		$commentCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_mg_comment AS c
			LEFT JOIN xf_mg_media_item AS mi ON
				(c.content_type = \'xfmg_media\' AND c.content_id = mi.media_id)
			LEFT JOIN xf_mg_album AS a ON
				(c.content_type = \'xfmg_album\' AND c.content_id = a.album_id)
			WHERE c.comment_state = \'visible\'
			AND IF(mi.media_id > 0, mi.media_state = \'visible\', 1=1)
			AND IF(a.album_id > 0, a.album_state = \'visible\', 1=1)
		');

		$diskUsage = $db->fetchOne('
			SELECT SUM(attd.file_size)
			FROM xf_attachment_data AS attd
			INNER JOIN xf_attachment AS att ON
				(attd.data_id = att.data_id)
			LEFT JOIN xf_mg_media_item AS mi ON
				(att.content_type = \'xfmg_media\' AND att.content_id = mi.media_id)
			LEFT JOIN xf_mg_album AS a ON
				(mi.album_id = a.album_id)
			WHERE att.content_type = \'xfmg_media\'
			AND mi.media_state = \'visible\'
			AND IF(a.album_id > 0, a.album_state = \'visible\', 1=1)
		');

		$cache->statisticsCache = [
			'category_count' => $categoryCount,
			'album_count' => $albumCount,

			'upload_count' => $uploadCount,
			'embed_count' => $embedCount,

			'comment_count' => $commentCount,

			'disk_usage' => $diskUsage
		];
	}
}