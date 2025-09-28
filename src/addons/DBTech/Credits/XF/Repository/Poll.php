<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Helper;

class Poll extends XFCP_Poll
{
	/**
	 * @param \XF\Entity\Poll $poll
	 * @param $votes
	 * @param \XF\Entity\User|null $voter
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function voteOnPoll(\XF\Entity\Poll $poll, $votes, ?\XF\Entity\User $voter = null)
	{
		$voter = $voter ?: \XF::visitor();
		$responses = $poll->Responses;

		if (!is_array($votes))
		{
			$votes = [$votes];
		}

		foreach ($votes AS $k => $responseId)
		{
			if (!isset($responses[$responseId]))
			{
				unset($votes[$k]);
			}
		}

		if (!$votes)
		{
			return parent::voteOnPoll($poll, $votes, $voter);
		}

		if (!$voter->user_id)
		{
			return parent::voteOnPoll($poll, $votes, $voter);
		}

		$contentInfo = $poll->getContent();
		if ($contentInfo !== null)
		{
			// Grab our previous votes
			$previousVotes = $this->db()->fetchAllKeyed('
				SELECT poll_response_id, vote_date
				FROM xf_poll_vote
				WHERE poll_id = ?
					AND user_id = ?
			', 'poll_response_id', [$poll->poll_id, $voter->user_id]);

			$numOldVotes = $oldVoteDate = 0;
			foreach ($previousVotes AS $previousVote)
			{
				$numOldVotes++;
				$oldVoteDate = $previousVote['vote_date'];
			}

			$nodeId = 0;
			if ($poll->content_type == 'thread')
			{
				$nodeId = $contentInfo->node_id;
			}

			$eventTriggerRepo = Helper::repository(\DBTech\Credits\Repository\EventTrigger::class);

			if ($numOldVotes)
			{
				$eventTriggerRepo->getHandler('vote')
					->undo($poll->poll_id, [
						'multiplier' => $numOldVotes,
						'node_id' => $nodeId,
						'owner_id' => $contentInfo->user_id,
						'timestamp' => $oldVoteDate,
						'content_type' => $poll->content_type,
						'content_id' => $poll->content_id
					], $voter)
				;
			}

			if (count($votes))
			{
				$eventTriggerRepo->getHandler('vote')
					->apply($poll->poll_id, [
						'multiplier' => count($votes),
						'node_id' => $nodeId,
						'owner_id' => $contentInfo->user_id,
						'timestamp' => \XF::$time,
						'content_type' => $poll->content_type,
						'content_id' => $poll->content_id
					], $voter)
				;
			}
		}

		return parent::voteOnPoll($poll, $votes, $voter);
	}
}