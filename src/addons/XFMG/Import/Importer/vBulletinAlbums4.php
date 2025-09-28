<?php

namespace XFMG\Import\Importer;

use XF\Import\StepState;

class vBulletinAlbums4 extends vBulletinAlbums3
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'vBulletin Albums 4.2',
		];
	}

	protected function getAttachPathConfig(array &$baseConfig, \XF\Db\Mysqli\Adapter $sourceDb)
	{
		try
		{
			$options = $sourceDb->fetchPairs("
				SELECT varname, value
				FROM setting
				WHERE varname IN('attachfile', 'attachpath')
			");
		}
		catch (\XF\Db\Exception $e) {}

		if (!empty($options))
		{
			if (!empty($options['attachfile']) && !empty($options['attachpath']))
			{
				$baseConfig['attachpath'] = trim($options['attachpath']);
			}
		}
	}

	// ############################## STEP: MEDIA ITEMS #########################

	public function getStepEndMediaItems()
	{
		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		return $this->sourceDb->fetchOne("
			SELECT 
				MAX(att.attachmentid)
			FROM attachment AS att
			LEFT JOIN filedata AS f ON (att.filedataid = f.filedataid)
			WHERE f.extension IN($extensionsQuoted) AND att.state = 'visible' AND att.contenttypeid = '8'
		") ?: 0;
	}

	protected function getStepDataMediaItems(StepState $state, $limit = 500)
	{
		$extensionsQuoted = $this->sourceDb->quote($this->importableTypes);

		return $this->sourceDb->fetchAll("
			SELECT
				att.*, 
				f.*,
				u.username,
				a.*,
				att.attachmentid AS pictureid
			FROM attachment AS att
			LEFT JOIN album AS a ON (att.contentid = a.albumid)
			LEFT JOIN filedata AS f ON (att.filedataid = f.filedataid)
			LEFT JOIN user AS u ON (att.userid = u.userid)
			WHERE att.attachmentid > ? AND att.attachmentid <= ? AND f.extension IN($extensionsQuoted) AND att.state = 'visible' AND att.contenttypeid = '8'
			ORDER BY att.attachmentid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
	}

	protected function getSourceFilePath(array $mediaItem, array $stepConfig)
	{
		return sprintf(
			'%s/%s/%d.attach',
			$stepConfig['path'],
			implode('/', str_split($mediaItem['userid'])),
			$mediaItem['filedataid']
		);
	}

	// ############################## STEP: COMMENTS #########################

	public function getStepEndComments()
	{
		return $this->sourceDb->fetchOne("SELECT MAX(commentid) FROM picturecomment") ?: 0;
	}

	protected function getStepDataComments(StepState $state, $limit = 500)
	{
		return $this->sourceDb->fetchAll("
			SELECT
				c.*,
				u.*,
				IF(u.username IS NULL, c.postusername, u.username) AS username,
			    c.filedataid AS pictureid
			FROM picturecomment AS c
			LEFT JOIN user AS u ON (c.userid = u.userid)
			WHERE
				c.commentid > ? AND c.commentid <= ?
			ORDER BY
				c.commentid
			LIMIT {$limit}
		", [$state->startAfter, $state->end]);
	}
}