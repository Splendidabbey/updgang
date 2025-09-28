<?php

namespace BS\LiveForumStatistics;

use BS\LiveForumStatistics\Entity\Tab;
use BS\LiveForumStatistics\Install\AbstractSetup;
use BS\LiveForumStatistics\Install\Upgrade1000771;
use BS\LiveForumStatistics\Tab\XenAddons\AMSArticle;
use BS\LiveForumStatistics\Tab\LatestActivity;
use BS\LiveForumStatistics\Tab\Members;
use BS\LiveForumStatistics\Tab\Tag;
use BS\LiveForumStatistics\Tab\Threads;
use BS\LiveForumStatistics\Tab\XenAddons\ShowcaseItem;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;
use XF\Entity\Widget;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	use Upgrade1000771;

	const GROUP_MEMBERS = 'members';
	const GROUP_THREADS = 'threads';
	const GROUP_LATEST_ACTIVITY = 'latest_activity';

	const DEFINITION_MEMBERS = 'members';
	const DEFINITION_THREADS = 'threads';
	const DEFINITION_LATEST_ACTIVITY = 'latest_activity';
	const DEFINITION_TAG = 'tag';

    const DEFINITION_AMS_ARTICLE = 'ams_article';
    const DEFINITION_SHOWCASE_ITEM = 'showcase_item';

    // ############################## INSTALL ##############################

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() AS $tableName => $closure)
        {
            $sm->createTable($tableName, $closure);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() AS $tableName => $closure)
        {
            $sm->alterTable($tableName, $closure[0]);
        }
    }

    public function installStep3()
    {
        $db = $this->db();

        $db->insert('xf_purchasable', [
            'purchasable_type_id' => 'lfs_sticked_link_purchase',
            'purchasable_class' => 'BS\LiveForumStatistics:StickedLinkPurchase',
            'addon_id' => 'BS/LiveForumStatistics'
        ]);

        $db->insert('xf_purchasable', [
            'purchasable_type_id' => 'lfs_sticked_thread',
            'purchasable_class' => 'BS\LiveForumStatistics:StickedThread',
            'addon_id' => 'BS/LiveForumStatistics'
        ]);
    }

    public function installStep4()
    {
        $this->createWidget('live_forum_statistics', 'live_forum_statistics', [
            'positions' => [
                'lfs_above_body_content' => 10
            ],
            'options' => [
                'show_groups' => [self::GROUP_MEMBERS, self::GROUP_THREADS],
                'show_tabs'   => $this->getShowTabsForGroups([self::GROUP_MEMBERS, self::GROUP_THREADS])
            ]
        ]);

        $this->createWidget('lfs_latest_activity', 'live_forum_statistics', [
            'positions' => [
                'forum_list_sidebar' => 25
            ],
            'options' => [
                'show_groups' => [self::GROUP_LATEST_ACTIVITY],
                'show_tabs'   => $this->getShowTabsForGroups(self::GROUP_LATEST_ACTIVITY)
            ]
        ]);
    }

    public function installStep5()
    {
        $this->importLfsDefinitions();
    }

    public function installStep6()
    {
        $this->importLfsGroups();
    }

    public function installStep7()
    {
        $this->importLfsTabs();
    }

    // ############################ UPGRADE 1.0.1 ############################

    public function upgrade1000170Step1()
    {
        $this->schemaManager()->alterTable('xf_user_option', function (Alter $table)
        {
            $table->addColumn('bs_lfs_disable', 'tinyint', 3)->setDefault(0);
        });
    }

    // ############################ UPGRADE 1.0.2 ############################

    public function upgrade1000270Step1()
    {
        $this->createLfsDefinition(self::DEFINITION_LATEST_ACTIVITY, LatestActivity::class);
    }

    public function upgrade1000270Step2()
    {
        $this->createLfsGroup(self::GROUP_LATEST_ACTIVITY, 10);
    }

    public function upgrade1000270Step3()
    {
        $this->createLfsTab(
            'latest_activity',
            self::GROUP_LATEST_ACTIVITY,
            self::DEFINITION_LATEST_ACTIVITY,
            [
                'limit' => 15
            ],
            10
        );

        $this->createLfsTab(
            'la_latest_posts',
            self::GROUP_LATEST_ACTIVITY,
            self::DEFINITION_THREADS,
            [
                'order' => ['last_post_date', 'desc'],
                'limit' => 15
            ],
            20
        );
    }

    public function upgrade1000270Step4()
    {
        $this->createWidget('lfs_latest_activity', 'live_forum_statistics', [
            'positions' => [
                'forum_list_sidebar' => 25
            ],
            'options' => [
                'exclude_groups' => [self::GROUP_MEMBERS, self::GROUP_THREADS]
            ]
        ]);
    }

    public function upgrade1000270Step5()
    {
        /** @var Widget $widget */
        $widget = $this->app->em()->findOne('XF:Widget', ['widget_key' => 'live_forum_statistics']);
        if ($widget)
        {
            $options = $widget->options;
            $options['exclude_groups'] = [self::GROUP_LATEST_ACTIVITY];

            $widget->options = $options;
            $widget->save();
        }
    }

    // ############################ UPGRADE 1.0.3 ############################

    public function upgrade1000370Step1()
    {
        $threadTabs = $this->app->finder('BS\LiveForumStatistics:Tab')
            ->where('definition_id', '=', self::DEFINITION_THREADS)
            ->fetch();

        /** @var Tab $tab */
        foreach ($threadTabs as $tab)
        {
            $options = $tab->options;
            $options['order'] = [$options['order']];

            $tab->fastUpdate('options', $options);
        }
    }

    public function upgrade1000370Step2()
    {
        $this->executeUpgradeQuery('
            UPDATE xf_bs_lfs_tab
            SET display_order = display_order + 10
            WHERE group_id = ?
              AND definition_id = ?
              AND display_order >= 30
        ', [self::GROUP_THREADS, self::DEFINITION_THREADS]);
    }

    public function upgrade1000370Step3()
    {
        $this->createLfsTab(
            'hottest_threads',
            self::GROUP_THREADS,
            self::DEFINITION_THREADS,
            [
                'order' => [
                    ['reply_count', 'desc'],
                    ['view_count', 'desc'],
                    ['first_post_reaction_score', 'desc']
                ],
                'cut_off' => ['<', 7],
                'limit' => 15
            ],
            30
        );
    }

    public function upgrade1000370Step4()
    {
        $this->createLfsTab(
            'la_new_threads',
            self::GROUP_LATEST_ACTIVITY,
            self::DEFINITION_THREADS,
            [
                'order' => [['post_date', 'desc']],
                'limit' => 15
            ],
            30
        );
    }

    // ############################ UPGRADE 1.0.4 ############################

    public function upgrade1000470Step1()
    {
        $threadTabs = $this->app->finder('BS\LiveForumStatistics:Tab')
            ->where('definition_id', '=', self::DEFINITION_THREADS)
            ->fetch();

        /** @var Tab $tab */
        foreach ($threadTabs as $tab)
        {
            $options = $tab->options;
            if (isset($options['only_open']))
            {
                $options['discussion_open'] = 1;
                unset($options['only_open']);
            }

            $tab->fastUpdate('options', $options);
        }
    }

    // ############################ UPGRADE 1.0.5 ############################

    public function upgrade1000570Step1()
    {
        $this->schemaManager()->alterTable('xf_bs_lfs_tab_group', function (Alter $table)
        {
            $table->addColumn('carousel_interval', 'int')->setDefault(0);
        });
    }

    // ############################ UPGRADE 1.2.1 ############################

    public function upgrade1000670Step1()
    {
        $this->createLfsDefinition(self::DEFINITION_TAG, Tag::class);
    }

    // ############################ UPGRADE 1.2.1 ############################

    public function upgrade1000671Step1()
    {
        $widgets = $this->app->finder('XF:Widget')
            ->where('definition_id', 'live_forum_statistics')
            ->fetch();

        $tabGroups = $this->getTabGroupRepo()
            ->findActiveGroupsForList()
            ->fetch();

        $showGroups = $tabGroups->keys();
        $showTabs = [];

        foreach ($tabGroups as $group)
        {
            $showTabs = array_merge($showTabs, $group->Tabs_->keys());
        }

        /** @var \XF\Entity\Widget $widget */
        foreach ($widgets as $widget)
        {
            $options = $widget->options;
            $options['show_groups'] = array_diff($showGroups, $options['exclude_groups'] ?? []);
            $options['show_tabs'] = array_diff($showTabs, $options['exclude_tabs'] ?? []);

            unset($options['exclude_groups'], $options['exclude_tabs']);

            $widget->options = $options;
            $widget->save();
        }
    }

    // ############################ UPGRADE 1.0.7 ############################

    public function upgrade1000770Step1()
    {
        $this->createLfsDefinition(self::DEFINITION_AMS_ARTICLE, AMSArticle::class);
    }

    public function upgrade1000770Step2()
    {
        $this->schemaManager()->alterTable('xf_bs_lfs_tab', function (Alter $table)
        {
            $table->addColumn('link', 'text')->nullable();
        });
    }

    // ############################ UPGRADE 1.0.7a ############################

    public function upgrade1000771Step1(array $stepParams)
    {
        $position = empty($stepParams[0]) ? 0 : $stepParams[0];

        return $this->rebuildUserForumIgnored($position, $stepParams);
    }

    // ############################ UPGRADE 1.0.9 ############################

    public function upgrade1000970Step1()
    {
        $this->createLfsDefinition(self::DEFINITION_SHOWCASE_ITEM, ShowcaseItem::class);
    }

    public function upgrade1000970Step2()
    {
        /** @var \BS\LiveForumStatistics\Entity\TabDefinition $articleDefinition */
        $articleDefinition = $this->app->em()->find('BS\LiveForumStatistics:TabDefinition', self::DEFINITION_AMS_ARTICLE);
        if ($articleDefinition)
        {
            $articleDefinition->definition_class = AMSArticle::class;
            $articleDefinition->save();
        }
        else
        {
            $this->createLfsDefinition(self::DEFINITION_AMS_ARTICLE, AMSArticle::class);
        }
    }

    // ############################## UNINSTALL ##############################

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) AS $tableName)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() AS $tableName => $closure)
        {
            $sm->alterTable($tableName, $closure[1]);
        }
    }

    public function uninstallStep3()
    {
        $this->db()->delete('xf_purchasable', 'addon_id = \'BS/LiveForumStatistics\'');
    }

    // ####################### TABLE / DATA DEFINITIONS #######################

    protected function getTables()
    {
        $tables = [];

        $tables['xf_bs_lfs_tab_group'] = function(Create $table)
        {
            $table->addColumn('group_id', 'varbinary', 25)->primaryKey();
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('addon_id', 'varbinary', 50);
            $table->addColumn('is_active', 'tinyint', 3);
            $table->addColumn('carousel_interval', 'int')->setDefault(0);
            $table->addKey('display_order');
            $table->addKey('is_active');
            $table->addKey('addon_id');
        };

        $tables['xf_bs_lfs_tab'] = function(Create $table)
        {
            $table->addColumn('tab_id', 'varbinary', 25)->primaryKey();
            $table->addColumn('group_id', 'varbinary', 25);
            $table->addColumn('definition_id', 'varbinary', 25);
            $table->addColumn('is_active', 'tinyint', 3);
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('options', 'mediumblob')->nullable();
            $table->addColumn('link', 'text')->nullable();
            $table->addColumn('addon_id', 'varbinary', 50);
            $table->addKey('group_id');
            $table->addKey('definition_id');
            $table->addKey('is_active');
            $table->addKey('display_order');
            $table->addKey('addon_id');
        };

        $tables['xf_bs_lfs_tab_definition'] = function(Create $table)
        {
            $table->addColumn('definition_id', 'varbinary', 25)->primaryKey();
            $table->addColumn('definition_class', 'varchar', 300);
            $table->addColumn('addon_id', 'varbinary', 50);
            $table->addKey('addon_id');
        };

        $tables['xf_bs_lfs_user_thread_ignored'] = function (Create $table)
        {
            $table->addColumn('thread_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addPrimaryKey(['thread_id', 'user_id']);
            $table->addKey('thread_id');
        };

        $tables['xf_bs_lfs_user_forum_ignored'] = function (Create $table)
        {
            $table->addColumn('node_id', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('tab_id', 'varbinary', 25);
            $table->addPrimaryKey(['node_id', 'user_id', 'tab_id']);
            $table->addKey('node_id');
            $table->addKey('user_id');
        };

        $tables['xf_bs_lfs_sticked_link'] = function(Create $table)
        {
            $table->addColumn('link_id', 'int')->autoIncrement();
            $table->addColumn('sticked_order', 'int')->setDefault(0);
            $table->addColumn('title', 'varchar', 500);
            $table->addColumn('link', 'text');
            $table->addColumn('attributes', 'mediumtext');
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('is_active', 'tinyint', 3)->setDefault(1);
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addKey('sticked_order');
            $table->addKey('end_date');
            $table->addKey('is_active');
        };

        $tables['xf_bs_lfs_sticked_attribute'] = function(Create $table)
        {
            $table->addColumn('attribute_id', 'int')->autoIncrement();
            $table->addColumn('attribute_key', 'varchar', 30);
            $table->addColumn('cost_amount', 'decimal', '10,2')->setDefault(0);
            $table->addColumn('allowable', 'mediumtext');
            $table->addColumn('type', 'enum', ['style', 'another']);
        };

        $tables['xf_bs_lfs_sticked_link_purchase'] = function(Create $table)
        {
            $table->addColumn('purchase_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 500);
            $table->addColumn('link', 'text');
            $table->addColumn('attributes', 'mediumtext')->nullable();
            $table->addColumn('purchase_date', 'int')->setDefault(0);
            $table->addColumn('number_of_days', 'int')->setDefault(0);
            $table->addColumn('paid_date', 'int')->setDefault(0);
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('link_id', 'int')->setDefault(0);
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('cost_amount', 'decimal', '10,2')->setDefault(0);
            $table->addColumn('cost_currency', 'varchar', 3);
            $table->addColumn('status', 'enum', ['moderated', 'rejected', 'awaiting_payment', 'paid']);
            $table->addColumn('purchase_request_key', 'varbinary', 32)->nullable();
            $table->addKey('purchase_date');
            $table->addKey('status');
            $table->addKey('user_id');
            $table->addKey('link_id');
        };

        $tables['xf_bs_lfs_sticked_thread_purchase'] = function(Create $table)
        {
            $table->addColumn('purchase_id', 'int')->autoIncrement();
            $table->addColumn('thread_id', 'int');
            $table->addColumn('cost_amount', 'decimal', '10,2')->setDefault(0);
            $table->addColumn('cost_currency', 'varchar', 3);
            $table->addColumn('attributes', 'mediumtext')->nullable();
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('create_date', 'int')->setDefault(0);
            $table->addColumn('is_payed', 'tinyint', 3)->setDefault(0);
            $table->addColumn('purchase_request_key', 'varbinary', 32)->nullable();
            $table->addKey('create_date');
            $table->addKey('is_payed');
        };

        return $tables;
    }

    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_thread'] =
        [
            function (Alter $table)
            {
                $table->addColumn('bs_lfs_is_sticked', 'tinyint', 3)->setDefault(0);
                $table->addColumn('bs_lfs_sticked_order', 'int')->setDefault(0);
                $table->addColumn('bs_lfs_sticked_end_date', 'int')->setDefault(0);
                $table->addColumn('bs_lfs_sticked_attributes', 'mediumtext')->nullable();
                $table->addKey('bs_lfs_sticked_order');
                $table->addKey('bs_lfs_is_sticked');
                $table->addKey('bs_lfs_sticked_end_date');
            },
            function (Alter $table)
            {
                $table->dropColumns(['bs_lfs_is_sticked', 'bs_lfs_sticked_order', 'bs_lfs_sticked_end_date', 'bs_lfs_sticked_attributes']);
            }
        ];

        $tables['xf_user_profile'] =
        [
            function (Alter $table)
            {
                $table->addColumn('bs_lfs_ignored_threads', 'text')->nullable();
                $table->addColumn('bs_lfs_ignored_forums', 'text')->nullable();
            },
            function (Alter $table)
            {
                $table->dropColumns(['bs_lfs_ignored_threads', 'bs_lfs_ignored_forums']);
            }
        ];

        $tables['xf_user_option'] =
        [
            function (Alter $table)
            {
                $table->addColumn('bs_lfs_disable', 'tinyint', 3)->setDefault(0);
            },
            function (Alter $table)
            {
                $table->dropColumns('bs_lfs_disable');
            }
        ];

        return $tables;
    }

    protected function getLfsDefinitions() : array
    {
        return [
            self::DEFINITION_MEMBERS => Members::class,
            self::DEFINITION_THREADS => Threads::class,

            self::DEFINITION_LATEST_ACTIVITY => LatestActivity::class,

            self::DEFINITION_AMS_ARTICLE   => AMSArticle::class,
            self::DEFINITION_SHOWCASE_ITEM => ShowcaseItem::class,

            self::DEFINITION_TAG => Tag::class
        ];
    }

    protected function getLfsGroups() : array
    {
        return [
            self::GROUP_MEMBERS => 10,
            self::GROUP_THREADS => 20,

            self::GROUP_LATEST_ACTIVITY => 10
        ];
    }

    protected function getLfsTabs() : array
    {
        return [
            self::GROUP_MEMBERS => [
                '_Definition' => self::DEFINITION_MEMBERS,

                'new_members' => [
                    'display_order' => 10,
                    'options' => [
                        'order' => ['register_date', 'desc'],
                        'limit' => 15
                    ]
                ],
                'most_messages' => [
                    'display_order' => 20,
                    'options' => [
                        'order' => ['message_count', 'desc'],
                        'limit' => 15
                    ]
                ],
                'most_reactions' => [
                    'display_order' => 30,
                    'options' => [
                        'order' => ['reaction_score', 'desc'],
                        'limit' => 15
                    ]
                ],
                'most_trophies' => [
                    'display_order' => 40,
                    'options' => [
                        'order' => ['trophy_points', 'desc'],
                        'limit' => 15
                    ]
                ]
            ],

            self::GROUP_THREADS => [
                '_Definition' => self::DEFINITION_THREADS,

                'latest_posts' => [
                    'display_order' => 10,
                    'options' => [
                        'order' => [['last_post_date', 'desc']],
                        'limit' => 15
                    ]
                ],
                'new_threads' => [
                    'display_order' => 20,
                    'options' =>  [
                        'order' => [['post_date', 'desc']],
                        'limit' => 15
                    ]
                ],
                'hottest_threads' => [
                    'display_order' => 30,
                    'options' =>  [
                        'order' => [
                            ['reply_count', 'desc'],
                            ['view_count', 'desc'],
                            ['first_post_reaction_score', 'desc']
                        ],
                        'cut_off' => ['<', 7],
                        'limit' => 15
                    ]
                ],
                'most_viewable' => [
                    'display_order' => 40,
                    'options' =>  [
                        'order' => [['view_count', 'desc']],
                        'limit' => 15
                    ]
                ],
                'my_threads' => [
                    'display_order' => 50,
                    'options' => [
                        'order' => [['post_date', 'desc']],
                        'limit' => 15,
                        'by_user' => '{visitor}'
                    ]
                ]
            ],

            self::GROUP_LATEST_ACTIVITY => [
                '_Definition' => null,

                'latest_activity' => [
                    'definition' => self::DEFINITION_LATEST_ACTIVITY,
                    'display_order' => 10,
                    'options' => [
                        'limit' => 15
                    ]
                ],
                'la_latest_posts' => [
                    'definition' => self::DEFINITION_THREADS,
                    'display_order' => 20,
                    'options' => [
                        'order' => ['last_post_date', 'desc'],
                        'limit' => 15
                    ]
                ],
                'la_new_threads' => [
                    'definition' => self::DEFINITION_THREADS,
                    'display_order' => 30,
                    'options' => [
                        'order' => ['post_date', 'desc'],
                        'limit' => 15
                    ]
                ],
            ]
        ];
    }

    /**
     * @return \BS\LiveForumStatistics\Repository\TabGroup
     */
    protected function getTabGroupRepo()
    {
        return $this->app->repository('BS\LiveForumStatistics:TabGroup');
    }
}