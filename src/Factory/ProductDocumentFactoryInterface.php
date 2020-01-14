<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Factory;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;

interface ProductDocumentFactoryInterface
{
    /**
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
    ): ProductDocument;
}
