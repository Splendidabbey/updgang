<?php

namespace XFRM\Stats;

use XF\Stats\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getStatsTypes()
	{
		return [
			'resource' => \XF::phrase('xfrm_resources'),
			'resource_update' => \XF::phrase('xfrm_resource_updates'),
			'resource_reaction' => \XF::phrase('xfrm_resource_reactions'),
			'resource_rating' => \XF::phrase('xfrm_resource_ratings')
		];
	}

	public function getData($start, $end)
	{
		$db = $this->db();

		$resources = $db->fetchPairs(
			$this->getBasicDataQuery('xf_rm_resource', 'resource_date', 'resource_state = ?'),
			[$start, $end, 'visible']
		);

		$resourceUpdates = $db->fetchPairs(
			$this->getBasicDataQuery('xf_rm_resource_update', 'post_date', 'message_state = ?'),
			[$start, $end, 'visible']
		);
		// deduct the number of resources posted in that same rate range so we only include actual updates,
		// not the description
		foreach ($resourceUpdates AS $key => &$value)
		{
			if (isset($resources[$key])) {
				$value = max(0, $value - $resources[$key]);
			}
		}

		$resourceReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'resource_update', 1]
		);

		$resourceRatings = $db->fetchPairs(
			$this->getBasicDataQuery('xf_rm_resource_rating', 'rating_date', 'rating_state = ?'),
			[$start, $end, 'visible']
		);

		return [
			'resource' => $resources,
			'resource_update' => $resourceUpdates,
			'resource_reaction' => $resourceReactions,
			'resource_rating' => $resourceRatings
		];
	}
}