<?php

namespace XENTR\ForumStats;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	public function install(array $stepParams = [])
    {
        $this->createWidget(

            'xentr_forum_statistics',
            'xentr_forum_statistics_widget',
            [
                'positions' => []
            ]

        );
    }

    public function upgrade(array $stepParams = [])
    {
        // Implement upgrade() method.
    }

    public function uninstall(array $stepParams = [])
    {
        $this->deleteWidget('xentr_forum_statistics');
    }
}