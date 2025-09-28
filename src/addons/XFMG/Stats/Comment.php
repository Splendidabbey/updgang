<?php

namespace XFMG\Stats;

use XF\Stats\AbstractHandler;

class Comment extends AbstractHandler
{
	public function getStatsTypes()
	{
		return [
			'xfmg_comment' => \XF::phrase('xfmg_total_comments'),
			'xfmg_comment_reaction' => \XF::phrase('xfmg_comment_reactions')
		];
	}

	public function getData($start, $end)
	{
		$db = $this->db();

		$comments = $db->fetchPairs(
			$this->getBasicDataQuery('xf_mg_comment', 'comment_date', 'comment_state = ?'),
			[$start, $end, 'visible']
		);

		$commentReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'xfmg_comment', 1]
		);

		return [
			'xfmg_comment' => $comments,
			'xfmg_comment_reaction' => $commentReactions
		];
	}
}