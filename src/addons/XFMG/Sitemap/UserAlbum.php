<?php

namespace XFMG\Sitemap;

use XF\Sitemap\AbstractHandler;
use XF\Sitemap\Entry;

class UserAlbum extends AbstractHandler
{
	public function getRecords($start)
	{
		$ids = $this->getIds('xf_user', 'user_id', $start);

		$finder = $this->app->finder('XF:User');
		$users = $finder
			->where('user_id', $ids)
			->with(['Profile', 'Privacy'])
			->order('user_id')
			->fetch();

		return $users;
	}

	/**
	 * @param $record \XF\Entity\User
	 *
	 * @return Entry
	 */
	public function getEntry($record)
	{
		$router = $this->app->router('public');
		$url = $router->buildLink('canonical:media/albums/users', $record);

		$data = [
			'priority' => 0.3
		];
		if ($record->avatar_date || $record->gravatar)
		{
			$avatar = \XF::canonicalizeUrl($record->getAvatarUrl('o', null, true));
			$data['image'] = $avatar;
		}
		return Entry::create($url,$data);
	}

	public function isIncluded($record)
	{
		/** @var $record \XFMG\XF\Entity\User */
		return ($record->canViewMedia()
			&& $record->xfmg_album_count
			&& $record->canViewFullProfile()
		);
	}
}