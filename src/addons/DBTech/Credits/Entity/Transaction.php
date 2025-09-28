<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\ArrayCollection;

/**
 * COLUMNS
 * @property int|null $transaction_id
 * @property int $event_id
 * @property string $event_trigger_id
 * @property int $user_id
 * @property int $dateline
 * @property int $source_user_id
 * @property string $transaction_state
 * @property float $amount
 * @property string $reference_id
 * @property string $content_type
 * @property int $content_id
 * @property int $node_id
 * @property int $owner_id
 * @property int $multiplier
 * @property int $currency_id
 * @property bool $negate
 * @property string $message
 * @property bool $is_disputed
 * @property int $expiry_date
 * @property float $balance
 *
 * GETTERS
 * @property null|ArrayCollection|\XF\Mvc\Entity\Entity $Content
 *
 * RELATIONS
 * @property \DBTech\Credits\Entity\Event $Event
 * @property \DBTech\Credits\Entity\Currency $Currency
 * @property \XF\Entity\User $TargetUser
 * @property \XF\Entity\User $SourceUser
 * @property \XF\Entity\User $Owner
 * @property \XF\Entity\Forum $Forum
 * @property \XF\Entity\ApprovalQueue $ApprovalQueue
 */
class Transaction extends Entity
{
	/**
	 * @return bool
	 */
	public function canView(): bool
	{
		/** @var \DBTech\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (
			$this->user_id != $visitor->user_id
			&& $this->source_user_id != $visitor->user_id
			&& !$visitor->canViewAnyDbtechCreditsTransaction()
		) {
			return false;
		}

		if (!$this->Event || !$this->Event->canView())
		{
			return false;
		}

		if (!$this->Currency || !$this->Currency->canView($this->TargetUser))
		{
			return false;
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canApproveUnapprove(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return (
			$visitor->user_id
			&& $visitor->hasPermission('dbtechCredits', 'approveUnapprove')
		);
	}

	/**
	 * @return bool
	 */
	public function canSendModeratorActionAlert(): bool
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $this->user_id
			&& $visitor->user_id != $this->user_id
		);
	}

	/**
	 * @return bool
	 */
	public function isIgnored(): bool
	{
		return (\XF::visitor()->isIgnoring($this->user_id)
			|| \XF::visitor()->isIgnoring($this->source_user_id)
		);
	}

	/**
	 * @return null|ArrayCollection|\XF\Mvc\Entity\Entity
	 */
	public function getContent()
	{
		return \XF::app()->findByContentType($this->content_type, $this->content_id);
	}

	/**
	 * @param Entity|null $content
	 */
	public function setContent(?Entity $content = null)
	{
		$this->_getterCache['Content'] = $content;
	}

	/**
	 * @return string
	 */
	public function getContentLink(): string
	{
		if (empty($this->content_type) || empty($this->content_id))
		{
			return '';
		}

		if (isset($this->_getterCache['Content'])
			&& $this->Content instanceof \XF\Entity\LinkableInterface
		) {
			return '<a href="' . $this->Content->getContentUrl() . '">' .
				$this->Content->getContentTitle() .
				'</a>';
		}

		$router = $this->app()->router('public');

		switch ($this->content_type)
		{
			case 'dbtech_ecommerce_order':
				return \XF::phrase('dbtech_credits_ecommerce_order_x', ['position' => $this->content_id]);

			case 'dbtech_shop_trade_post':
				return \XF::phrase('dbtech_credits_shop_trade_post_x', ['position' => $this->content_id]);

			default:
				return '';
		}
	}

	/**
	 * @throws \XF\Db\Exception
	 * @throws \XF\PrintableException
	 */
	protected function _postSave()
	{
		$approvalChange = $this->isStateChanged('transaction_state', 'moderated');

		if ($this->isUpdate())
		{
			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}

		if ($approvalChange == 'enter')
		{
			/** @var \XF\Entity\ApprovalQueue $approvalQueue */
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = \XF::$time;
			$approvalQueue->save();
		}

		if ($this->transaction_state == 'visible')
		{
			// This is split into two queries for readability
			if (!$this->Currency->negative)
			{
				// Update the currency table, but ensure it resets to 0 if needed
				$this->db()->query('
					UPDATE xf_user
					SET ' . $this->Currency->column . ' = GREATEST(0, ' . $this->Currency->column . ' + ?)
					WHERE user_id = ?
				', [
					$this->amount,
					$this->user_id
				]);
			}
			else
			{
				// Update the currency table to whatever the real value is
				$this->db()->query('
					UPDATE xf_user
					SET ' . $this->Currency->column . ' = ' . $this->Currency->column . ' + ?
					WHERE user_id = ?
				', [
					$this->amount,
					$this->user_id
				]);
			}
		}

		// Grab the current balance
		$balance = $this->db()->fetchOne('
			SELECT ROUND(' . $this->Currency->column . ', ?)
			FROM xf_user
			WHERE user_id = ?
		', [
			max($this->Currency->decimals, 2),
			$this->user_id
		]);

		if ($this->isInsert())
		{
			// Update the balance column
			$this->fastUpdate('balance', $balance);

			if ($this->transaction_state == 'visible')
			{
				/** @var \DBTech\Credits\XF\Entity\User $visitor */
				$visitor = \XF::visitor();

				if ($this->user_id == $visitor->user_id)
				{
					// Also set the current user's balance for consistency
					$visitor->setAsSaved($this->Currency->column, $balance);
				}

				if ($this->Event->alert && $this->getOption('enableAlert'))
				{
					$source = $this->source_user_id
						? $this->SourceUser
						: Helper::repository(\XF\Repository\User::class)
							->getGuestUser()
					;

					// Send alert if needed
					Helper::repository(\DBTech\Credits\Repository\Transaction::class)
						->sendTransactionAlert($this->TargetUser, $source, $this)
					;
				}
			}
		}
	}

	/**
	 * @throws \XF\PrintableException
	 * @throws \XF\Db\Exception
	 */
	protected function _postDelete()
	{
		if ($this->transaction_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}

		$alertRepo = Helper::repository(\XF\Repository\UserAlert::class);
		$alertRepo->fastDeleteAlertsForContent('dbtech_credits_txn', $this->transaction_id);

		if ($this->Currency && $this->transaction_state == 'visible')
		{
			// This is split into two queries for readability
			if (!$this->Currency->negative)
			{
				// Update the currency table, but ensure it resets to 0 if needed
				$this->db()->query('
					UPDATE xf_user
					SET ' . $this->Currency->column . ' = GREATEST(0, ' . $this->Currency->column . ' - ?)
					WHERE user_id = ?
				', [
					$this->amount,
					$this->user_id
				]);
			}
			else
			{
				// Update the currency table to whatever the real value is
				$this->db()->query('
					UPDATE xf_user
					SET ' . $this->Currency->column . ' = ' . $this->Currency->column . ' - ?
					WHERE user_id = ?
				', [
					$this->amount,
					$this->user_id
				]);
			}

			/** @var \DBTech\Credits\XF\Entity\User $visitor */
			$visitor = \XF::visitor();

			if ($this->user_id == $visitor->user_id)
			{
				// Grab the current balance
				$balance = $this->db()->fetchOne('
					SELECT ROUND(' . $this->Currency->column . ', ?)
					FROM xf_user
					WHERE user_id = ?
				', [
					max($this->Currency->decimals, 2),
					$this->user_id
				]);

				// Also set the current user's balance for consistency
				$visitor->setAsSaved($this->Currency->column, $balance);
			}

			/*
			if ($this->Event->alert && $this->getOption('enableAlert'))
			{
				$source = $this->source_user_id
					? $this->SourceUser
					: Helper::repository(\XF\Repository\User::class)
						->getGuestUser()
				;

				// Send alert if needed
				Helper::repository(\DBTech\Credits\Repository\Transaction::class)
					->sendModeratorActionAlert($this, 'negate')
				;
			}
			*/
		}
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_transaction';
		$structure->shortName = 'DBTech\Credits:Transaction';
		$structure->contentType = 'dbtech_credits_txn';
		$structure->primaryKey = 'transaction_id';
		$structure->columns = [
			'transaction_id'    => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'event_id'          => ['type' => self::UINT, 'required' => true],
			'event_trigger_id'  => ['type' => self::STR, 'required' => true],
			'user_id'           => ['type' => self::UINT, 'required' => true],
			'dateline'          => ['type' => self::UINT, 'default' => \XF::$time],
			'source_user_id'    => ['type' => self::UINT, 'required' => true],
			'transaction_state' => [
				'type'          => self::STR,
				'default'       => 'visible',
				'allowedValues' => [
					'visible',
					'moderated',
					'skipped',
					'skipped_maximum'
				]
			],
			'amount'            => ['type' => self::FLOAT, 'required' => true],
			'reference_id'      => ['type' => self::STR, 'default' => ''],
			'content_type'      => ['type' => self::STR, 'maxLength' => 25, 'default' => ''],
			'content_id'        => ['type' => self::UINT, 'default' => 0],
			'node_id'           => ['type' => self::UINT, 'default' => 0],
			'owner_id'          => ['type' => self::UINT, 'default' => 0],
			'multiplier'        => ['type' => self::INT, 'default' => 0],
			'currency_id'       => ['type' => self::UINT, 'required' => true],
			'negate'            => ['type' => self::BOOL, 'default' => false],
			'message'           => ['type' => self::STR, 'default' => '', 'censor' => true],
			'is_disputed'       => ['type' => self::BOOL, 'default' => false],
			'expiry_date'       => ['type' => self::UINT, 'default' => 0],
			'balance'           => ['type' => self::FLOAT, 'default' => 0],
		];
		$structure->getters = [
			'Content' => true
		];
		$structure->relations = [
			'Event' => [
				'entity' => 'DBTech\Credits:Event',
				'type' => self::TO_ONE,
				'conditions' => 'event_id',
				'primary' => true,
			],
			'Currency' => [
				'entity' => 'DBTech\Credits:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			],
			'TargetUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'SourceUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$source_user_id']
				],
				'primary' => true
			],
			'Owner' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$owner_id']
				],
				'primary' => true
			],
			'Forum' => [
				'entity' => 'XF:Forum',
				'type' => self::TO_ONE,
				'conditions' => 'node_id',
				'primary' => true
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_credits_txn'],
					['content_id', '=', '$transaction_id']
				],
				'primary' => true
			]
		];
		$structure->options = [
			'enableAlert' => true,
		];

		$structure->withAliases = [
			'full' => [
				'Event',
				'TargetUser',
				'SourceUser',
				'Owner'
			],
		];

		return $structure;
	}
}