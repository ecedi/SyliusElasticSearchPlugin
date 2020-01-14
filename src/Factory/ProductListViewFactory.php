<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Factory;

use ONGR\ElasticsearchBundle\Collection\Collection;
use ONGR\FilterManagerBundle\Search\SearchResponse;
use Sylius\ElasticSearchPlugin\Controller\ImageView;
use Sylius\ElasticSearchPlugin\Controller\PriceView;
use Sylius\ElasticSearchPlugin\Controller\AttributeView;
use Sylius\ElasticSearchPlugin\Controller\ProductView;
use Sylius\ElasticSearchPlugin\Controller\ProductListView;
use Sylius\ElasticSearchPlugin\Controller\VariantView;
use Sylius\ElasticSearchPlugin\Controller\TaxonView;
use Sylius\ElasticSearchPlugin\Document\AttributeDocument;
use Sylius\ElasticSearchPlugin\Document\ImageDocument;
use Sylius\ElasticSearchPlugin\Document\PriceDocument;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Document\ProductTaxonDocument;
use Sylius\ElasticSearchPlugin\Document\TaxonDocument;

final class ProductListViewFactory implements ProductListViewFactoryInterface
{
    /** @var string */
    private $productListViewClass;

    /** @var string */
    private $productViewClass;

    /** @var string */
    private $productVariantViewClass;

    /** @var string */
    private $attributeViewClass;

    /** @var string */
    private $imageViewClass;

    /** @var string */
    private $priceViewClass;

    /** @var string */
    private $taxonViewClass;

    public function __construct(
        string $productListViewClass,
        string $productViewClass,
        string $productVariantViewClass,
        string $attributeViewClass,
        string $imageViewClass,
        string $priceViewClass,
        string $taxonViewClass
    ) {
        $this->productListViewClass = $productListViewClass;
        $this->productViewClass = $productViewClass;
        $this->productVariantViewClass = $productVariantViewClass;
        $this->attributeViewClass = $attributeViewClass;
        $this->imageViewClass = $imageViewClass;
        $this->priceViewClass = $priceViewClass;
        $this->taxonViewClass = $taxonViewClass;
    }

    /**
     * {@inheritdoc}
     */
    public function createFromSearchResponse(SearchResponse $response): ProductListView
    {
        $result = $response->getResult();
        $filters = $response->getFilters();

        /** @var ProductListView $productListView */
        $productListView = new $this->productListViewClass();
        $productListView->filters = $filters;

        $pager = $filters['paginator']->getSerializableData()['pager'];
        $productListView->page = $pager['current_page'];
        $productListView->total = $pager['total_items'];
        $productListView->pages = $pager['num_pages'];
        $productListView->limit = $pager['limit'];

        /** @var ProductDocument $product */
        foreach ($result as $product) {
            $productListView->items[] = $this->getProductView($product);
        }

        return $productListView;
    }

    /**
     * @param Collection|ImageDocument[] $images
     *
     * @return ImageView[]
     */
    private function getImageViews(Collection $images): array
    {
        $imageViews = [];
        foreach ($images as $image) {
            /** @var ImageView $imageView */
            $imageView = new $this->imageViewClass();
            $imageView->code = $image->getCode();
            $imageView->path = $image->getPath();

            $imageViews[] = $imageView;
        }

        return $imageViews;
    }

    /**
     * @param Collection|TaxonDocument[] $taxons
     * @param TaxonDocument|null $mainTaxonDocument
     *
     * @return TaxonView
     */
    private function getTaxonView(Collection $taxons, ?TaxonDocument $mainTaxonDocument): TaxonView
    {
        /** @var TaxonView $taxonView */
        $taxonView = new $this->taxonViewClass();

        $taxonView->main = null === $mainTaxonDocument ? null : $mainTaxonDocument->getCode();
        foreach ($taxons as $taxon) {
            $taxonView->others[] = $taxon->getCode();
        }

        return $taxonView;
    }

    /**
     * @param Collection|AttributeDocument[] $attributes
     *
     * @return AttributeView[]
     */
    private function getAttributeViews(Collection $attributes): array
    {
        $attributeValueViews = [];
        foreach ($attributes as $attribute) {
            /** @var AttributeView $attributeView */
            $attributeView = new $this->attributeViewClass();
            $attributeView->code = $attribute->getCode();
            $attributeView->value = $attribute->getValue();
            $attributeView->name = $attribute->getName();

            $attributeValueViews[] = $attributeView;
        }

        return $attributeValueViews;
    }

    /**
     * @param PriceDocument $price
     *
     * @return PriceView
     */
    private function getPriceView(PriceDocument $price): PriceView
    {
        /** @var PriceView $priceView */
        $priceView = new $this->priceViewClass();
        $priceView->current = $price->getAmount();
        $priceView->currency = $price->getCurrency();

        return $priceView;
    }

    /**
     * @param ProductDocument $product
     *
     * @return VariantView
     */
    private function getVariantView(ProductDocument $product): VariantView
    {
        /** @var VariantView $variantView */
        $variantView = new $this->productVariantViewClass();
        $variantView->price = $this->getPriceView($product->getPrice());
        $variantView->code = $product->getCode();
        $variantView->name = $product->getName();
        $variantView->images = $this->getImageViews($product->getImages());

        return $variantView;
    }

    /**
     * @param ProductDocument $product
     *
     * @return ProductView
     */
    private function getProductView(ProductDocument $product): ProductView
    {
        /** @var ProductView $productView */
        $productView = new $this->productViewClass();
        $productView->slug = $product->getSlug();
        $productView->name = $product->getName();
        $productView->code = $product->getCode();
        $productView->localeCode = $product->getLocaleCode();
        $productView->channelCode = $product->getChannelCode();
        $productView->images = $this->getImageViews($product->getImages());
        $productView->taxons = $this->getTaxonView($product->getTaxons(), $product->getMainTaxon());
        $productView->attributes = $this->getAttributeViews($product->getAttributes());
        $productView->variants = [$product->getCode() => $this->getVariantView($product)];

        return $productView;
    }
}
