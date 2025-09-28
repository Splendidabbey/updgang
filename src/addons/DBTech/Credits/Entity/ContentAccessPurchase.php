<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property int $user_id
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class ContentAccessPurchase extends Entity
{
	/**
	 * @return \DBTech\Credits\EventTrigger\AbstractHandler|null
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function getHandler(): ?\DBTech\Credits\EventTrigger\AbstractHandler
	{
		return Helper::repository(\DBTech\Credits\Repository\EventTrigger::class)
			->getHandler('content_access')
		;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_content_access_purchase';
		$structure->shortName = 'DBTech\Credits:ContentAccessPurchase';
		$structure->primaryKey = ['content_type', 'content_id', 'user_id'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
		];
		$structure->getters = [];
		$structure->relations = [
			'User'   => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true
			],
		];
		return $structure;
	}
}