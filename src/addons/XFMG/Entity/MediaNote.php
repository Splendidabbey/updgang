<?php

namespace XFMG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null note_id
 * @property string note_type
 * @property int media_id
 * @property array note_data
 * @property int note_date
 * @property string note_text
 * @property int user_id
 * @property string username
 * @property string tag_state
 * @property int tag_state_date
 * @property int tagged_user_id
 * @property string tagged_username
 *
 * RELATIONS
 * @property \XFMG\Entity\MediaItem MediaItem
 * @property \XF\Entity\User TaggedUser
 * @property \XF\Entity\User User
 */
class MediaNote extends Entity
{
	public function canView(&$error = null)
	{
		$visitor = \XF::visitor();
		$mediaItem = $this->MediaItem;

		if ($mediaItem->media_type != 'image')
		{
			return false;
		}

		if ($this->tag_state === 'approved')
		{
			if ($visitor->user_id && $this->tagged_user_id && $visitor->user_id == $this->tagged_user_id)
			{
				// A tagged user should always be able to see a tag if they are tagged.
				return true;
			}

			return ($mediaItem->canView($error) && $mediaItem->hasPermission('viewNote'));
		}
		else if ($this->tag_state === 'pending')
		{
			// Only view pending tags with explicit permission, if you're the note owner, or if you're the tagged user
			return ($mediaItem->hasPermission('viewPendingNote')
				|| ($visitor->user_id == $this->user_id)
				|| ($visitor->user_id == $this->tagged_user_id)
			);
		}
		else
		{
			// rejected tag, will be cleaned up automatically
			return false;
		}
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		$mediaItem = $this->MediaItem;

		if (!$visitor->user_id || $mediaItem->media_type != 'image')
		{
			return false;
		}

		if ($mediaItem->hasPermission('editNoteAny'))
		{
			return true;
		}

		if ($visitor->user_id == $mediaItem->user_id && $mediaItem->hasPermission('addNoteOwn'))
		{
			return true;
		}

		if ($this->note_type == 'user_tag')
		{
			if ($this->tagged_user_id
				&& $visitor->user_id == $this->tagged_user_id
				&& $mediaItem->hasPermission('tagSelf')
			)
			{
				return 'self';
			}
		}

		return false;
	}

	public function canDelete(&$error = null)
	{
		$visitor = \XF::visitor();
		$mediaItem = $this->MediaItem;

		if (!$visitor->user_id || $mediaItem->media_type != 'image')
		{
			return false;
		}

		if ($mediaItem->hasPermission('deleteAnyNote'))
		{
			return true;
		}

		if ($visitor->user_id == $mediaItem->user_id && $mediaItem->hasPermission('deleteOwnNote'))
		{
			return true;
		}

		if ($this->note_type == 'user_tag')
		{
			if ($this->tagged_user_id
				&& $visitor->user_id == $this->tagged_user_id
				&& $mediaItem->hasPermission('deleteTagSelf')
			)
			{
				return true;
			}
		}

		return false;
	}

	public function canApproveReject()
	{
		if ($this->tag_state !== 'pending')
		{
			return false;
		}

		$visitor = \XF::visitor();
		return ($this->note_type == 'user_tag'
			&& $visitor->user_id
			&& $this->tagged_user_id
			&& $visitor->user_id == $this->tagged_user_id
		);
	}

	public function getNoteInsertState()
	{
		if ($this->note_type == 'note')
		{
			return 'approved';
		}
		else
		{
			$visitor = \XF::visitor();
			if ($this->MediaItem->hasPermission('tagWithoutApproval')
				|| ($visitor->user_id === $this->tagged_user_id)
			)
			{
				return 'approved';
			}
			else
			{
				return 'pending';
			}
		}
	}

	protected function _preSave()
	{
		$visitor = \XF::visitor();
		if ($this->note_type == 'user_tag')
		{
			$selfOnly = ($this->canEdit() === 'self');
			if ($selfOnly && $this->tagged_user_id !== $visitor->user_id)
			{
				$this->error(\XF::phrase('xfmg_you_only_permitted_to_tag_yourself_in_this_media_item'));
			}
		}
	}

	protected function _postSave()
	{
		if ($this->note_type == 'user_tag')
		{
			$alertRepo = $this->repository('XF:UserAlert');

			if ($this->isChanged('tag_state'))
			{
				// delete existing tag approval alerts on state change to avoid
				// approval / rejection being re-attempted
				if ($this->tag_state == 'approved' || $this->tag_state == 'rejected')
				{
					$alertRepo->fastDeleteAlertsFromUser(
						$this->user_id,
						'xfmg_media_note',
						$this->note_id,
						'tag_approval'
					);
				}
			}
			if ($this->isChanged('tagged_user_id') && $this->getExistingValue('tagged_user_id'))
			{
				$alertRepo->fastDeleteAlertsToUser(
					$this->getExistingValue('tagged_user_id'),
					'xfmg_media_note',
					$this->note_id,
					'tag_approval'
				);
				$alertRepo->fastDeleteAlertsToUser(
					$this->getExistingValue('tagged_user_id'),
					'xfmg_media_note',
					$this->note_id,
					'tag_insert'
				);
			}
		}
	}

	protected function _postDelete()
	{
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent(
			'xfmg_media_note',
			$this->note_id
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mg_media_note';
		$structure->shortName = 'XFMG:MediaNote';
		$structure->primaryKey = 'note_id';
		$structure->columns = [
			'note_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'note_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['user_tag', 'note']
			],
			'media_id' => ['type' => self::UINT, 'required' => true],
			'note_data' => ['type' => self::JSON_ARRAY, 'required' => true],
			'note_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'note_text' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'tag_state' => ['type' => self::STR, 'default' => 'approved',
				'allowedValues' => ['approved', 'pending', 'rejected']
			],
			'tag_state_date' => ['type' => self::UINT, 'default' => 0],
			'tagged_user_id' => ['type' => self::UINT, 'default' => 0],
			'tagged_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
		];
		$structure->getters = [];
		$structure->relations = [
			'MediaItem' => [
				'entity' => 'XFMG:MediaItem',
				'type' => self::TO_ONE,
				'conditions' => 'media_id',
				'primary' => true
			],
			'TaggedUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$tagged_user_id']
				],
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		$structure->options = [];

		$structure->defaultWith = ['MediaItem', 'TaggedUser', 'User'];

		return $structure;
	}
}