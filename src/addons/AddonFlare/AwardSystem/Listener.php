<?php

namespace AddonFlare\AwardSystem;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function importImporterClasses(\XF\SubContainer\Import $container, \XF\Container $parentContainer, array &$importers)
    {
        $importers[] = 'AddonFlare\AwardSystem:bdMedal';
        $importers[] = 'AddonFlare\AwardSystem:MasterBadge';
    }

    public static function adminOptionControllerPostDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params, \XF\Mvc\Reply\AbstractReply &$reply)
    {
        if ($params['group_id'] == 'af_awardSystem')
        {
            $reply->setSectionContext('af_as_award_system_settings');
        }
        if ($params['group_id'] == 'af_as_levels')
        {
            $reply->setSectionContext('af_as_award_levels_settings');
        }
    }

    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        $class = \XF::extendClass('AddonFlare\AwardSystem\Template\TemplaterSetup');
        $templaterSetup = new $class();

        $templater->addFunction('award_image', [$templaterSetup, 'fnAwardImage']);
        $templater->addFunction('user_award_level', [$templaterSetup, 'fnUserAwardLevel']);
    }

    public static function noticesSetup(\XF\Pub\App $app, \XF\NoticeList $noticeList, array $pageParams)
    {
        $options = $app->options();
        $visitor = \XF::visitor();
        $templater = $app->templater();

        $noticeOptions = $options->af_as_pending_notice_show;
        $noticeTemplates = array_filter(explode(',', $noticeOptions['templates']));

        // never show in these templates
        $neverShowTemplates = [
            'af_as_pending_user_awards_list'
        ];

        if ($visitor->canManageAwards() && $noticeOptions['enabled'] && $noticeTemplates && isset($pageParams['template']))
        {
            if (in_array($pageParams['template'], $noticeTemplates) || in_array('*', $noticeTemplates))
            {
                if (!in_array($pageParams['template'], $neverShowTemplates))
                {
                    $awardsPendingTotal = $app->repository('AddonFlare\AwardSystem:UserAward')->getPendingUserAwardsTotal();
                    if ($awardsPendingTotal > 0)
                    {
                        $noticeList->addNotice('af_as_notice_pending_user_awards', 'block',
                            $templater->renderTemplate('public:af_as_notice_pending_user_awards', $pageParams + ['awardsPendingTotal' => $awardsPendingTotal]),
                            ['display_style' => 'accent']
                        );
                    }
                }
            }
        }
    }

    ### NOT USED BECAUSE <xf:checkbowrow> doesn't support <xf:include template="" />
    ### Used normal template modifications instead
    public static function templateModificationCallbackHelperCriteria($matches)
    {
        $found = $matches[0];

        $find = $replace = [];

        $includeTemplate = function($find, $template, $before = true)
        {
            $include = '<xf:include template="' .$template. '" />';

            if ($before)
            {
                return $include . "\n" . $find;
            }
            else
            {
                return $find . "\n" . $include;
            }
        };

        $find[1] = '<!--[XF:user:content_after_messages]-->';
        $replace[1] = $includeTemplate($find[1], 'af_as_helper_criteria_user_content');

        $find[2] = '<!--[XF:user:profile_top]-->';
        $replace[2] = $includeTemplate($find[2], 'af_as_helper_criteria_user_profile');

        $found = strtr($found, array_combine($find, $replace));

        return $found;
    }

    public static function templateModificationCallbackMessageMacros($matches)
    {
        $found = $matches[0];

        $getIncludeString = function($position)
        {
            // disabled positions for this add-on
            $enabledPositions = [
                'userExtras_top', 'userExtras_bottom',
                'userDetails_top', 'userDetails_bottom',
            ];

            if (!in_array($position, $enabledPositions))
            {
                return '';
            }

            $str = [
                "\n",
                '<xf:include template="af_as_message_postbit">',
                '<xf:set var="$postbitPosition" value="'.$position.'" />',
                '</xf:include>',
                "\n",
            ];

            return implode("\n", $str);
        };

        $found = $found . $getIncludeString('end');

        // userExtras positions
        $re = '/(?<before_content><div class="message-userExtras">\s*<xf:contentcheck>)(?<content>.+)(?<after_content><\/xf:contentcheck>\s*<\/div>)/iUs';
        $found = preg_replace_callback($re, function($matches) use ($getIncludeString)
        {
            return $getIncludeString('userExtras_before')
            . $matches['before_content'] . $getIncludeString('userExtras_top')
            . $matches['content']
            . $getIncludeString('userExtras_bottom') . $matches['after_content']
            . $getIncludeString('userExtras_after');
        }, $found);


        // userDetails positions
        $re = '/(?<before_content><div class="message-userDetails">)(?<content>.+)(?<after_content>\n\s*<\/div>)/siU';
        $found = preg_replace_callback($re, function($matches) use ($getIncludeString)
        {
            return $getIncludeString('userDetails_before')
            . $matches['before_content'] . $getIncludeString('userDetails_top')
            . $matches['content']
            . $getIncludeString('userDetails_bottom') . $matches['after_content']
            . $getIncludeString('userDetails_after');
        }, $found);

        // other positions
        $find = $replace = [];

        // after username
        $find[1] = '<xf:usertitle ';
        $replace[1] = $getIncludeString('afterUsername') . $find[1];

        // before custom user fields
        $find[2] = '<xf:if is="$extras.custom_fields">';
        $replace[2] = $getIncludeString('beforeCustomFields') . $find[2];

        // awards total
        $find[3] = '<xf:if is="$extras.age && $user.Profile.age">';
        $replace[3] =
        '<xf:macro template="af_as_message_postbit" name="award_level" arg-user="{$user}" />'.
        '<xf:macro template="af_as_message_postbit" name="award_total" arg-user="{$user}" />'.
         "\n" . $find[3];

        $found = strtr($found, array_combine($find, $replace));

        return $found;
    }

    public static function appPubRenderPage(\XF\Pub\App $app, array &$params, \XF\Mvc\Reply\AbstractReply $reply, \XF\Mvc\Renderer\AbstractRenderer $renderer)
    {
    }

    public static function templaterMacroPreRender(\XF\Template\Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars)
    {
        if (!empty($arguments['group']) && $arguments['group']->group_id == 'af_awardSystem')
        {
            // Override template name
            $template = 'af_as_option_macros';

            // Or use 'option_form_block_tabs' for tabs
            $name = 'option_form_block_tabs';

            // Your block header configurations
            $arguments['headers'] = [
                'generalOptions'      => [
                    'label'           => \XF::phrase('general_options'),
                    'minDisplayOrder' => 0,
                    'maxDisplayOrder' => 200,
                    'active'          => true // Only used for tabs, indicates default active tab
                ],
                'levelsOptions'        => [
                    'label'           => \XF::phrase('af_as_level_system_options'),
                    'minDisplayOrder' => 200,
                    'maxDisplayOrder' => -1 // This allows for any higher display order value
                ],
            ];
        }
    }

    const ID = 'AddonFlare/AwardSystem';
    const TITLE = 'Awards System';
    const ID_NUM = '4ffce04d92a4d6cb21c1494cdfcd6dc1';
    public static $IDS1 = [
        97, 102, 95, 97, 115, 95, 122,
    ];
}