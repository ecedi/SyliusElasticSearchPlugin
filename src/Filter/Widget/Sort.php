<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Filter\Widget;

use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchBundle\Result\DocumentIterator;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use ONGR\FilterManagerBundle\Filter\FilterState;
use ONGR\FilterManagerBundle\Filter\Helper\ViewDataFactoryInterface;
use ONGR\FilterManagerBundle\Filter\ViewData;
use ONGR\FilterManagerBundle\Filter\Widget\AbstractFilter;
use ONGR\FilterManagerBundle\Search\SearchRequest;
use Sylius\ElasticSearchPlugin\Filter\ViewData\EmptyViewData;
use Symfony\Component\HttpFoundation\Request;

final class Sort extends AbstractFilter implements ViewDataFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getState(Request $request): FilterState
    {
        $state = new FilterState();
        $value = $request->get($this->getRequestField());

        if (null !== $value && '' !== $value) {
            $state->setActive(true);
            $state->setValue($value);
            $state->setUrlParameters([$this->getRequestField() => $value]);
        }

        return $state;
    }

    /**
     * {@inheritdoc}
     */
    public function modifySearch(Search $search, ?FilterState $state = null, ?SearchRequest $request = null)
    {
        if ($state && $state->isActive()) {
            $stateValue = $state->getValue();

            if (!is_array($stateValue)) {
                return;
            }

            $aliases = $this->getOption('aliases') ?? [];

            foreach ($stateValue as $field => $data) {
                if ('attributes' === $field) {
                    $this->addAttributeFieldToSort($search, $data);

                    continue;
                }

                if ('taxonPositionByCode' === $field) {
                    $this->addPositionFieldToSort($search, 'code', $data);

                    continue;
                }

                if ('taxonPositionBySlug' === $field) {
                    $this->addPositionFieldToSort($search, 'slug', $data);

                    continue;
                }

                if (array_key_exists($field, $aliases)) {
                    $this->addRegularFieldToSort($search, $aliases[$field], $data);

                    continue;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preProcessSearch(Search $search, Search $relatedSearch, ?FilterState $state = null)
    {
        // Nothing to do here.
    }

    /**
     * {@inheritdoc}
     */
    public function createViewData(): EmptyViewData
    {
        return new EmptyViewData();
    }

    /**
     * {@inheritdoc}
     */
    public function getViewData(DocumentIterator $result, ViewData $data): ViewData
    {
        return $data;
    }

    private function addAttributeFieldToSort(Search $search, array $settings): void
    {
        foreach ($settings as $attributeCode => $sortingOrder) {
            $fieldSort = new FieldSort('attributes.value.raw', $sortingOrder, ['nested_path' => 'attributes', 'mode' => 'max']);
            $fieldSort->setNestedFilter(new TermQuery('attributes.code', $attributeCode));

            $search->addSort($fieldSort);
        }
    }

    private function addPositionFieldToSort(Search $search, string $identifier, array $settings): void
    {
        foreach ($settings as $taxonIdentifier => $sortingOrder) {
            $fieldSort = new FieldSort('product_taxons.position', $sortingOrder, ['nested_path' => 'product_taxons']);
            $fieldSort->setNestedFilter(new TermQuery(sprintf('product_taxons.%s', $identifier), $taxonIdentifier));

            $search->addSort($fieldSort);
        }
    }

    private function addRegularFieldToSort(Search $search, string $field, string $order, array $options = []): void
    {
        $search->addSort(new FieldSort($field, $order, $options));
    }
}
