<?php

namespace BS\LiveForumStatistics\Tab\XenAddons;

use BS\LiveForumStatistics\Tab\XenAddons\AbstractItem;

class AMSArticle extends AbstractItem
{
    protected $addOnName = 'AMS';

    protected $itemPrefix = 'article';

    protected $finderName = 'ArticleItem';

    protected $itemsVar = 'articles';

    protected $createDateColumn = 'publish_date';

    protected $defaultOptions = [
        'limit' => 15,
        'order' => [['last_update', 'desc']],

        'exclude_category_ids' => [],
        'prefix_ids' => [-1],
        'exclude_prefix_ids' => [],
        'comments_open' => '',
        'featured' => '',
        'cut_off' => ['>', 0],
        'not_article_ids' => [],
        'article_ids' => [],

        'by_user' => [],
        'user_is_not' => [],
        'user_has_groups' => [],
        'user_has_not_groups' => [],
        'language_ids' => [-1],
        'watched' => false
    ];

    public function getDefaultTemplate($preset = null)
    {
        return file_get_contents(__DIR__ . '/stubs/ams_articles.stub');
    }
}