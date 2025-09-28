<?php

namespace AddonFlare\AwardSystem\Cli\Command\Rebuild;

use Symfony\Component\Console\Input\InputOption;

class FixAwardOverwrite extends \XF\Cli\Command\Rebuild\AbstractRebuildCommand
{
    protected function getRebuildName()
    {
        return 'af-as-fix-award-overwrite';
    }

    protected function getRebuildDescription()
    {
        return 'Removes awards that have been overwritten. Use with caution.';
    }

    protected function getRebuildClass()
    {
        return 'AddonFlare\AwardSystem:FixAwardOverwrite';
    }
}