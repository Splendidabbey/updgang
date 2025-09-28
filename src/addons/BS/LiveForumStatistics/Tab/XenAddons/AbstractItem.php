<?php

namespace BS\LiveForumStatistics\Tab\XenAddons;

use BS\LiveForumStatistics\Tab\AbstractTab;
use BS\LiveForumStatistics\Tab\Concerns\ItemOptions;
use BS\LiveForumStatistics\Tab\Concerns\MainOptions;
use BS\LiveForumStatistics\Tab\Concerns\TabPagination;
use BS\LiveForumStatistics\Tab\Concerns\UserOptions;
use BS\LiveForumStatistics\Tab\Concerns\ViewPermissions;
use BS\LiveForumStatistics\Tab\XenAddons\Concerns\ApplyOptions;
use XF\App;

abstract class AbstractItem extends AbstractTab
{
    use ViewPermissions;
    use ItemOptions, MainOptions, UserOptions;
    use ApplyOptions;
    use TabPagination;

    protected $addOnId;
    protected $addOnNamespace;
    protected $addOnName;

    protected $itemPrefix;

    protected $finderName;

    protected $itemsVar;

    protected $createDateColumn;

    public function __construct(App $app, array $tabConfig = [])
    {
        parent::__construct($app, $tabConfig);

        $this->addOnId = 'XenAddons/' . $this->addOnName;
        $this->addOnNamespace = 'XenAddons\\' . $this->addOnName;
    }

    public function canView()
    {
        return $this->canViewByUser() && $this->canViewByLanguage();
    }

    public function isAvailable()
    {
        $installedAddOns = $this->app->addOnManager()->getInstalledAddOns();
        if (isset($installedAddOns[$this->addOnId]) && $installedAddOns[$this->addOnId]->isActive())
        {
            return true;
        }

        return false;
    }

    public function render($finalRender = true, \XF\Http\Request $request = null)
    {
        $options = $this->options;

        $finder = $this->addOnNamespace . ':' . $this->finderName;

        /** @var \XenAddons\Showcase\Finder\Item|\XenAddons\AMS\Finder\ArticleItem|\XF\Mvc\Entity\Finder $itemFinder */
        $itemFinder = $this->finder($finder)
            ->where($this->itemPrefix . '_state', 'visible')
            ->withReadData()
            ->with('full')
            ->order($options['order']);

        $this->skipIgnored($itemFinder);

        $this->applyOptions($itemFinder, $options);

        $this->renderPagination($itemFinder, $viewParams, $request, $options['limit'], $options['limit']);

        $items = $itemFinder->fetch()
            ->filterViewable()
            ->slice(0, $options['limit']);

        $viewParams[$this->itemsVar] = $items;
        $viewParams['hasItems'] = (bool)$items->count();

        return $this->finalRender($this->getTemplateName(), $viewParams, $finalRender);
    }

    protected function _getParamsForOptions($preset)
    {
        $addOnNamespace = $this->addOnNamespace;

        /** @var \XenAddons\Showcase\Repository\Category|\XenAddons\AMS\Repository\Category $categoryRepo */
        $categoryRepo = $this->repository($addOnNamespace . ':Category');

        /** @var \XenAddons\Showcase\Repository\ItemPrefix|\XenAddons\AMS\Repository\ArticlePrefix $prefixRepo */
        $prefixRepo = $this->repository($addOnNamespace . ':' . ucfirst($this->itemPrefix) . 'Prefix');
        $prefixListData = $prefixRepo->getPrefixListData();

        $userGroups = $this->repository('XF:UserGroup')->getUserGroupTitlePairs();

        $options = $this->options;

        $this->userOptionsParams($options);

        return [
            'categoryTree' => $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch()),

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

    protected function applyOptions(\XF\Mvc\Entity\Finder $finder, array $options)
    {
        $this->applyItemOptions($finder, $options);
        $this->applyUserOptions($finder, $options);
    }

    protected function skipIgnored(\XF\Mvc\Entity\Finder $finder)
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id && $visitor->Profile && $visitor->Profile->ignored)
        {
            $finder->where('user_id', '<>', array_keys($visitor->Profile->ignored));
        }
    }
}