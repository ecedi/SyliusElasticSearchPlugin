<?php

namespace spec\Sylius\ElasticSearchPlugin\Projection;

use Doctrine\Common\Collections\ArrayCollection;
use ONGR\ElasticsearchBundle\Service\Manager;
use ONGR\ElasticsearchBundle\Service\Repository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Event\ProductCreated;
use Sylius\ElasticSearchPlugin\Factory\ProductDocumentFactoryInterface;
use Sylius\ElasticSearchPlugin\Projection\ProductProjector;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class ProductProjectorSpec extends ObjectBehavior
{
    function let(
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory,
        Repository $productDocumentRepository
    ) {
        $elasticsearchManager->getRepository(ProductDocument::class)->willReturn($productDocumentRepository);

        $this->beConstructedWith($elasticsearchManager, $productDocumentFactory);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ProductProjector::class);
    }

    function it_saves_product_documents_and_removes_the_current_ones(
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory,
        Repository $productDocumentRepository,
        ProductInterface $product,
        LocaleInterface $locale,
        ChannelInterface $channel
    ) {
        $product->getCode()->willReturn('FOO');
        $product->getChannels()->willReturn(new ArrayCollection([$channel->getWrappedObject()]));
        $channel->getLocales()->willReturn(new ArrayCollection([$locale->getWrappedObject()]));

        $existingProductDocument = new ProductDocument();
        $productDocumentRepository->findBy(['code' => 'FOO'])->willReturn(new \ArrayIterator([$existingProductDocument]));

        $newProductDocument = new ProductDocument();
        $productDocumentFactory->createFromSyliusSimpleProductModel($product, $locale, $channel)->willReturn($newProductDocument);

        $elasticsearchManager->persist($newProductDocument)->shouldBeCalled();
        $elasticsearchManager->remove($existingProductDocument)->shouldBeCalled();
        $elasticsearchManager->commit()->shouldBeCalled();

        $this->handleProductCreated(ProductCreated::occur($product->getWrappedObject()));
    }

    function it_does_not_save_product_document_if_product_does_not_have_channel_defined(
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory,
        Repository $productDocumentRepository,
        ProductInterface $product
    ) {
        $product->getCode()->willReturn('FOO');
        $product->getChannels()->willReturn(new ArrayCollection([]));

        $productDocumentRepository->findBy(['code' => 'FOO'])->willReturn(new \ArrayIterator([]));

        $productDocumentFactory->createFromSyliusSimpleProductModel(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $elasticsearchManager->persist(Argument::any())->shouldNotBeCalled();
        $elasticsearchManager->commit()->shouldBeCalled();

        $this->handleProductCreated(ProductCreated::occur($product->getWrappedObject()));
    }

    function it_does_not_save_product_document_if_channel_has_not_locales_defined(
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory,
        Repository $productDocumentRepository,
        ProductInterface $product,
        ChannelInterface $channel
    ) {
        $product->getCode()->willReturn('FOO');
        $channel->getLocales()->willReturn(new ArrayCollection([]));
        $product->getChannels()->willReturn(new ArrayCollection([$channel->getWrappedObject()]));

        $productDocumentRepository->findBy(['code' => 'FOO'])->willReturn(new \ArrayIterator([]));

        $productDocumentFactory->createFromSyliusSimpleProductModel(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $elasticsearchManager->persist(Argument::any())->shouldNotBeCalled();
        $elasticsearchManager->commit()->shouldBeCalled();

        $this->handleProductCreated(ProductCreated::occur($product->getWrappedObject()));
    }
}
