<?php

namespace AddonFlare\AwardSystem\Cli\Command\Rebuild;

use Symfony\Component\Console\Input\InputOption;

class UserAwardTotalRebuild extends \XF\Cli\Command\Rebuild\AbstractRebuildCommand
{
    protected function getRebuildName()
    {
        return 'af-as-user-award-totals';
    }

    protected function getRebuildDescription()
    {
        return 'Rebuilds user award totals.';
    }

    protected function getRebuildClass()
    {
        return 'AddonFlare\AwardSystem:UserAwardTotalRebuild';
    }
}