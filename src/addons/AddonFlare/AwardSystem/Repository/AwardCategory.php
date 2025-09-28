<?php

namespace AddonFlare\AwardSystem\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class AwardCategory extends Repository
{
	public function getDefaultCategory()
	{
		$awardCategory = $this->em->create('AddonFlare\AwardSystem:AwardCategory');
		$awardCategory->setTrusted('award_category_id', 0);
		$awardCategory->setTrusted('display_order', 0);
		$awardCategory->setReadOnly(true);

		return $awardCategory;
	}

	public function findAwardCategoriesForList($getDefault = false, $categoryIds = null)
	{
		$categoriesFinder = $this->finder('AddonFlare\AwardSystem:AwardCategory')
			->order(['display_order']);

		if (isset($categoryIds))
		{
			$categoriesFinder->where('award_category_id', $categoryIds);
		}

		$categories = $categoriesFinder->fetch();

		if ($getDefault)
		{
			$defaultCategory = $this->getDefaultCategory();
			$awardCategories = $categories->toArray();
			$awardCategories = [$defaultCategory] + $awardCategories;
			$categories = $this->em->getBasicCollection($awardCategories);
		}

		return $categories;
	}

	public function getAwardCategoryTitlePairs()
	{
		$awardCategories = $this->finder('AddonFlare\AwardSystem:AwardCategory')
			->order('display_order');

		return $awardCategories->fetch()->pluckNamed('title', 'award_category_id');
	}
}