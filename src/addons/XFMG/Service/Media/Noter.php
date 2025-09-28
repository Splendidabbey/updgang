<?php

namespace XFMG\Service\Media;

use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Noter extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var \XFMG\Entity\MediaNote
	 */
	protected $note;

	protected $newNote = false;

	/**
	 * @var \XF\Entity\User
	 */
	protected $user;

	/**
	 * @var \XF\Entity\User
	 */
	protected $taggedUser;

	public function __construct(\XF\App $app, \XFMG\Entity\MediaNote $note)
	{
		parent::__construct($app);

		$this->note = $note;
		$this->newNote = $note->isInsert();
		if ($this->newNote)
		{
			$this->setUser(\XF::visitor());
		}
		else
		{
			$this->setUser($note->User);
		}
	}

	protected function setUser(\XF\Entity\User $user)
	{
		$this->user = $user;
	}

	public function setNoteData(array $noteData)
	{
		$this->note->note_data = $noteData;
	}

	public function setUserTag($username)
	{
		$note = $this->note;
		$note->note_type = 'user_tag';

		$taggedUser = $this->em()->findOne('XF:User', ['username' => $username]);
		if ($taggedUser)
		{
			$this->taggedUser = $taggedUser;
		}
	}

	public function setNoteText($noteText)
	{
		$this->note->note_type = 'note';
		$this->note->note_text = $noteText;
	}

	public function checkForSpam()
	{
		$note = $this->note;
		if ($note->note_type == 'note' && $note->isChanged('note_text') && $this->user->isSpamCheckRequired())
		{
			$checker = $this->app->spam()->contentChecker();
			$checker->check($this->user, $note->note_text, [
				'content_type' => 'xfmg_note'
			]);

			$decision = $checker->getFinalDecision();
			switch ($decision)
			{
				case 'moderated':
				case 'denied':
					$checker->logSpamTrigger('xfmg_note', null);
					$note->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}
	}

	protected function finalSetup()
	{
		$note = $this->note;
		$note->user_id = $this->user->user_id;
		$note->username = $this->user->username;

		$this->checkForSpam();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$note = $this->note;

		$errors = [];
		if ($note->note_type == 'user_tag')
		{
			if ($this->taggedUser)
			{
				$note->tagged_user_id = $this->taggedUser->user_id;
				$note->tagged_username = $this->taggedUser->username;
			}
			else
			{
				$errors[] = \XF::phrase('requested_user_not_found');
			}
		}

		// Remove some legacy stuff we don't use from the note data
		$noteData = $note->note_data;
		unset($noteData['tag_x2'], $noteData['tag_y2'], $noteData['tag_multiplier']);
		$note->note_data = $noteData;

		return $errors;
	}

	protected function _save()
	{
		$note = $this->note;

		if ($this->newNote)
		{
			$note->note_date = time();
			$note->tag_state = $note->getNoteInsertState();
			$note->tag_state_date = time();
		}
		else
		{
			if ($note->isChanged('tagged_user_id'))
			{
				$note->tag_state = $note->getNoteInsertState();
				$note->tag_state_date = time();
			}
		}

		$note->save();

		$this->sendNotifications();

		return $note;
	}

	protected function sendNotifications()
	{
		$note = $this->note;

		if ($note->note_type == 'note')
		{
			return;
		}

		$sendAlert = false;
		if ($this->newNote)
		{
			$sendAlert = true;
		}
		else
		{
			if ($note->isChanged('tagged_user_id'))
			{
				$sendAlert = true;
			}
		}

		if ($sendAlert)
		{
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');

			if ($note->tag_state === 'pending')
			{
				$alertRepo->alert(
					$this->taggedUser,
					$note->user_id,
					$note->username,
					'xfmg_media_note',
					$note->note_id,
					'tag_approval',
					[],
					['autoRead' => false]
				);
			}
			else
			{
				$alertRepo->alert(
					$this->taggedUser,
					$note->user_id,
					$note->username,
					'xfmg_media_note',
					$note->note_id,
					'tag_insert',
					[],
					['autoRead' => false]
				);
			}
		}
	}
}