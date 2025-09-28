<?php

namespace AddonFlare\AwardSystem\Cron;

class Award
{
	public static function runAwardCheck()
	{
		$awardRepo = \XF::repository('AddonFlare\AwardSystem:Award');
		$userAwardRepo = \XF::repository('AddonFlare\AwardSystem:UserAward');

		$awards = $awardRepo->findAwardsForList()->fetch();

		if (!$awards->count())
		{
			return;
		}

		$userFinder = \XF::finder('XF:User');

		$users = $userFinder
			->where('last_activity', '>=',time() - 2 * 3600)
			->isValidUser(false)
			->fetch();

		$userAwards = $userAwardRepo->findUserAwardsForList($users->keys(), 'approved')
			->with('Award', true)
			->with('Award.Category')
			->fetch()->groupBy('user_id');

		foreach ($users as $user)
		{
			$userAwardRepo->updateAwardsForUser($user, $userAwards[$user->user_id] ?? [], $awards);
		}
	}
}