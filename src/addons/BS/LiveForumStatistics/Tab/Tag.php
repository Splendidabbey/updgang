<?php

namespace BS\LiveForumStatistics\Tab;

use XF\Search\Query\MetadataConstraint;

class Tag extends AbstractTab
{
    protected $defaultOptions = [
        'tags' => '',
        'tag_ids' => [],
        'match' => 'any',
        'exclude_content_types' => [],
        'limit'  => 15
    ];

    public function canView()
    {
        return $this->app->options()->enableTagging;
    }

    public function render($finalRender = true, \XF\Http\Request $request = null)
    {
        $options = $this->options;

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();

        $query->withTags($options['tag_ids'], $options['match']);
        $constraints = [
            'tag' => implode(' ', $options['tag_ids'])
        ];

        $this->applyOptions($query, $options, $constraints);

        $resultSet = $searcher->getResultSet($searcher->search($query));
        $results = $searcher->wrapResultsForRender($resultSet->limitResults($options['limit'], false));

        return $this->finalRender($this->getTemplateName(), compact('results'), $finalRender);
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $tagRepo = $this->getTagRepo();

        $tags = $tagRepo->splitTagList($options['tags']);

        $validTags = $tagRepo->getTags($tags, $notFound);
        if ($notFound)
        {
            $error = \XF::phrase(
                'following_tags_not_found_x',
                ['tags' => implode(', ', $notFound)]
            );

            return false;
        }

        if (empty($validTags))
        {
            $error = \XF::phrase('lfs_specify_at_least_one_tag');

            return false;
        }

        $options['tag_ids'] = array_keys($validTags);

        return true;
    }

    protected function applyOptions(\XF\Search\Query\Query $query, $options, array &$constraints)
    {
        $excludeContentTypes = array_values(array_filter($options['exclude_content_types']));

        if (! empty($excludeContentTypes))
        {
            $constraints['exclude_content'] = $excludeContentTypes;
            $query->withMetadata('content', $excludeContentTypes, MetadataConstraint::MATCH_NONE);

            // EXCLUDE FOR ELASTICSEARCH
            $query->withMetadata('type', $excludeContentTypes, MetadataConstraint::MATCH_NONE);
        }
    }

    public function getDefaultTemplate($preset = null)
    {
        return file_get_contents(__DIR__ . '/stubs/tag.stub');
    }

    /**
     * @return \XF\Repository\Tag
     */
    protected function getTagRepo()
    {
        return $this->repository('XF:Tag');
    }
}