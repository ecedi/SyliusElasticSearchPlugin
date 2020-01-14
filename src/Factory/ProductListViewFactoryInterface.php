<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Factory;

use ONGR\FilterManagerBundle\Search\SearchResponse;
use Sylius\ElasticSearchPlugin\Controller\ProductListView;

interface ProductListViewFactoryInterface
{
    /**
     * @param SearchResponse $response
     *
     * @return ProductListView
     */
    public function createFromSearchResponse(SearchResponse $response): ProductListView;
}
