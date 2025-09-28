<?php

namespace AddonFlare\AwardSystem;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	// install
	public function installStep1()
	{
	    $this->schemaManager()->createTable('xf_af_as_award', function(Create $table)
	    {
	        $table->addColumn('award_id', 'int')->autoIncrement();
			$table->addColumn('award_category_id', 'int')->setDefault(0);
			$table->addColumn('award_trophy_id', 'int')->setDefault(0);
			$table->addColumn('award_admin_id', 'int')->setDefault(0);
			$table->addColumn('award_icon_ext', 'varchar', 10)->setDefault('');
            $table->addColumn('award_icon_date', 'int')->setDefault(0);
	      	$table->addColumn('display_order','int')->setDefault(1);

            $table->addKey('award_category_id');
	    });
	}

	public function installStep2()
	{
		$this->schemaManager()->createTable('xf_af_as_award_category', function(Create $table)
	    {
	    	$table->addColumn('award_category_id','int')->autoIncrement();
	      	$table->addColumn('display_order','int');
	    });
	}

	public function installStep3()
	{
	    $this->schemaManager()->createTable('xf_af_as_user_award', function(Create $table)
	    {
	        $table->addColumn('user_award_id', 'int')->autoIncrement();
            $table->addColumn('award_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('recommended_user_id', 'int');
	        $table->addColumn('award_reason', 'text');
	        $table->addColumn('date_received', 'int')->nullable()->setDefault(null);
	        $table->addColumn('date_requested', 'int');
	        $table->addColumn('status', 'varchar', 25);

            $table->addKey('award_id');
            $table->addKey('user_id');
            $table->addKey('status');
	    });
	}

    public function installStep4()
    {
        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('af_as_award_total', 'int')->setDefault(0);
        });
    }

    public function installStep5()
    {
        $this->schemaManager()->alterTable('xf_af_as_user_award', function(Alter $table)
        {
            $table->addColumn('display_order', 'int')->setDefault(1);
            $table->addKey('display_order');
        });
    }

    public function installStep6()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_af_as_award_category', function(Alter $table)
        {
            $table->addColumn('display_mode', 'enum')->values(['visible', 'step', 'hidden'])->setDefault('visible');
        });

        $sm->alterTable('xf_af_as_award', function(Alter $table)
        {
            $table->addColumn('award_points', 'int')->setDefault(0);
            $table->addColumn('can_feature', 'tinyint', 3)->setDefault(1);
            $table->addColumn('show_in_list', 'tinyint', 3)->setDefault(1);
            $table->addColumn('can_request', 'tinyint', 3)->setDefault(1);
            $table->addColumn('can_recommend', 'tinyint', 3)->setDefault(1);
            $table->addColumn('allow_multiple', 'tinyint', 3)->setDefault(0);

            $table->addKey('can_feature');
        });

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('af_as_award_points', 'int')->setDefault(0)->after('af_as_award_total');;
        });

        $sm->alterTable('xf_af_as_user_award', function(Alter $table)
        {
            $table->addColumn('is_featured', 'tinyint', 3)->setDefault(0);

            $table->addKey('is_featured');
        });

        $db = $this->db();

        // reset this since our new system requires a fresh start and uses the is_featured column too
        $db->update('xf_af_as_user_award', ['display_order' => 0], '1 = 1');
    }

    public function installStep7()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_af_as_user_award', function(Alter $table)
        {
            // change to enum and add double column key for optimization
            $table->changeColumn('status')->resetDefinition()->type('enum', ['pending', 'approved', 'rejected'])->setDefault('pending');

            $table->addKey(['award_id', 'status'], 'award_id_status');
        });
    }

    public function installStep8()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user_privacy', function(Alter $table)
        {
            $table->addColumn('af_as_allow_view_profile', 'enum')->values(['everyone','members','followed','none'])->setDefault('everyone');
        });

        $sm->alterTable('xf_af_as_award', function(Alter $table)
        {
            $table->addColumn('inline_css', 'mediumblob');
        });

        $sm->alterTable('xf_user_option', function(Alter $alter)
        {
            $alter->addColumn('af_as_auto_feature', 'tinyint', 3)->nullable()->setDefault(1);
        });
    }

    public function installStep9()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_af_as_award_category', function(Alter $table)
        {
            $table->addColumn('overwrite', 'tinyint', 3)->nullable()->setDefault(0);
        });
    }

    public function installStep10()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_af_as_award', function(Alter $table)
        {
            $table->addColumn('user_criteria', 'mediumblob');
        });

        $sm->alterTable('xf_af_as_user_award', function(Alter $table)
        {
            $table->addColumn('given_by_user_id', 'int');
        });
    }

    // upgrade
    public function upgrade1000670Step1()
    {
        $this->installStep4();
    }

    public function upgrade1000770Step1()
    {
        $this->installStep5();
    }

    public function upgrade1010070Step1()
    {
        $this->installStep6();
    }

    public function upgrade1010071Step1()
    {
        $this->installStep7();
    }

    public function upgrade1020070Step1()
    {
        $this->installStep8();
    }

    public function upgrade1030072Step1()
    {
        $this->installStep9();
    }

    public function upgrade1040070Step1()
    {
        $this->installStep10();
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if ($previousVersion && $previousVersion < 1000670)
        {
            $this->app->jobManager()->enqueueUnique(
                'afasUserAwardTotalRebuild',
                'AddonFlare\AwardSystem:UserAwardTotalRebuild'
            );
        }

        if ($previousVersion && $previousVersion < 1020070)
        {
            // cache system is new in this version, so rebuild it
            \XF::repository('AddonFlare\AwardSystem:Award')->rebuildAwardCache();

            // auto feature for users with featureable awards that have never featured
            $this->app->jobManager()->enqueueUnique(
                'afasAutoFeatureRebuild',
                'AddonFlare\AwardSystem:AutoFeatureRebuild'
            );
        }

        if ($previousVersion && $previousVersion < 1040070)
        {
            $awards = \XF::finder('AddonFlare\AwardSystem:Award')->fetch();

            // copy user criteria from linked trophies
            // from now on we use our own user_criteria column
            foreach ($awards as $award)
            {
                if (!$trophyId = $award->award_trophy_id)
                {
                    continue;
                }

                if ($trophy = \XF::em()->find('XF:Trophy', $trophyId))
                {
                    $award->user_criteria = $trophy->user_criteria;
                    $award->award_trophy_id = 0; // don't need a value anymore
                    $award->save();
                }
            }
        }
    }

	//uninstall
	public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        $sm->dropTable('xf_af_as_award');
        $sm->dropTable('xf_af_as_award_category');
        $sm->dropTable('xf_af_as_user_award');

        \XF\Util\File::deleteAbstractedDirectory('data://addonflare/awardsystem');
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $alter)
        {
            $alter->dropColumns([
                'af_as_award_total',
                'af_as_award_points',
            ]);
        });

        $sm->alterTable('xf_user_privacy', function(Alter $alter)
        {
            $alter->dropColumns(['af_as_allow_view_profile']);
        });

        $sm->alterTable('xf_user_option', function(Alter $alter)
        {
            $alter->dropColumns(['af_as_auto_feature']);
        });
    }
}