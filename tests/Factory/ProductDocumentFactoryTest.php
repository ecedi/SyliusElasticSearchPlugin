<?php

namespace Tests\Sylius\ElasticSearchPlugin\Factory;

use ONGR\ElasticsearchBundle\Collection\Collection;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\ElasticSearchPlugin\Document\AttributeDocument;
use Sylius\ElasticSearchPlugin\Document\ImageDocument;
use Sylius\ElasticSearchPlugin\Document\OptionDocument;
use Sylius\ElasticSearchPlugin\Document\PriceDocument;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Document\ProductTaxonDocument;
use Sylius\ElasticSearchPlugin\Document\TaxonDocument;
use Sylius\ElasticSearchPlugin\Document\VariantDocument;
use Sylius\ElasticSearchPlugin\Exception\UnsupportedFactoryMethodException;
use Sylius\ElasticSearchPlugin\Factory\ProductDocumentFactory;

final class ProductDocumentFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_creates_product_document_from_sylius_product_model()
    {
        $createdAt = \DateTime::createFromFormat(\DateTime::W3C, '2017-04-18T16:12:55+02:00');
        $syliusProductAttributeValue = new ProductAttributeValue();
        $syliusProductAttribute = new ProductAttribute();
        $syliusProductAttribute->setCurrentLocale('en_US');
        $syliusProductAttribute->setCode('color');
        $syliusProductAttribute->setName('Color');
        $syliusProductAttributeValue->setLocaleCode('en_US');
        $syliusProductAttribute->setType(TextAttributeType::TYPE);
        $syliusProductAttribute->setStorageType(TextAttributeType::TYPE);
        $syliusProductAttributeValue->setAttribute($syliusProductAttribute);
        $syliusProductAttributeValue->setValue('red');

        $syliusTaxon = new Taxon();
        $syliusTaxon->setCurrentLocale('en_US');
        $syliusTaxon->setCode('TREE');
        $syliusTaxon->setSlug('tree');
        $syliusTaxon->setDescription('Lorem ipsum');
        $syliusTaxon->setPosition(0);

        $syliusProductTaxon = new ProductTaxon();
        $syliusProductTaxon->setPosition(1);

        $syliusLocale = new Locale();
        $syliusLocale->setCode('en_US');

        $syliusProductVariant = new ProductVariant();
        $syliusProductVariant->setCode('red_banana');
        $syliusProductVariant->setPosition(1);

        $syliusProduct = new Product();
        $channelPrice = new ChannelPricing();
        $syliusChannel = new Channel();
        $currency = new Currency();
        $currency->setCode('USD');

        $syliusProductTaxon->setProduct($syliusProduct);
        $syliusProductTaxon->setTaxon($syliusTaxon);
        $syliusProductTaxon->setPosition(1);
        $channelPrice->setPrice(1000);
        $channelPrice->setChannelCode('mobile');

        $syliusChannel->setCode('mobile');
        $syliusChannel->setDefaultLocale($syliusLocale);
        $syliusChannel->addLocale($syliusLocale);
        $syliusChannel->addCurrency($currency);
        $syliusChannel->setBaseCurrency($currency);

        $syliusProductVariant->addChannelPricing($channelPrice);
        $syliusProduct->addVariant($syliusProductVariant);
        $syliusProduct->addChannel($syliusChannel);
        $syliusProduct->setMainTaxon($syliusTaxon);
        $syliusProduct->addProductTaxon($syliusProductTaxon);
        $syliusProduct->setCreatedAt($createdAt);
        $syliusProduct->setCurrentLocale('en_US');
        $syliusProduct->setName('Banana');
        $syliusProduct->setSlug('/banana');
        $syliusProduct->setDescription('Lorem ipsum');
        $syliusProduct->setCode('banana');
        $syliusProduct->addAttribute($syliusProductAttributeValue);

        $factory = new ProductDocumentFactory(
            ProductDocument::class,
            AttributeDocument::class,
            ImageDocument::class,
            PriceDocument::class,
            ProductTaxonDocument::class,
            TaxonDocument::class,
            VariantDocument::class,
            OptionDocument::class,
            ['color']
        );
        /** @var ProductDocument $product */
        $product = $factory->createFromSyliusSimpleProductModel(
            $syliusProduct,
            $syliusLocale,
            $syliusChannel
        );

        $taxon = new TaxonDocument();
        $taxon->setCode('TREE');
        $taxon->setPosition(0);
        $taxon->setSlug('tree');
        $taxon->setDescription('Lorem ipsum');

        $productTaxon = new ProductTaxonDocument();
        $productTaxon->setCode('TREE');
        $productTaxon->setSlug('tree');
        $productTaxon->setPosition(1);

        $productAttribute = new AttributeDocument();
        $productAttribute->setCode('color');
        $productAttribute->setName('Color');
        $productAttribute->setValue('red');

        $this->assertEquals('banana', $product->getCode());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals(
            new Collection([
                $productAttribute
            ]),
            $product->getAttributes()
        );
        $this->assertEquals(1000, $product->getPrice()->getAmount());
        $this->assertEquals('USD', $product->getPrice()->getCurrency());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals('mobile', $product->getChannelCode());
        $this->assertEquals('/banana', $product->getSlug());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals($createdAt, $product->getCreatedAt());
        $this->assertEquals('Lorem ipsum', $product->getDescription());
        $this->assertEquals($taxon, $product->getMainTaxon());
        $this->assertEquals(new Collection([$productTaxon]), $product->getProductTaxons());
        $this->assertEquals(0.0, $product->getAverageReviewRating());
    }

    /**
     * @test
     */
    public function it_creates_product_document_only_with_whitelisted_attributes()
    {
        $createdAt = \DateTime::createFromFormat(\DateTime::W3C, '2017-04-18T16:12:55+02:00');
        $firstSyliusProductAttributeValue = new ProductAttributeValue();
        $firstSyliusProductAttribute = new ProductAttribute();
        $firstSyliusProductAttribute->setCurrentLocale('en_US');
        $firstSyliusProductAttribute->setCode('material');
        $firstSyliusProductAttribute->setName('Material');
        $firstSyliusProductAttributeValue->setLocaleCode('en_US');
        $firstSyliusProductAttribute->setType(TextAttributeType::TYPE);
        $firstSyliusProductAttribute->setStorageType(TextAttributeType::TYPE);
        $firstSyliusProductAttributeValue->setAttribute($firstSyliusProductAttribute);
        $firstSyliusProductAttributeValue->setValue('wood');

        $secondSyliusProductAttributeValue = new ProductAttributeValue();
        $secondSyliusProductAttribute = new ProductAttribute();
        $secondSyliusProductAttribute->setCurrentLocale('en_US');
        $secondSyliusProductAttribute->setCode('size');
        $secondSyliusProductAttribute->setName('Size');
        $secondSyliusProductAttributeValue->setLocaleCode('en_US');
        $secondSyliusProductAttribute->setType(TextAttributeType::TYPE);
        $secondSyliusProductAttribute->setStorageType(TextAttributeType::TYPE);
        $secondSyliusProductAttributeValue->setAttribute($secondSyliusProductAttribute);
        $secondSyliusProductAttributeValue->setValue('M');

        $syliusTaxon = new Taxon();
        $syliusTaxon->setCurrentLocale('en_US');
        $syliusTaxon->setCode('TREE');
        $syliusTaxon->setSlug('tree');
        $syliusTaxon->setDescription('Lorem ipsum');
        $syliusTaxon->setPosition(0);

        $syliusProductTaxon = new ProductTaxon();
        $syliusProductTaxon->setPosition(1);

        $syliusLocale = new Locale();
        $syliusLocale->setCode('en_US');

        $syliusProductVariant = new ProductVariant();
        $syliusProductVariant->setCode('red_banana');
        $syliusProductVariant->setPosition(1);

        $syliusProduct = new Product();
        $channelPrice = new ChannelPricing();
        $syliusChannel = new Channel();
        $currency = new Currency();
        $currency->setCode('USD');

        $syliusProductTaxon->setProduct($syliusProduct);
        $syliusProductTaxon->setTaxon($syliusTaxon);
        $syliusProductTaxon->setPosition(1);
        $channelPrice->setPrice(1000);
        $channelPrice->setChannelCode('mobile');

        $syliusChannel->setCode('mobile');
        $syliusChannel->setDefaultLocale($syliusLocale);
        $syliusChannel->addLocale($syliusLocale);
        $syliusChannel->addCurrency($currency);
        $syliusChannel->setBaseCurrency($currency);

        $syliusProductVariant->addChannelPricing($channelPrice);
        $syliusProduct->addVariant($syliusProductVariant);
        $syliusProduct->addChannel($syliusChannel);
        $syliusProduct->setMainTaxon($syliusTaxon);
        $syliusProduct->addProductTaxon($syliusProductTaxon);
        $syliusProduct->setCreatedAt($createdAt);
        $syliusProduct->setCurrentLocale('en_US');
        $syliusProduct->setName('Banana');
        $syliusProduct->setSlug('/banana');
        $syliusProduct->setDescription('Lorem ipsum');
        $syliusProduct->setCode('banana');
        $syliusProduct->addAttribute($firstSyliusProductAttributeValue);
        $syliusProduct->addAttribute($secondSyliusProductAttributeValue);

        $factory = new ProductDocumentFactory(
            ProductDocument::class,
            AttributeDocument::class,
            ImageDocument::class,
            PriceDocument::class,
            ProductTaxonDocument::class,
            TaxonDocument::class,
            VariantDocument::class,
            OptionDocument::class,
            ['material']
        );
        /** @var ProductDocument $product */
        $product = $factory->createFromSyliusSimpleProductModel(
            $syliusProduct,
            $syliusLocale,
            $syliusChannel
        );

        $taxon = new TaxonDocument();
        $taxon->setCode('TREE');
        $taxon->setPosition(0);
        $taxon->setSlug('tree');
        $taxon->setDescription('Lorem ipsum');

        $productTaxon = new ProductTaxonDocument();
        $productTaxon->setCode('TREE');
        $productTaxon->setSlug('tree');
        $productTaxon->setPosition(1);

        $firstProductAttribute = new AttributeDocument();
        $firstProductAttribute->setCode('material');
        $firstProductAttribute->setName('Material');
        $firstProductAttribute->setValue('wood');

        $secondProductAttribute = new AttributeDocument();
        $secondProductAttribute->setCode('size');
        $secondProductAttribute->setName('Size');
        $secondProductAttribute->setValue('M');

        $this->assertEquals('banana', $product->getCode());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals(
            new Collection([
                $firstProductAttribute,
            ]),
            $product->getAttributes()
        );

        $this->assertEquals(1000, $product->getPrice()->getAmount());
        $this->assertEquals('USD', $product->getPrice()->getCurrency());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals('mobile', $product->getChannelCode());
        $this->assertEquals('/banana', $product->getSlug());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals($createdAt, $product->getCreatedAt());
        $this->assertEquals('Lorem ipsum', $product->getDescription());
        $this->assertEquals($taxon, $product->getMainTaxon());
        $this->assertEquals(new Collection([$taxon]), $product->getTaxons());
        $this->assertEquals(new Collection([$productTaxon]), $product->getProductTaxons());
        $this->assertEquals(0.0, $product->getAverageReviewRating());
    }

    /**
     * @test
     */
    public function it_creates_product_document_with_all_taxon_parents()
    {
        $createdAt = \DateTime::createFromFormat(\DateTime::W3C, '2017-04-18T16:12:55+02:00');
        $firstSyliusProductAttributeValue = new ProductAttributeValue();
        $firstSyliusProductAttribute = new ProductAttribute();
        $firstSyliusProductAttribute->setCurrentLocale('en_US');
        $firstSyliusProductAttribute->setCode('material');
        $firstSyliusProductAttribute->setName('Material');
        $firstSyliusProductAttributeValue->setLocaleCode('en_US');
        $firstSyliusProductAttribute->setType(TextAttributeType::TYPE);
        $firstSyliusProductAttribute->setStorageType(TextAttributeType::TYPE);
        $firstSyliusProductAttributeValue->setAttribute($firstSyliusProductAttribute);
        $firstSyliusProductAttributeValue->setValue('wood');

        $secondSyliusProductAttributeValue = new ProductAttributeValue();
        $secondSyliusProductAttribute = new ProductAttribute();
        $secondSyliusProductAttribute->setCurrentLocale('en_US');
        $secondSyliusProductAttribute->setCode('size');
        $secondSyliusProductAttribute->setName('Size');
        $secondSyliusProductAttributeValue->setLocaleCode('en_US');
        $secondSyliusProductAttribute->setType(TextAttributeType::TYPE);
        $secondSyliusProductAttribute->setStorageType(TextAttributeType::TYPE);
        $secondSyliusProductAttributeValue->setAttribute($secondSyliusProductAttribute);
        $secondSyliusProductAttributeValue->setValue('M');

        $syliusParentTaxon = new Taxon();
        $syliusParentTaxon->setCurrentLocale('en_US');
        $syliusParentTaxon->setCode('ROOT');
        $syliusParentTaxon->setSlug('root');
        $syliusParentTaxon->setDescription('Lorem ipsum');
        $syliusParentTaxon->setPosition(0);

        $syliusTaxon = new Taxon();
        $syliusTaxon->setCurrentLocale('en_US');
        $syliusTaxon->setCode('TREE');
        $syliusTaxon->setSlug('tree');
        $syliusTaxon->setDescription('Lorem ipsum');
        $syliusTaxon->setParent($syliusParentTaxon);
        $syliusTaxon->setPosition(0);

        $syliusLocale = new Locale();
        $syliusLocale->setCode('en_US');

        $syliusProductVariant = new ProductVariant();
        $syliusProductVariant->setCode('red_banana');
        $syliusProductVariant->setPosition(1);

        $syliusProduct = new Product();
        $channelPrice = new ChannelPricing();
        $syliusChannel = new Channel();
        $currency = new Currency();
        $currency->setCode('USD');

        $syliusProductTaxon = new ProductTaxon();
        $syliusProductTaxon->setProduct($syliusProduct);
        $syliusProductTaxon->setTaxon($syliusTaxon);
        $syliusProductTaxon->setPosition(1);
        $channelPrice->setPrice(1000);
        $channelPrice->setChannelCode('mobile');

        $syliusChannel->setCode('mobile');
        $syliusChannel->setDefaultLocale($syliusLocale);
        $syliusChannel->addLocale($syliusLocale);
        $syliusChannel->addCurrency($currency);
        $syliusChannel->setBaseCurrency($currency);

        $syliusProductVariant->addChannelPricing($channelPrice);
        $syliusProduct->addVariant($syliusProductVariant);
        $syliusProduct->addChannel($syliusChannel);
        $syliusProduct->setMainTaxon($syliusTaxon);
        $syliusProduct->addProductTaxon($syliusProductTaxon);
        $syliusProduct->setCreatedAt($createdAt);
        $syliusProduct->setCurrentLocale('en_US');
        $syliusProduct->setName('Banana');
        $syliusProduct->setSlug('/banana');
        $syliusProduct->setDescription('Lorem ipsum');
        $syliusProduct->setCode('banana');
        $syliusProduct->addAttribute($firstSyliusProductAttributeValue);
        $syliusProduct->addAttribute($secondSyliusProductAttributeValue);

        $factory = new ProductDocumentFactory(
            ProductDocument::class,
            AttributeDocument::class,
            ImageDocument::class,
            PriceDocument::class,
            ProductTaxonDocument::class,
            TaxonDocument::class,
            VariantDocument::class,
            OptionDocument::class,
            ['material']
        );
        /** @var ProductDocument $product */
        $product = $factory->createFromSyliusSimpleProductModel(
            $syliusProduct,
            $syliusLocale,
            $syliusChannel
        );

        $taxon = new TaxonDocument();
        $taxon->setCode('TREE');
        $taxon->setPosition(0);
        $taxon->setSlug('tree');
        $taxon->setDescription('Lorem ipsum');

        $rootTaxon = new TaxonDocument();
        $rootTaxon->setCode('ROOT');
        $rootTaxon->setPosition(0);
        $rootTaxon->setSlug('root');
        $rootTaxon->setDescription('Lorem ipsum');

        $productTaxon = new ProductTaxonDocument();
        $productTaxon->setCode('TREE');
        $productTaxon->setSlug('tree');
        $productTaxon->setPosition(1);

        $firstProductAttribute = new AttributeDocument();
        $firstProductAttribute->setCode('material');
        $firstProductAttribute->setName('Material');
        $firstProductAttribute->setValue('wood');

        $secondProductAttribute = new AttributeDocument();
        $secondProductAttribute->setCode('size');
        $secondProductAttribute->setName('Size');
        $secondProductAttribute->setValue('M');

        $this->assertEquals('banana', $product->getCode());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals(
            new Collection([
                $firstProductAttribute,
            ]),
            $product->getAttributes()
        );

        $this->assertEquals(1000, $product->getPrice()->getAmount());
        $this->assertEquals('USD', $product->getPrice()->getCurrency());
        $this->assertEquals('en_US', $product->getLocaleCode());
        $this->assertEquals('mobile', $product->getChannelCode());
        $this->assertEquals('/banana', $product->getSlug());
        $this->assertEquals('Banana', $product->getName());
        $this->assertEquals($createdAt, $product->getCreatedAt());
        $this->assertEquals('Lorem ipsum', $product->getDescription());
        $this->assertEquals($taxon, $product->getMainTaxon());
        $this->assertEquals(new Collection([$taxon, $rootTaxon]), $product->getTaxons());
        $this->assertEquals(new Collection([$productTaxon]), $product->getProductTaxons());
    }
}
