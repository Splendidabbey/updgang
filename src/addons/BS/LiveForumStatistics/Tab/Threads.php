<?php

namespace BS\LiveForumStatistics\Tab;

use BS\LiveForumStatistics\Entity\Tab;
use BS\LiveForumStatistics\Repository\UserIgnored;
use BS\LiveForumStatistics\Tab\Concerns\ItemOptions;
use BS\LiveForumStatistics\Tab\Concerns\MainOptions;
use BS\LiveForumStatistics\Tab\Concerns\TabPagination;
use BS\LiveForumStatistics\Tab\Concerns\Threads\ApplyOptions;
use BS\LiveForumStatistics\Tab\Concerns\UserOptions;
use BS\LiveForumStatistics\Tab\Concerns\ViewPermissions;
use XF\Pub\Controller\AbstractController;

class Threads extends AbstractTab
{
    use ViewPermissions;
    use ItemOptions, MainOptions, UserOptions;
    use ApplyOptions;
    use TabPagination;

    protected $itemPrefix = 'thread';

    protected $defaultOptions = [
        'limit' => 15,
        'order' => [['last_post_date', 'desc']],

        'exclude_node_ids' => [],
        'prefix_ids' => [-1],
        'exclude_prefix_ids' => [],
        'discussion_open' => '',
        'sticky' => '',
        'cut_off' => ['>', 0],
        'not_thread_ids' => [],
        'thread_ids' => [],

        'by_user' => [],
        'user_is_not' => [],
        'user_has_groups' => [],
        'user_has_not_groups' => [],
        'language_ids' => [-1],
        'watched' => false
    ];

    public function canView()
    {
        return $this->canViewByUser() && $this->canViewByLanguage();
    }

    public function canSetting()
    {
        $visitor = \XF::visitor();
        return $visitor->user_id && ($this->app->options()->lfsThreadsLimit['enable_custom_limit'] || $visitor->canIgnoreForumInLfs());
    }

    public function render($finalRender = true, \XF\Http\Request $request = null)
    {
        $options = $this->options;
        $visitor = \XF::visitor();

        $threadFinder = $this->finder('XF:Thread')
            ->where('discussion_state', 'visible')
            ->skipIgnored($visitor)
            ->skipIgnoredThreadsInLfs($visitor, $this->tabConfig['tab_id'])
            ->withReadData()
            ->with('full')
            ->with(['FirstPost', 'FirstPost.Bookmarks|' . $visitor->user_id, 'LastPost'])
            ->order([['bs_lfs_is_sticked', 'DESC'], ['bs_lfs_sticked_order', 'ASC']]);

        if ($request)
        {
            $this->applyRequestFilter($threadFinder, $request, $options);
        }

        $threadFinder->order($options['order']);

        $this->applyOptions($threadFinder, $options);

        $this->renderPagination($threadFinder, $viewParams, $request, $options['limit'], $options['limit']);

        $threads = $threadFinder->fetch()
            ->filterViewable()
            ->slice(0, $options['limit']);

        $stickedLinks = $viewParams['page'] > 1 ? null : $this->repository('BS\LiveForumStatistics:StickedLink')
            ->findActiveLinksForList()
            ->fetch();

        $viewParams += compact('threads', 'stickedLinks');
        $viewParams['hasItems'] = $threads->count() || ! empty($stickedLinks);

        return $this->finalRender($this->getTemplateName(), $viewParams, $finalRender);
    }

    public function renderSetting(AbstractController $controller, Tab $tab)
    {
        $visitor = \XF::visitor();
        $visitorExcludedNodes = $visitor->Profile->bs_lfs_ignored_forums[$this->tabConfig['tab_id']] ?? [];

        $nodeTree = null;
        $excludedNodes = array_merge($this->options['exclude_node_ids'], $visitorExcludedNodes);

        if ($visitor->canIgnoreForumInLfs())
        {
            $nodeRepo = $this->getNodeRepo();

            $nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
        }

        $currentLimit = $this->options['limit'];

        if ($this->app->options()->lfsThreadsLimit['enable_custom_limit'])
        {
            $currentLimit = min((int)$controller->request()->getCookie("lfs_tab_{$this->tabConfig['tab_id']}_limit"), 500);
        }

        return $controller->view('BS\LiveForumStatistics:Tab\Setting', $this->getSettingTemplate(), compact('tab', 'currentLimit', 'nodeTree', 'excludedNodes'));
    }

    public function saveSetting(AbstractController $controller, Tab $tab)
    {
        $visitor = \XF::visitor();
        if ($visitor->canIgnoreForumInLfs())
        {
            $nodeIds = $controller->filter('exclude_node_ids', 'array-uint');
            $this->getUserIgnoredRepo()->replaceIgnoredUserForum($visitor->user_id, $nodeIds, $this->tabConfig['tab_id']);
        }

        if ($this->app->options()->lfsThreadsLimit['enable_custom_limit'])
        {
            $this->app->response()->setCookie("lfs_tab_{$this->tabConfig['tab_id']}_limit", $controller->filter('limit', 'uint'));
        }

        return $controller->redirect($controller->getDynamicRedirect(null, false));
    }

    protected function _getParamsForOptions($preset)
    {
        $nodeRepo = $this->repository('XF:Node');

        $prefixRepo = $this->repository('XF:ThreadPrefix');
        $prefixListData = $prefixRepo->getPrefixListData();

        $userGroups = $this->repository('XF:UserGroup')->getUserGroupTitlePairs();

        $options = $this->options;

        $this->userOptionsParams($options);

        return [
            'nodeTree' => $nodeRepo->createNodeTree($nodeRepo->getFullNodeList()),

            'prefixGroups' => $prefixListData['prefixGroups'],
            'prefixesGrouped' => $prefixListData['prefixesGrouped'],

            'userGroups' => $userGroups,

            'options' => $options
        ];
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        return $this->verifyMainOptions($request, $options, $error)
            && $this->verifyItemOptions($request, $options, $error)
            && $this->verifyUserOptions($request, $options, $error);
    }

    protected function applyOptions(\XF\Finder\Thread $finder, array $options)
    {
        $this->applyThreadOptions($finder, $options);
        $this->applyUserOptions($finder, $options);
    }

    protected function applyRequestFilter(\XF\Finder\Thread $finder, \XF\Http\Request $request, array &$options)
    {
        if ($this->app->options()->lfsThreadsLimit['enable_custom_limit'])
        {
            $limit = min((int)$request->getCookie("lfs_tab_{$this->tabConfig['tab_id']}_limit"), 500);

            if ($limit)
            {
                $options['limit'] = $limit;
            }
        }
    }

    public function getPresets()
    {
        return [
            'latest_posts' => [
                'title'   => \XF::phrase('lfs_thread_preset.latest_posts'),
                'options' => [
                    'order'    => [['last_post_date', 'desc']],
                    'template' => 'preset'
                ]
            ],
            'new_threads' => [
                'title'   => \XF::phrase('lfs_thread_preset.new_threads'),
                'options' => [
                    'order' => [['post_date', 'desc']],
                    'template' => 'preset'
                ]
            ],
            'hottest_threads' => [
                'title'   => \XF::phrase('lfs_thread_preset.hottest_threads'),
                'options' => [
                    'order' => [
                        ['view_count', 'desc'],
                        ['reply_count', 'desc'],
                        ['first_post_reaction_score', 'desc']
                    ],
                    'cut_off' => ['<', '7'],
                    'template' => 'preset'
                ]
            ]
        ];
    }

    public function getDefaultTemplate($preset = null)
    {
        $request = $this->app->request();

        $pathFormat = __DIR__ . '/stubs/threads/%s.stub';
        $stubName = 'default';

        switch ($request->filter('thread_definition_template', 'str'))
        {
            case 'adaptive':
                $stubName = 'adaptive';
                break;

            case 'preset':
                $preset = $request->filter('preset', 'str');

                if (file_exists(sprintf($pathFormat, $preset)))
                {
                    $stubName = $preset;
                }
                break;
        }

        return file_get_contents(sprintf($pathFormat, $stubName));
    }

    /** @return \XF\Repository\Node */
    protected function getNodeRepo()
    {
        return $this->repository('XF:Node');
    }

    /** @return UserIgnored */
    protected function getUserIgnoredRepo()
    {
        return $this->repository('BS\LiveForumStatistics:UserIgnored');
    }
}