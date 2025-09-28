<?php

namespace BS\LiveForumStatistics\Widget;

use XF\Widget\AbstractWidget;

class Statistics extends AbstractWidget
{
    protected $defaultOptions = [
        'update_interval' => 15,
        'show_groups'  => [],
        'show_tabs'    => [],
        'sound_link'   => ''
    ];

    public function render()
    {
        $visitor = \XF::visitor();

        if (! (method_exists($visitor, 'canViewLfs') && $visitor->canViewLfs())
            || ((method_exists($visitor, 'canHideLfs') && $visitor->canHideLfs()) && ($visitor->Option->bs_lfs_disable ?? false))
        )
        {
            return '';
        }

        $options = $this->options;

        $tabGroups = $this->getTabGroupRepo()
            ->getGroupsForWidget($options['show_groups'], $options['show_tabs']);

        $isMuted = filter_var(
            $this->app->request()->getCookie('lfs_mute_' . $this->widgetConfig->widgetId)
        , FILTER_VALIDATE_BOOLEAN);

        return $this->renderer('widget_live_forum_statistics', compact('tabGroups', 'isMuted'));
    }

    protected function getDefaultTemplateParams($context)
    {
        $params = parent::getDefaultTemplateParams($context);

        if ($context == 'options')
        {
            $params['tabGroups'] = $this->getTabGroupRepo()->findActiveGroupsForList();
        }

        return $params;
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\TabGroup
     */
    protected function getTabGroupRepo()
    {
        return $this->repository('BS\LiveForumStatistics:TabGroup');
    }
}