<?php

namespace XFRM\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int resource_id
 * @property int user_id
 *
 * RELATIONS
 * @property \XFRM\Entity\ResourceItem Resource
 * @property \XF\Entity\User User
 */
class ResourceTeamMember extends Entity
{
	protected function _postSave()
	{
		$this->rebuildResourceTeamMemberCache();
	}

	protected function _postDelete()
	{
		$this->rebuildResourceTeamMemberCache();

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsToUser(
			$this->user_id,
			'resource',
			$this->resource_id,
			'team_member_add'
		);
	}

	protected function rebuildResourceTeamMemberCache()
	{
		\XF::runOnce(
			'xfrmResourceTeamMemberCache' . $this->resource_id,
			function()
			{
				/** @var \XFRM\Repository\ResourceItem */
				$resourceRepo = $this->repository('XFRM:ResourceItem');
				$resourceRepo->rebuildResourceTeamMemberCache($this->Resource);
			}
		);
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_rm_resource_team_member';
		$structure->shortName = 'XFRM:ResourceTeamMember';
		$structure->primaryKey = ['resource_id', 'user_id'];
		$structure->columns = [
			'resource_id' => [
				'type' => self::UINT,
				'required' => true
			],
			'user_id' => [
				'type' => self::UINT,
				'required' => true
			]
		];
		$structure->relations = [
			'Resource' => [
				'entity' => 'XFRM:ResourceItem',
				'type' => self::TO_ONE,
				'conditions' => 'resource_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];

		return $structure;
	}
}
