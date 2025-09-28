<?php

namespace AddonFlare\AwardSystem\Template;

class TemplaterSetup
{
    public function fnAwardImage($templater, &$escape, $award, $href = '')
    {
        if (!($award instanceof \AddonFlare\AwardSystem\Entity\Award))
        {
            return '';
        }

        $escape = false;

        if ($href)
        {
            $tag = 'a';
            $hrefAttr = 'href="' . htmlspecialchars($href) . '"';
            $relAttr = 'rel="nofollow"';
        }
        else
        {
            $tag = 'span';
            $hrefAttr = '';
            $relAttr = '';
        }

        if ($imageUrl = $award['icon_url'])
        {
            if ($award['inline_css'])
            {
                $templater->includeCss('public:af_as_award_display.less');
            }

            $alt = $award['title'];
            $alt = ''; // dont use this

            return "<{$tag} {$hrefAttr} {$relAttr}>"
                . '<img class="afAwardImg afAwardImg--'.$award['award_id'].'" data-xf-init="tooltip" data-placement="right" src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($alt) . '" title="' . \XF::phrase('af_as_user_award_tooltip', ['title' => $award['title'], 'description' => $award['description']]) . '" />'
                . "</{$tag}>";
        }
        else
        {
            return '';
        }
    }

    public function fnUserAwardLevel($templater, &$escape, $user, $href = '')
    {
        if (!($user instanceof \XF\Entity\User) || !$user->user_id)
        {
            return '';
        }

        $escape = false;

        $classes = ['afAwardLevel'];

        if ($user->award_level_color_class)
        {
            $classes[] = 'afAwardLevel--style';
            $classes[] = $user->award_level_color_class;
        }

        return '<div class="'.htmlspecialchars(implode(' ', $classes)).'">' .$templater->fnNumber($templater, $escape, $user->af_as_award_level). '</div>';
    }
}