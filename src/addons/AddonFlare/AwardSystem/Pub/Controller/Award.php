<?php

namespace AddonFlare\AwardSystem\Pub\Controller;

use XF\Mvc\ParameterBag;

class Award extends \XF\Pub\Controller\AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		return $this->rerouteController(__CLASS__, 'list');
	}

	public function actionList(ParameterBag $params)
	{
		$selfRoute = 'award-system/list';
		$this->assertCanonicalUrl($this->buildLink($selfRoute));

		$categoryId = $this->filter('category', 'int', -1);
		if ($categoryId > 0)
		{
    		$this->assertAwardCategoryExists($categoryId);
		}

        // TODO: further optimize
		$awardData = $this->getAwardRepo()->getAwardListData();

        if (empty($awardData['totalAwards']))
        {
            return $this->message(\XF::phrase('af_as_no_awards_added_yet'));
        }

		$viewParams = [
			'awardData' 	=> $awardData,
			'selfRoute' 	=> $selfRoute,
			'categoryId'	=> $categoryId,
		];
		return $this->view('AddonFlare\AwardSystem:Award\Listing', 'af_as_pub_award_list', $viewParams);
	}

    public function actionPending(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canManageAwards())
        {
            return $this->noPermission();
        }

        $status = 'pending';

        $page = $this->filterPage();
        $perPage = 25;

        $userAwardRepo = $this->getUserAwardRepo();

        $userAwardFinder = $userAwardRepo->findUserAwardsForList(0, $status)
            ->with('Award', true)
            ->with('User', true)
            ->limitByPage($page, $perPage)
            ->order('date_requested','DESC');

        $userAwards = $userAwardFinder->fetch();

        $actions = $userAwardRepo->getStatusActions($status);

        $viewParams = [
            'status'        => $status,
            'statusPhrase'  => \XF::phrase('af_as_user_award_status.' . $status),
            'actions'       => $actions,
            'userAwards'    => $userAwards,
            'page'          => $page,
            'perPage'       => $perPage,
            'total'         => $userAwardFinder->total(),
        ];

        return $this->view('', 'af_as_pending_user_awards_list', $viewParams);
    }

    public function actionStatusUpdate(ParameterBag $params)
    {
        $userAwardRepo = $this->getUserAwardRepo();

        $visitor = \XF::visitor();

        $status = $this->filter('status', 'str');
        $actions = $userAwardRepo->getStatusActions('pending');

        if (!$visitor->canManageAwards() || !in_array($status, $actions))
        {
            return $this->noPermission();
        }

        $userAwardIds = $this->filter('user_award_ids', 'array-uint');
        $userAwards = $this->finder('AddonFlare\AwardSystem:UserAward')
            ->with('Award', true)
            ->with('User', true)
            ->where('user_award_id', $userAwardIds)
            ->where('status', 'pending')
            ->fetch();

        if ($status == 'delete')
        {
            foreach ($userAwards as $userAward)
            {
                $userAward->delete();
            }
        }
        else
        {
            $approved = [];

            $db = $this->app()->db();
            $db->beginTransaction();

            foreach ($userAwards as $userAward)
            {
                if ($status == 'approved')
                {
                    $validRecipient = $userAwardRepo->getValidatedRecipients($userAward->Award, $userAward->User, $error);

                    if ($error)
                    {
                        throw $this->errorException($error);
                    }

                    $approved[] = $userAward;
                }

                $userAward->status = $status;
                $userAward->given_by_user_id = \XF::visitor()->user_id;
                $userAward->save(true, false);
            }

            $db->commit();

            if ($approved)
            {
                $alertRepo = $this->repository('XF:UserAlert');
                foreach ($approved as $userAward)
                {
                    $userAward->fastUpdate('date_received', \XF::$time);
                    $alertRepo->alertFromUser($userAward->User, $userAward->User, 'af_as_award', $userAward->Award->award_id, 'award');
                }
            }
        }
        return $this->redirect($this->buildLink('award-system/pending'));
    }

	public function actionRecommend(ParameterBag $params)
	{
		$requestType = \XF::phrase('af_as_recommend');
		return $this->actionCreateRequest($params, $requestType);
	}

	public function actionRequest(ParameterBag $params)
	{
		$requestType = \XF::phrase('af_as_request');
		$user = \XF::visitor();

		return $this->actionCreateRequest($params, $requestType, $user);
	}

	public function actionCreateRequest(ParameterBag $params, $requestType = null, $user = null)
	{
        $this->assertRegistrationRequired();

        $award = $this->assertAwardExists($params['award_id']);

		if ($this->isPost())
		{
			$inputFilter = $this->filter([
				'username'		=> 'str',
				'award_reason'	=> 'str'
			]);

			$user = $this->finder('XF:User')->where('username', $inputFilter['username'])->fetchOne();
	        if (!$user)
	        {
	            return $this->error(\XF::phrase('requested_user_not_found'));
	        }

	        if (!$this->validateRequest($user, $award, $message))
	        {
	            return $this->error(\XF::phrase($message, ['username' => $user->username]));
	        }

			$userAward = $this->em()->create('AddonFlare\AwardSystem:UserAward');
			$userAward->bulkset([
		        'user_id'  				=> $user->user_id,
		        'award_id' 				=> $award->award_id,
		        'recommended_user_id' 	=> \XF::visitor()->user_id,
		        'award_reason' 			=> $inputFilter['award_reason'],
		        'date_received' 		=> null,
				'date_requested' 		=> \XF::$time,
				'status' 				=> 'pending',
			]);
			$userAward->save();

            $phrase = ($user == \XF::visitor()) ? 'af_as_request_submitted' : 'af_as_recommendation_submitted';

			return $this->redirect($this->buildLink('award-system'), \XF::phrase($phrase));
		}
		else
		{
			$viewParams = [
				'request_type' 	=> $requestType,
				'award'			=> $award,
				'user'			=> $user
			];

			return $this->view('AddonFlare\AwardSystem:Award\Request', 'af_as_pub_award_request', $viewParams);
		}
	}

	protected function validateRequest($user, \AddonFlare\AwardSystem\Entity\Award $award, &$message)
	{
        $visitor = \XF::visitor();

        $isRequest = ($visitor->user_id == $user->user_id);
        $isRecommendation = !$isRequest;

        $options = $this->options();

        if ($isRequest && (!$options->af_as_award_can_request || !$award->can_request))
        {
            $message = 'af_as_award_request_not_allowed';
            return false;
        }
        if ($isRecommendation && (!$options->af_as_award_can_recommend || !$award->can_recommend))
        {
            $message = 'af_as_award_recommend_not_allowed';
            return false;
        }

		$userAwards = $this->getUserAwardRepo()->findUserAwardsForList($user->user_id)
			->where('award_id', $award->award_id)
			->fetch()
			->groupBy('status');

        if (!$award->allow_multiple)
        {
            if (array_key_exists('pending', $userAwards))
            {
                $message = 'af_as_x_user_already_has_pending_request';
                return false;
            }

            if (array_key_exists('approved', $userAwards))
            {
                $message = 'af_as_x_user_already_has_award';
                return false;
            }
        }

		if (array_key_exists('rejected', $userAwards))
		{
			$reject_num = $options->af_as_auto_reject_request;
			if (count($userAwards['rejected']) >= $reject_num)
			{
				$message = 'af_as_x_user_already_rejected_x_times';
				return false;
			}
		}

		return true;
	}

    public function actionUser(ParameterBag $params, $max = null)
    {
    	$userId = $this->filter('userId', 'uint') ?: \XF::visitor()->user_id;

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        if (!$user->canViewAwardsUserProfile())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 25;

        $userAwardsFinder = $this->getUserAwardRepo()->findUserAwardsForList($userId, 'approved')
            ->order('display_order', 'ASC')
            ->order('date_received', 'DESC');

        if ($max)
        {
            $userAwardsFinder->limit($max);
        }
        else
        {
            $userAwardsFinder->limitByPage($page, $perPage);
        }

        $userAwards = $userAwardsFinder->fetch();

        $viewParams = [
            'userAwards' 	=> $userAwards,
            'user'			=> $user,
            'max'			=> $max,
            'page'          => $page,
            'perPage'       => $perPage,
            'total'			=> $userAwardsFinder->total(),
        ];

        return $this->view('AddonFlare\AwardSystem:Award\ProfileAwards', 'af_as_pub_award_profile', $viewParams);
    }

    public function actionUserAwards()
    {
        $userId = $this->filter('user', 'uint') ?: \XF::visitor()->user_id;

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        if (!$user->canViewAwardsUserProfile())
        {
            return $this->noPermission();
        }

        // $db = $this->app->db();

        // $latestUserAwardIdsPerCategory = $db->fetchAllColumn("
        //     SELECT user_award_id
        //     FROM (
        //         SELECT award.award_category_id, useraward.user_award_id, COALESCE(cat.display_order, 0) AS display_order, useraward.date_received
        //         FROM xf_af_as_user_award useraward
        //         INNER JOIN xf_af_as_award award ON (award.award_id = useraward.award_id)
        //         LEFT JOIN xf_af_as_award_category cat ON (cat.award_category_id = award.award_category_id)
        //         WHERE
        //             useraward.user_id = ? AND useraward.status = ?
        //         ORDER BY useraward.date_received DESC, useraward.user_award_id DESC
        //     ) as x
        //     GROUP BY award_category_id
        //     ORDER BY display_order
        // ", [$userId, 'approved']);

        // $userAwards = $this->finder('AddonFlare\AwardSystem:UserAward')
        //     ->with('User', true)
        //     ->with('Award', true)
        //     ->with('Award.Category')
        //     ->where('user_award_id', $latestUserAwardIdsPerCategory)
        //     ->fetch()
        //     ->sortByList($latestUserAwardIdsPerCategory); // sorts by category display order

        // $viewParams = [
        //     'userAwards' => $userAwards,
        //     'user'       => $user,
        //     'levelData' => $user->award_level_data,
        // ];
        // return $this->view('', 'af_as_user_award_list', $viewParams);


        $category = null;

        // allow award_id to be passed and find correct category, not used for now but leave it incase we need it later
        if ($awardId = $this->filter('award', 'uint'))
        {
            $award = $this->em()->find('AddonFlare\AwardSystem:Award', $awardId);
            if ($award)
            {
                $categoryId = $award->award_category_id;
            }
        }

        $categoryId = isset($categoryId) ? $categoryId : $this->filter('category', 'int', -1);

        $categories = $this->finder('AddonFlare\AwardSystem:AwardCategory')
            ->order('display_order', 'ASC')
            ->fetch();

        $awardFinder = $this->finder('AddonFlare\AwardSystem:Award')
            ->with('Category')
            ->order('display_order', 'ASC');

        $userAwardFinder = $this->finder('AddonFlare\AwardSystem:UserAward')
            ->with('User', true)
            ->with('Award', true)
            ->with('Award.Category')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->order([['date_received', 'DESC'], ['user_award_id', 'DESC']]);

        if ($categoryId > 0)
        {
            $category = $this->assertAwardCategoryExists($categoryId);

            $awardFinder->where('award_category_id', $categoryId);
            $userAwardFinder->where('Award.award_category_id', $categoryId);
        }

        $awards = $awardFinder->fetch();
        $awardsGrouped = $awards->groupBy('award_category_id');

        $userAwards = $userAwardFinder->fetch();
        $userAwardsGrouped = $userAwards->groupBy('award_id');


        $grouped = [];

        // add uncategorized category to be first (incase we have awards for it)
        if ($categoryId == -1 || 0 == $category->award_category_id)
        {
            $grouped[0] = [];
        }

        // create the initial arrays so it respects the category & award orders
        foreach ($categories as $category)
        {
            if ($categoryId == -1 || $categoryId == $category->award_category_id)
            {
                $grouped[$category->award_category_id] = [];
            }
        }

        foreach ($awards as $award)
        {
            if ($categoryId == -1 || $categoryId == $award->award_category_id)
            {
                $grouped[$award->award_category_id][$award->award_id] = [];
            }
        }

        $mostRecentUserAwards = [];

        foreach ($userAwards as $userAwardId => $userAward)
        {
            if (!isset($grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId]))
            {
                $grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId] = [];
            }

            $grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId] = $userAward;

            if (
                !isset($mostRecentUserAwards[$userAward->Award->award_category_id]) ||
                $userAward->date_received > $mostRecentUserAwards[$userAward->Award->award_category_id]->date_received
            )
            {
                $mostRecentUserAwards[$userAward->Award->award_category_id] = $userAward;
            }
        }

        $lastAwardIdsPerCategory = [];

        foreach ($grouped as $groupedCatId => $group)
        {
            foreach ($group as $groupAwardId => $groupUserAwards)
            {
                if (!empty($groupUserAwards) ||
                    $groupedCatId == 0 || // since the uncategorized mode is visible by default
                    (isset($categories[$groupedCatId]) && $categories[$groupedCatId]->display_mode == 'visible')
                )
                {
                    $lastAwardIdsPerCategory[$groupedCatId] = $groupAwardId;
                }
            }
        }

        $nextAwards = [];

        foreach ($awardsGrouped as $_categoryId => $_awards)
        {
            $category = $categories[$_categoryId] ?? null;
            if ($category && $category->isModeStep())
            {
                foreach ($_awards as $awardId => $award)
                {
                    if (!isset($userAwardsGrouped[$awardId]))
                    {
                        $nextAwards[$_categoryId] = $award;
                    }
                }
            }
        }

        $viewParams = [
            'categoryId' => $categoryId,
            'categories' => $categories,
            'awards' => $awards,
            'userAwards' => $userAwards,
            'userAwardsGrouped' => $userAwardsGrouped,
            'user' => $user,
            'levelData' => $user->award_level_data,
            'nextAwards' => $nextAwards,

            'grouped' => $grouped,
            'mostRecentUserAwards' => $mostRecentUserAwards,
            'lastAwardIdsPerCategory' => $lastAwardIdsPerCategory,
        ];

        // \XF::dump($viewParams);

        return $this->view('', 'af_as_user_award_category_list', $viewParams);
    }

    public function actionUserAwardsCategory()
    {
        $userId = $this->filter('user', 'uint') ?: \XF::visitor()->user_id;

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        if (!$user->canViewAwardsUserProfile())
        {
            return $this->noPermission();
        }

        $category = null;

        // allow award_id to be passed and find correct category, not used for now but leave it incase we need it later
        if ($awardId = $this->filter('award', 'uint'))
        {
            $award = $this->em()->find('AddonFlare\AwardSystem:Award', $awardId);
            if ($award)
            {
                $categoryId = $award->award_category_id;
            }
        }

        $categoryId = isset($categoryId) ? $categoryId : $this->filter('category', 'int', -1);

        $categories = $this->finder('AddonFlare\AwardSystem:AwardCategory')
            ->order('display_order', 'ASC')
            ->fetch();

        $awardFinder = $this->finder('AddonFlare\AwardSystem:Award')
            ->with('Category')
            ->order('display_order', 'ASC');

        $userAwardFinder = $this->finder('AddonFlare\AwardSystem:UserAward')
            ->with('User', true)
            ->with('Award', true)
            ->with('Award.Category')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->order([['date_received', 'DESC'], ['user_award_id', 'DESC']]);

        if ($categoryId > 0)
        {
            $category = $this->assertAwardCategoryExists($categoryId);

            $awardFinder->where('award_category_id', $categoryId);
            $userAwardFinder->where('Award.award_category_id', $categoryId);
        }

        $awards = $awardFinder->fetch();
        $awardsGrouped = $awards->groupBy('award_category_id');

        $userAwards = $userAwardFinder->fetch();
        $userAwardsGrouped = $userAwards->groupBy('award_id');


        $grouped = [];

        // add uncategorized category to be first (incase we have awards for it)
        if ($categoryId == -1 || 0 == $category->award_category_id)
        {
            $grouped[0] = [];
        }

        // create the initial arrays so it respects the category & award orders
        foreach ($categories as $category)
        {
            if ($categoryId == -1 || $categoryId == $category->award_category_id)
            {
                $grouped[$category->award_category_id] = [];
            }
        }

        foreach ($awards as $award)
        {
            if ($categoryId == -1 || $categoryId == $award->award_category_id)
            {
                $grouped[$award->award_category_id][$award->award_id] = [];
            }
        }

        $mostRecentUserAwards = [];

        foreach ($userAwards as $userAwardId => $userAward)
        {
            if (!isset($grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId]))
            {
                $grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId] = [];
            }

            $grouped[$userAward->Award->award_category_id][$userAward->award_id][$userAwardId] = $userAward;

            if (
                !isset($mostRecentUserAwards[$userAward->Award->award_category_id]) ||
                $userAward->date_received > $mostRecentUserAwards[$userAward->Award->award_category_id]->date_received
            )
            {
                $mostRecentUserAwards[$userAward->Award->award_category_id] = $userAward;
            }
        }

        $lastAwardIdsPerCategory = [];

        foreach ($grouped as $groupedCatId => $group)
        {
            foreach ($group as $groupAwardId => $groupUserAwards)
            {
                if (!empty($groupUserAwards) ||
                    $groupedCatId == 0 || // since the uncategorized mode is visible by default
                    (isset($categories[$groupedCatId]) && $categories[$groupedCatId]->display_mode == 'visible')
                )
                {
                    $lastAwardIdsPerCategory[$groupedCatId] = $groupAwardId;
                }
            }
        }

        $nextAwards = [];

        foreach ($awardsGrouped as $_categoryId => $_awards)
        {
            $category = $categories[$_categoryId] ?? null;
            if ($category && $category->isModeStep())
            {
                foreach ($_awards as $awardId => $award)
                {
                    if (!isset($userAwardsGrouped[$awardId]))
                    {
                        $nextAwards[$_categoryId] = $award;
                    }
                }
            }
        }

        $viewParams = [
            'categoryId' => $categoryId,
            'categories' => $categories,
            'awards' => $awards,
            'userAwards' => $userAwards,
            'userAwardsGrouped' => $userAwardsGrouped,
            'user' => $user,
            'nextAwards' => $nextAwards,

            'grouped' => $grouped,
            'mostRecentUserAwards' => $mostRecentUserAwards,
            'lastAwardIdsPerCategory' => $lastAwardIdsPerCategory,
        ];

        // \XF::dump($viewParams);

        return $this->view('', 'af_as_user_award_category_list', $viewParams);
    }

    // used to correctly redirect back to the awards tab if the user was sorting through there via the overlay
    protected function sortDynamicRedirect()
    {
        $visitor = \XF::visitor();

        $profileUrl = $this->buildLink('members', $visitor);
        $profileUrlAwardsTab = $profileUrl . '#profile-awards';

        $url = $this->getDynamicRedirectIfNot($profileUrl, $profileUrlAwardsTab);

        return $this->redirect($url);
    }

    public function actionUserSort()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();

        $userId = $this->filter('user', 'uint') ?: $visitor->user_id;

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        $visitorIsUser = ($visitor->user_id == $user->user_id);

        if ($visitorIsUser)
        {
            if (!$visitor->canManuallyFeatureAwards() && !$visitor->canManageAwards())
            {
                return $this->noPermission();
            }
        }
        else // visitor is not user
        {
            if (!$visitor->canManageAwards())
            {
                return $this->noPermission();
            }
        }

        $db = $this->app()->db();

        if ($this->filter('remove', 'bool'))
        {
            $db->update('xf_af_as_user_award', [
                'is_featured'   => 0,
                'display_order' => 0,
            ], 'user_id = ?', [$userId]);

            return $this->sortDynamicRedirect();
        }

        // $latestUserAwardIdsPerAward = $db->fetchPairs("
        //     SELECT award_id, user_award_id
        //     FROM (
        //         SELECT award.award_id, useraward.user_award_id, COALESCE(cat.display_order, 0) AS cat_display_order, award.display_order AS award_display_order, useraward.date_received
        //         FROM xf_af_as_user_award useraward
        //         INNER JOIN xf_af_as_award award ON (award.award_id = useraward.award_id)
        //         LEFT JOIN xf_af_as_award_category cat ON (cat.award_category_id = award.award_category_id)
        //         WHERE
        //             useraward.user_id = ? AND useraward.status = ? AND award.can_feature = ?
        //         ORDER BY useraward.date_received DESC, useraward.user_award_id DESC
        //     ) as x
        //     GROUP BY award_id
        //     ORDER BY NULL
        // ", [$userId, 'approved', 1]);

        // \XF::dump($latestUserAwardIdsPerAward);

        $userAwards = $this->getUserAwardRepo()
            ->findFeatureableAwards($userId)
            ->fetch();

        if (!$userAwards)
        {
            return $this->error(\XF::phrase('no_awards_added'));
        }

        $maxFeatured = $user->max_featured_awards;

        if ($this->isPost())
        {
            $sortData = $this->filter('awards-featured', 'json-array');

            $featuredUserAwardIds = array_column($sortData, 'id');
            // only allow the limit
            $featuredUserAwardIds = array_slice($featuredUserAwardIds, 0, $maxFeatured);

            $featuredUserAwards = [];
            foreach ($featuredUserAwardIds as $featuredUserAwardId)
            {
                if (isset($userAwards[$featuredUserAwardId]))
                {
                    $featuredUserAwards[$featuredUserAwardId] = $userAwards[$featuredUserAwardId];
                }
            }

            if ($featuredUserAwards)
            {
                // this sets the display orders
                $sorter = $this->plugin('XF:Sort');
                $sorter->sortFlat($sortData, $featuredUserAwards, ['preSaveCallback' => function($entry)
                {
                    $entry->is_featured = 1;
                }]);

                // set the rest as featured
                $quoted = $db->quote(array_merge([0], array_keys($featuredUserAwards)));
                $db->update('xf_af_as_user_award', [
                    'is_featured'   => 0,
                    'display_order' => 0,
                ], "user_id = ? AND user_award_id NOT IN ($quoted)",
                [$userId]);
            }

            return $this->sortDynamicRedirect();
        }
        else
        {
            $featuredUserAwardsByAward = $db->fetchPairs("
                SELECT useraward.user_award_id, useraward.user_award_id
                FROM xf_af_as_user_award useraward
                INNER JOIN xf_af_as_award award ON (award.award_id = useraward.award_id)
                WHERE
                    useraward.user_id = ? AND useraward.status = ? AND useraward.is_featured = ?
            ", [$userId, 'approved', 1]);

            $featuredUserAwards = $userAwards->filter(function($userAward)
            {
                return $userAward->is_featured;
            });

            $availableUserAwards = $userAwards->filter(function($userAward)
            {
                return !$userAward->is_featured;
            });

            $featuredUserAwards = \XF\Util\Arr::columnSort($featuredUserAwards->toArray(), 'display_order');
            $featuredUserAwards = $this->em()->getBasicCollection($featuredUserAwards);

            $featuredUserAwardsCount = count($featuredUserAwards);
            $featuredUserAwardsRemaining = max(0, $maxFeatured - $featuredUserAwardsCount);

            $viewParams = [
                'user' => $user,
                'availableUserAwards' => $availableUserAwards,
                'featuredUserAwards'  => $featuredUserAwards,
                'featuredUserAwardsCount' => $featuredUserAwardsCount,
                'featuredUserAwardsRemaining' => $featuredUserAwardsRemaining,
            ];

            return $this->view('', 'af_as_user_award_sort', $viewParams);
        }
    }

    public function actionProfile(ParameterBag $params)
    {
    	$maxProfileAwards = $this->options()->af_as_max_profile_awards;

    	return $this->actionUser($params, $maxProfileAwards);
    }

    public function actionUserOptions()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();

        $userId = $this->filter('user', 'uint') ?: $visitor->user_id;

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        $visitorIsUser = ($visitor->user_id == $user->user_id);

        if ($visitorIsUser)
        {
            if (!$visitor->canManuallyFeatureAwards() && !$visitor->canManageAwards())
            {
                return $this->noPermission();
            }
        }
        else // visitor is not user
        {
            if (!$visitor->canManageAwards())
            {
                return $this->noPermission();
            }
        }

        $form = $this->formAction();

        $input = $this->filter([
            'af_as_auto_feature' => 'bool',
        ]);

        $userOptions = $user->getRelationOrDefault('Option', false);

        $form->basicEntitySave($userOptions, $input);

        $form->run();

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionUserRecent()
    {
        $userId = $this->filter('user', 'uint') ?: \XF::visitor()->user_id;
        $awardId = $this->filter('award', 'uint');
        $award = $this->assertAwardExists($awardId);

        if (!$user = $this->em()->find('XF:User', $userId))
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        if (!$user->canViewAwardsUserProfile())
        {
            return $this->noPermission();
        }

        $userAwardsFinder = $this->getUserAwardRepo()->findUserAwardsForList($userId, 'approved')
            ->where('award_id', $award->award_id)
            ->order('date_received', 'DESC');

        $userAwards = $userAwardsFinder->fetch();

        $pagetitle = "{$user->username} | {$award->title}";

        $viewParams = [
            'userAwards'    => $userAwards,
            'award'         => $award,
            'total'         => $userAwardsFinder->total(),
            'pagetitle'     => $pagetitle,
        ];
        return $this->view('', 'af_as_pub_awarded_users', $viewParams);
    }

    public function actionRecent(ParameterBag $params)
    {
        // $this->assertRegistrationRequired(); // TODO: make UG permission

    	$options = $this->options()->af_as_most_recent_awards;
    	if (!$options['enabled'])
    	{
			return $this->notFound();
    	}

    	$award = $this->assertAwardExists($params['award_id']);

        $page = $this->filterPage();
        $perPage = 25;

    	$userAwardsFinder= $this->getUserAwardRepo()->findUserAwardsForList()
    		->where('award_id', $award->award_id)
    		->where('status', 'approved')
            ->order('display_order', 'ASC')
    		->order('date_received', 'DESC');

        $max = 0;

        if ($this->request()->isXhr())
        {
            $max = max($options['entries'], 1);
            $userAwardsFinder->limit($max);
        }
        else
        {
            $userAwardsFinder->limitByPage($page, $perPage);
        }

    	$userAwards = $userAwardsFinder->fetch()->filterViewable();

    	$viewParams = [
    		'userAwards'	=> $userAwards,
    		'award'			=> $award,
            'max'           => $max,
            'page'          => $page,
            'perPage'       => $perPage,
            'total'         => $userAwardsFinder->total(),
    	];
        return $this->view('AddonFlare\AwardSystem:Award\ProfileAward', 'af_as_pub_awarded_users', $viewParams);
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('af_as_viewing_awards_list');
    }

	protected function assertAwardExists($id, $with = null, $phraseKey = 'af_as_invalid_award_specified')
	{
		return $this->assertRecordExists('AddonFlare\AwardSystem:Award', $id, $with, $phraseKey);
	}

	protected function assertAwardCategoryExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('AddonFlare\AwardSystem:AwardCategory', $id, $with, $phraseKey);
	}

	protected function getAwardRepo()
	{
		return $this->repository('AddonFlare\AwardSystem:Award');
	}

    protected function getUserAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:UserAward');
    }
}