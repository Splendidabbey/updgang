<?php

namespace AddonFlare\AwardSystem\Job;

class AutoFeatureRebuild extends \XF\Job\AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT DISTINCT(user_id)
				FROM xf_af_as_user_award
				WHERE user_id > ?
				ORDER BY user_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
        $user = $this->app->em()->find('XF:User', $id, ['Option']);
        if (
        	!$user ||
        	!($maxFeatured = $user->max_featured_awards) ||
        	!$user->Option ||
        	!$user->Option->af_as_auto_feature)
        {
            return;
        }

		$db = $this->app->db();

		$userAwardRepo = $this->app->repository('AddonFlare\AwardSystem:UserAward');

		$db->beginTransaction();

		$featureableUserAwards = $userAwardRepo->findFeatureableAwards($user->user_id)
			->fetch();

        $featuredUserAwards = $featureableUserAwards->filter(function($userAward)
        {
            return $userAward->is_featured;
        });

		if (!$featuredUserAwards->count())
		{
			$lastOrder = $featuredCount = 0;
			foreach ($featureableUserAwards as $featureableUserAward)
			{
				$lastOrder += 100;

				$featureableUserAward->fastUpdate([
					'is_featured' => 1,
					'display_order' => $lastOrder,
				]);

				$featuredCount++;

				if ($featuredCount >= $maxFeatured)
				{
					break;
				}
			}

			// \XF::dolog('featuring for USERID: ' . $user->user_id . " ({$user->username}) featured count: {$featuredCount}");
		}

		$db->commit();
	}

	protected function getStatusType()
	{
		return \XF::phrase('af_as_auto_award_features');
	}
}