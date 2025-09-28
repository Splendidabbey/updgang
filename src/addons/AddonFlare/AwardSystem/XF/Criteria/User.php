<?php

namespace AddonFlare\AwardSystem\XF\Criteria;

use XF\Util\Arr;

class User extends XFCP_User
{
    protected function _matchAfAsAwardLevel(array $data, \XF\Entity\User $user)
    {
        if (empty($data['level']))
        {
            // no level to check
            return false;
        }

        return $user->af_as_award_level >= $data['level'];
    }

    protected function _matchAfAsMessagesPostedInForum(array $data, \XF\Entity\User $user)
    {
        $db = \XF::db();

        $lookbackTime = \XF::$time - (86400 * $data['days']);

        if (
            empty($data['node_ids']) ||
            (!$nodeIds = array_map('intval', $data['node_ids']))
        )
        {
            // no nodes to check
            return false;
        }

        $total = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post xf_post
            INNER JOIN xf_thread xf_thread ON (xf_thread.thread_id = xf_post.thread_id)
            WHERE
                xf_post.user_id = ?
                AND xf_post.post_date >= ?
                AND xf_thread.node_id IN (" .$db->quote($nodeIds). ")
        ", [$user->user_id, $lookbackTime]);

        // posted at least x messages
        return $total >= $data['messages'];
    }

    protected function _matchAfAsThreadInForumWithReplies(array $data, \XF\Entity\User $user)
    {
        $db = \XF::db();

        if (
            empty($data['node_ids']) ||
            (!$nodeIds = array_map('intval', $data['node_ids']))
        )
        {
            // no nodes to check
            return false;
        }

        $total = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE
                user_id = ?
                AND node_id IN (" .$db->quote($nodeIds). ")
                AND reply_count >= ?
        ", [$user->user_id, $data['replies']]);

        // posted at least 1 thread with x replies
        return $total;
    }

    protected function _matchAfAsUserFieldsComplete(array $data, \XF\Entity\User $user)
    {
        if (
            empty($data['field_ids']) ||
            !$user->user_id ||
            !$user->Profile ||
            (!$cFS = $user->Profile->custom_fields)
        )
        {
            // no fields to check
            return false;
        }

        $fieldIds = $data['field_ids'];
        $cFS = $user->Profile->custom_fields;

        foreach ($fieldIds as $fieldId)
        {
            // check if it exists for user
            if (!isset($cFS->{$fieldId}))
            {
                return false;
            }
            else
            {
                $value = $cFS->{$fieldId};

                // check if empty
                if ($value === '' || $value === false || $value === null || $value === [])
                {
                    return false;
                }
            }
        }

        return true;
    }

    public function getExtraTemplateData()
    {
        $templateData = parent::getExtraTemplateData();

        $editableUserFields = \XF::finder('XF:UserField')
            ->where('user_editable', ['once', 'yes'])
            ->order([['display_group', 'ASC'], ['display_order', 'ASC']])
            ->fetch()->pluckNamed('title', 'field_id');

        $editableUserFieldChoices = [];

        foreach ($editableUserFields as $fieldId => $label)
        {
            $editableUserFieldChoices[$fieldId] = [
                'value' => $fieldId,
                'label' => $label,
                '_type' => 'option',
            ];
        }

        $templateData['editableUserFields'] = $editableUserFieldChoices;

        return $templateData;
    }
}