<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Filter\Widget;

use ONGR\FilterManagerBundle\Filter\Widget\Dynamic\MultiDynamicAggregate;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\FilterManagerBundle\Filter\FilterState;

/**
 * Class MultiDynamicAggregateOverride implements the size option handling in the preProcessSearch method to be able
 * to set the amount of aggregation buckets returned, otherwise Elastic returns only 10 by default and there is not way
 * to change that globally - only via the query options for the aggregation object.
 * All other multi dynamic aggregates are extending from this class in the plugin. Size option is implemented in other
 * types of filters by the FilterManagerBundle
 *
 * @package Sylius\ElasticSearchPlugin\Filter\Widget
 */
class MultiDynamicAggregateOverride extends MultiDynamicAggregate
{
    /**
     * {@inheritdoc}
     */
    public function preProcessSearch(Search $search, Search $relatedSearch, FilterState $state = null)
    {
        list($path, $field) = explode('>', $this->getDocumentField());
        $filter = !empty($filter = $relatedSearch->getPostFilters()) ? $filter : new MatchAllQuery();
        $aggregation = new NestedAggregation($state->getName(), $path);
        $nameAggregation = new TermsAggregation('name', $this->getNameField());
        $valueAggregation = new TermsAggregation('value', $field);
        $filterAggregation = new FilterAggregation($state->getName() . '-filter');
        $nameAggregation->addAggregation($valueAggregation);
        $aggregation->addAggregation($nameAggregation);
        $filterAggregation->setFilter($filter);

        if ($this->hasOption('names_sort_type')) {
            $valueAggregation->addParameter('order', [$this->getOption('names_sort_type') => $this->getOption('names_sort_order')]);
        }

        if ($this->hasOption('names_size')) {
            $nameAggregation->addParameter('size', $this->getOption('names_size'));
        }

        if ($this->hasOption('values_sort_type')) {
            $valueAggregation->addParameter('order', [$this->getOption('values_sort_type') => $this->getOption('values_sort_order')]);
        }

        if ($this->getOption('values_size')) {
            $valueAggregation->addParameter('size', $this->getOption('values_size'));
        }

        if ($state->isActive()) {
            foreach ($state->getValue() as $key => $term) {
                $terms = $state->getValue();
                unset($terms[$key]);

                $this->addSubFilterAggregation(
                    $filterAggregation,
                    $aggregation,
                    $terms,
                    $key
                );
            }
        }

        $this->addSubFilterAggregation(
            $filterAggregation,
            $aggregation,
            $state->getValue() ? $state->getValue() : [],
            'all-selected'
        );

        $search->addAggregation($filterAggregation);

        if ($this->getShowZeroChoices()) {
            $search->addAggregation($aggregation);
        }
    }
}
