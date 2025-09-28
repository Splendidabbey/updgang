<?php

namespace BS\LiveForumStatistics\Tab;

use BS\LiveForumStatistics\Entity\Tab;
use XF\App;
use XF\Pub\Controller\AbstractController;

abstract class AbstractTab
{
    /**
     * @var App
     */
    protected $app;

    protected $tabConfig;

    protected $options;
    protected $defaultOptions = [];

    public function __construct(App $app, array $tabConfig = [])
    {
        $this->app = $app;
        $this->tabConfig = $tabConfig;
        $this->options = $this->setupOptions($tabConfig['options']);
    }

    public function canView()
    {
        return true;
    }

    public function canSetting()
    {
        return false;
    }

    public function isAvailable()
    {
        return true;
    }

    abstract public function render($finalRender = true, \XF\Http\Request $request = null);

    public function finalRender($templateName, array $viewParams = [], $finalRender = true)
    {
        $viewParams['tab'] = $this->tabConfig;

        if (! $finalRender)
        {
            return [$templateName, $viewParams];
        }

        $templateName = $this->getTemplateName();
        if (! $templateName)
        {
            return '';
        }
        return $this->app->templater()->renderTemplate(
            $templateName, $viewParams
        );
    }

    public function renderSetting(AbstractController $controller, Tab $tab)
    {
        if ($this->canSetting())
        {
            throw new \LogicException('Function renderSetting must be overridden.');
        }

        return null;
    }

    public function saveSetting(AbstractController $controller, Tab $tab)
    {
        if ($this->canSetting())
        {
            throw new \LogicException('Function saveSetting must be overridden.');
        }

        return null;
    }

    protected function setupOptions(array $options)
    {
        return array_replace($this->defaultOptions, $options);
    }

    public function renderOptions()
    {
        $templateName = $this->getOptionsTemplate();
        if (!$templateName)
        {
            return '';
        }

        $viewParams = array_replace([
            'tabConfig' => $this->tabConfig
        ], $this->getParamsForOptions());

        return $this->app->templater()->renderTemplate(
            $templateName, $viewParams
        );
    }

    public function getParamsForOptions($preset = null)
    {
        $params = $this->_getParamsForOptions($preset);

        $options = $params['options'] ?? $this->options;
        $presets = $this->getPresets();

        if ($preset && isset($presets[$preset]))
        {
            $options = array_merge($options, $presets[$preset]['options']);
        }

        $params['preset']  = $preset;
        $params['presets'] = $presets;
        $params['options'] = $options;

        return $params;
    }

    protected function _getParamsForOptions($preset)
    {
        return [];
    }

    public function getPresets()
    {
        return [];
    }

    public function getTemplateName()
    {
        return 'public:lfs_tab.' . $this->tabConfig['tab_id'];
    }

    public function getOptionsTemplate()
    {
        return 'admin:lfs_tab_def_options.' . $this->tabConfig['definition_id'];
    }

    public function getSettingTemplate()
    {
        return 'public:lfs_tab_setting.' . $this->tabConfig['definition_id'];
    }

    public function setTabConfig(array $tabConfig)
    {
        $this->tabConfig = $tabConfig;
        $this->options = $this->setupOptions($tabConfig['options']);
    }

    public function getTabConfig()
    {
        return $this->tabConfig;
    }

    public function getDefaultTemplate($preset = null)
    {
        return '';
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        return true;
    }

    /**
     * @return App
     */
    public function app()
    {
        return $this->app;
    }

    /**
     * @return \XF\Db\AbstractAdapter
     */
    public function db()
    {
        return $this->app->db();
    }

    /**
     * @return \XF\Mvc\Entity\Manager
     */
    public function em()
    {
        return $this->app->em();
    }

    /**
     * @param string $repository
     *
     * @return \XF\Mvc\Entity\Repository
     */
    public function repository($repository)
    {
        return $this->app->repository($repository);
    }

    /**
     * @param $finder
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function finder($finder)
    {
        return $this->app->finder($finder);
    }
}