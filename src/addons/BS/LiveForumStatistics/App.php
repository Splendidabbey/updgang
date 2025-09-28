<?php

namespace BS\LiveForumStatistics;

class App
{
    protected static $renderers;

    public static function getTabRenderer($identifier, array $tabConfig = [])
    {
        $tabConfig['options'] = $tabConfig['options'] ?? [];

        if (isset(self::$renderers[$identifier]))
        {
            self::$renderers[$identifier]->setTabConfig($tabConfig);
            return self::$renderers[$identifier];
        }

        $app = \XF::app();
        $rendererClass = \XF::stringToClass($identifier, '%s\Tab\%s');
        $rendererClass = $app->extension()->extendClass($rendererClass, '\BS\LiveForumStatistics\Tab\AbstractTab');
        if (! $rendererClass || ! class_exists($rendererClass))
        {
            throw new \LogicException("Could not find tab renderer '$rendererClass' for '$identifier'");
        }

        $renderer = new $rendererClass($app, $tabConfig);
        self::$renderers[$identifier] = $renderer;

        return $renderer;
    }
}