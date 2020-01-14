<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Factory;

use ONGR\ElasticsearchBundle\Collection\Collection;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeTranslationInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Resource\Model\TranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Sylius\ElasticSearchPlugin\Document\AttributeDocument;
use Sylius\ElasticSearchPlugin\Document\ImageDocument;
use Sylius\ElasticSearchPlugin\Document\OptionDocument;
use Sylius\ElasticSearchPlugin\Document\PriceDocument;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Document\ProductTaxonDocument;
use Sylius\ElasticSearchPlugin\Document\TaxonDocument;
use Sylius\ElasticSearchPlugin\Document\VariantDocument;

final class ProductDocumentFactory implements ProductDocumentFactoryInterface
{
    /** @var string */
    private $productDocumentClass;

    /** @var string */
    private $attributeDocumentClass;

    /** @var string */
    private $imageDocumentClass;

    /** @var string */
    private $priceDocumentClass;

    /** @var string */
    private $productTaxonDocumentClass;

    /** @var string */
    private $taxonDocumentClass;

    /** @var string */
    private $variantDocumentClass;

    /** @var string */
    private $optionDocumentClass;

    /** @var array */
    private $attributeWhitelist = [];

    /**
     * @param string $productDocumentClass
     * @param string $attributeDocumentClass
     * @param string $imageDocumentClass
     * @param string $priceDocumentClass
     * @param string $productTaxonDocumentClass
     * @param string $taxonDocumentClass
     * @param string $variantDocumentClass
     * @param string $optionDocumentClass
     * @param array $attributeWhitelist
     */
    public function __construct(
        string $productDocumentClass,
        string $attributeDocumentClass,
        string $imageDocumentClass,
        string $priceDocumentClass,
        string $productTaxonDocumentClass,
        string $taxonDocumentClass,
        string $variantDocumentClass,
        string $optionDocumentClass,
        array $attributeWhitelist
    ) {
        $this->assertClassExtends($productDocumentClass, ProductDocument::class);
        $this->productDocumentClass = $productDocumentClass;

        $this->assertClassExtends($attributeDocumentClass, AttributeDocument::class);
        $this->attributeDocumentClass = $attributeDocumentClass;

        $this->assertClassExtends($imageDocumentClass, ImageDocument::class);
        $this->imageDocumentClass = $imageDocumentClass;

        $this->assertClassExtends($priceDocumentClass, PriceDocument::class);
        $this->priceDocumentClass = $priceDocumentClass;

        $this->assertClassExtends($productTaxonDocumentClass, ProductTaxonDocument::class);
        $this->productTaxonDocumentClass = $productTaxonDocumentClass;

        $this->assertClassExtends($taxonDocumentClass, TaxonDocument::class);
        $this->taxonDocumentClass = $taxonDocumentClass;

        $this->assertClassExtends($variantDocumentClass, VariantDocument::class);
        $this->variantDocumentClass = $variantDocumentClass;

        $this->assertClassExtends($optionDocumentClass, OptionDocument::class);
        $this->optionDocumentClass = $optionDocumentClass;

        $this->attributeWhitelist = $attributeWhitelist;
    }

    /**
     * Create a product document from the product object with all it's related documents
     *
     * @param ProductInterface $product
     * @param LocaleInterface $locale
     * @param ChannelInterface $channel
     *
     * @return ProductDocument
     */
    public function createFromSyliusSimpleProductModel(
        ProductInterface $product,
        LocaleInterface $locale,
        ChannelInterface $channel
    ): ProductDocument {
        /** @var ProductVariantInterface[] $productVariants */
        $productVariants = $product->getVariants();

        $productVariantDocuments = [];
        foreach ($productVariants as $productVariant) {
            $productVariantDocuments[] = $this->createVariantDocumentFromSyliusVariant($productVariant, $channel, $locale);
        }

        $minProductChannelPrice = min(array_map(function (VariantDocument $document): int {
            return $document->getPrice()->getAmount();
        }, $productVariantDocuments));

        /** @var ProductTranslationInterface|TranslationInterface $productTranslation */
        $productTranslation = $product->getTranslation($locale->getCode());
        $productAttributesValues = $product->getAttributesByLocale(
            $locale->getCode(),
            $channel->getDefaultLocale()->getCode()
        );

        /** @var ProductDocument $productDocument */
        $productDocument = new $this->productDocumentClass();
        $productDocument->setId(Uuid::uuid4()->toString());
        $productDocument->setEnabled($product->isEnabled());
        $productDocument->setLocaleCode($locale->getCode());
        $productDocument->setSlug($productTranslation->getSlug());
        $productDocument->setName($productTranslation->getName());
        $productDocument->setDescription($productTranslation->getDescription());
        $productDocument->setChannelCode($channel->getCode());
        $productDocument->setCode($product->getCode());
        $productDocument->setCreatedAt($product->getCreatedAt());
        $productDocument->setSynchronisedAt(new \DateTime('now'));
        $productDocument->setAverageReviewRating($product->getAverageRating());
        $productDocument->setVariants(new Collection($productVariantDocuments));

        if (null !== $product->getMainTaxon()) {
            /** @var TaxonDocument $mainTaxonDocument */
            $mainTaxonDocument = new $this->taxonDocumentClass();
            $mainTaxonDocument->setCode($product->getMainTaxon()->getCode());

            /** @var TaxonTranslationInterface $mainTaxonTranslation */
            $mainTaxonTranslation = $product->getMainTaxon()->getTranslation($locale->getCode());

            $mainTaxonDocument->setCode($product->getMainTaxon()->getCode());
            $mainTaxonDocument->setSlug($mainTaxonTranslation->getSlug());
            $mainTaxonDocument->setDescription($mainTaxonTranslation->getDescription());
            $productDocument->setMainTaxon($mainTaxonDocument);
        }

        /** @var PriceDocument $priceDocument */
        $priceDocument = new $this->priceDocumentClass();
        $priceDocument->setAmount($minProductChannelPrice);
        $priceDocument->setCurrency($channel->getBaseCurrency()->getCode());
        $productDocument->setPrice($priceDocument);

        $imageDocuments = [];
        foreach ($product->getImages() as $productImage) {
            foreach ($productVariants as $productVariant) {
                if ($productVariant->hasImage($productImage)) {
                    continue 2;
                }
            }

            /** @var ImageDocument $imageDocument */
            $imageDocument = new $this->imageDocumentClass();
            $imageDocument->setPath($productImage->getPath());
            $imageDocument->setCode($productImage->getType());

            $imageDocuments[] = $imageDocument;
        }
        $productDocument->setImages(new Collection($imageDocuments));

        $taxonDocuments = $productTaxonDocuments = [];
        foreach ($product->getProductTaxons() as $productTaxon) {
            $taxonDocuments[$productTaxon->getTaxon()->getCode()] = $this->createTaxonDocument($productTaxon->getTaxon(), $locale->getCode());
            $productTaxonDocuments[$productTaxon->getTaxon()->getCode()] = $this->createProductTaxonDocument($productTaxon, $locale->getCode());

            /** @var TaxonInterface[] $productTaxonAncestors */
            $productTaxonAncestors = $productTaxon->getTaxon()->getAncestors();
            foreach ($productTaxonAncestors as $productTaxonAncestor) {
                $taxonDocuments[$productTaxonAncestor->getCode()] = $this->createTaxonDocument($productTaxonAncestor, $locale->getCode());
            }
        }
        $productDocument->setTaxons(new Collection(array_values($taxonDocuments)));
        $productDocument->setProductTaxons(new Collection(array_values($productTaxonDocuments)));

        $productAttributeDocuments = [];
        foreach ($productAttributesValues as $productAttributeValue) {
            if (in_array($productAttributeValue->getCode(), $this->attributeWhitelist, true)) {
                $productAttributeDocuments = array_merge(
                    $productAttributeDocuments,
                    $this->createAttributeDocumentFromSyliusProductAttributeValue(
                        $locale,
                        $productAttributeValue
                    )
                );
            }
        }
        $productDocument->setAttributes(new Collection($productAttributeDocuments));

        return $productDocument;
    }

    /**
     * Create a taxon document from a taxon object
     *
     * @param TaxonInterface $taxon
     * @param string $localeCode
     *
     * @return TaxonDocument
     */
    private function createTaxonDocument(TaxonInterface $taxon, ?string $localeCode = null): TaxonDocument
    {
        /** @var TaxonTranslationInterface $taxonTranslation */
        $taxonTranslation = $taxon->getTranslation($localeCode);

        /** @var TaxonDocument $taxonDocument */
        $taxonDocument = new $this->taxonDocumentClass();
        $taxonDocument->setCode($taxon->getCode());
        $taxonDocument->setSlug($taxonTranslation->getSlug());
        $taxonDocument->setPosition($taxon->getPosition());
        $taxonDocument->setDescription($taxonTranslation->getDescription());

        $taxonDocumentImages = [];
        $syliusTaxonImages = $taxon->getImages();
        foreach ($syliusTaxonImages as $syliusTaxonImage) {
            /** @var ImageDocument $taxonDocumentImage */
            $taxonDocumentImage = new $this->imageDocumentClass();
            $taxonDocumentImage->setPath($syliusTaxonImage->getPath());
            $taxonDocumentImage->setCode($syliusTaxonImage->getType());
            $taxonDocumentImages[] = $taxonDocumentImage;
        }
        $taxonDocument->setImages(new Collection($taxonDocumentImages));

        return $taxonDocument;
    }

    /**
     * Create variant document from a product variant with all it's related documents
     *
     * @param ProductVariantInterface $productVariant
     * @param ChannelInterface $channel
     * @param LocaleInterface $locale
     *
     * @return VariantDocument
     */
    private function createVariantDocumentFromSyliusVariant(
        ProductVariantInterface $productVariant,
        ChannelInterface $channel,
        LocaleInterface $locale
    ): VariantDocument {
        /** @var PriceDocument $price */
        $price = new $this->priceDocumentClass();
        $channelPricing = $this->getChannelPricingForChannelFromProductVariant($productVariant, $channel);
        $price->setAmount($channelPricing->getPrice());
        $price->setCurrency($channel->getBaseCurrency()->getCode());

        $images = [];
        foreach ($productVariant->getImages() as $image) {
            /** @var ImageDocument $productImage */
            $productImage = new $this->imageDocumentClass();
            $productImage->setPath($image->getPath());
            $productImage->setCode($image->getType());
            $images[] = $productImage;
        }

        $options = [];
        foreach ($productVariant->getOptionValues() as $optionValue) {
            $options[] = $this->createOptionDocumentFromSyliusOptionValue($optionValue, $locale);
        }

        /** @var VariantDocument $variant */
        $variant = new $this->variantDocumentClass();
        $variant->setCode($productVariant->getCode());
        $variant->setPrice($price);
        $variant->setStock($productVariant->getOnHand() - $productVariant->getOnHold());
        $variant->setIsTracked($productVariant->isTracked());
        $variant->setImages(new Collection($images));
        $variant->setOptions(new Collection($options));

        return $variant;
    }

    /**
     * @param ProductTaxonInterface $productTaxon
     * @param string $localeCode
     *
     * @return ProductTaxonDocument
     */
    private function createProductTaxonDocument(
        ProductTaxonInterface $productTaxon,
        $localeCode = null
    ): ProductTaxonDocument {
        /** @var TaxonInterface $taxon */
        $taxon = $productTaxon->getTaxon();

        /** @var TaxonTranslationInterface $taxonTranslation */
        $taxonTranslation = $taxon->getTranslation($localeCode);

        /** @var ProductTaxonDocument $productTaxonDocument */
        $productTaxonDocument = new $this->productTaxonDocumentClass();
        $productTaxonDocument->setPosition($productTaxon->getPosition());
        $productTaxonDocument->setCode($taxon->getCode());
        $productTaxonDocument->setSlug($taxonTranslation->getSlug());

        return $productTaxonDocument;
    }

    /**
     * Get price for the pricing document
     *
     * @param ProductVariantInterface $productVariant
     * @param ChannelInterface $channel
     *
     * @return ChannelPricingInterface|null
     */
    private function getChannelPricingForChannelFromProductVariant(
        ProductVariantInterface $productVariant,
        ChannelInterface $channel
    ): ?ChannelPricingInterface
    {
        $channelPricings = $productVariant->getChannelPricings();

        foreach ($channelPricings as $channelPricing) {
            if ($channelPricing->getChannelCode() === $channel->getCode()) {
                return $channelPricing;
            }
        }

        return null;
    }

    /**
     * @param string $class
     * @param string $parentClass
     *
     * @throws \InvalidArgumentException
     */
    private function assertClassExtends(string $class, string $parentClass)
    {
        if ($class !== $parentClass && !in_array($parentClass, class_parents($class), true)) {
            throw new \InvalidArgumentException(sprintf('Class %s MUST extend class %s!', $class, $parentClass));
        }
    }

    /**
     * Create an option document from an option value object
     *
     * @param ProductOptionValueInterface $optionValue
     * @param LocaleInterface $locale
     *
     * @return OptionDocument
     */
    private function createOptionDocumentFromSyliusOptionValue(
        ProductOptionValueInterface $optionValue,
        LocaleInterface $locale
    ): OptionDocument {
        /** @var OptionDocument $option */
        $option = new $this->optionDocumentClass();
        $option->setCode($optionValue->getOptionCode());
        $option->setName($optionValue->getOption()->getTranslation($locale->getCode())->getName());
        $option->setValue($optionValue->getTranslation($locale->getCode())->getValue());

        return $option;
    }

    /**
     * Creates an array of attribute document objects, handles the cases when attribute value is an array
     *
     * @param LocaleInterface $locale
     * @param ProductAttributeValueInterface $productAttributeValue
     *
     * @return AttributeDocument[]
     */
    private function createAttributeDocumentFromSyliusProductAttributeValue(
        LocaleInterface $locale,
        ProductAttributeValueInterface $productAttributeValue
    ): array {
        $productAttributes = [];

        if (is_array($productAttributeValue->getValue())) {
            foreach ($productAttributeValue->getValue() as $value) {
                $productAttributes[] = $this->createSingleAttributeDocumentFromSyliusProductAttributeValue(
                    $value,
                    $productAttributeValue,
                    $locale
                );
            }
        } else {
            $productAttributes[] = $this->createSingleAttributeDocumentFromSyliusProductAttributeValue(
                $productAttributeValue->getValue(),
                $productAttributeValue,
                $locale
            );
        }

        return $productAttributes;
    }

    /**
     * Creates a single attribute document from product attribute value object
     *
     * @param mixed $value
     * @param ProductAttributeValueInterface $productAttributeValue
     * @param LocaleInterface $locale
     *
     * @return AttributeDocument
     */
    public function createSingleAttributeDocumentFromSyliusProductAttributeValue(
        $value,
        ProductAttributeValueInterface $productAttributeValue,
        LocaleInterface $locale
    ) {
        /** @var ProductAttributeTranslationInterface $productAttributeValueTranslation */
        $productAttributeValueTranslation = $productAttributeValue->getAttribute()->getTranslation($locale->getCode());

        /** @var AttributeDocument $productAttribute */
        $productAttribute = new $this->attributeDocumentClass();
        $productAttribute->setCode($productAttributeValue->getCode());
        $productAttribute->setValue($value);
        $productAttribute->setName($productAttributeValueTranslation->getName());

        return $productAttribute;
    }
}
