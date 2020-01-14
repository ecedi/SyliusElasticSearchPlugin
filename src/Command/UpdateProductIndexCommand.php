<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Command;

use ONGR\ElasticsearchBundle\Result\DocumentIterator;
use ONGR\ElasticsearchBundle\Service\Manager;
use ONGR\ElasticsearchBundle\Service\Repository;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\ElasticSearchPlugin\Document\ProductDocument;
use Sylius\ElasticSearchPlugin\Factory\ProductDocumentFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateProductIndexCommand extends Command
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Manager
     */
    private $elasticsearchManager;

    /**
     * @var Repository
     */
    private $productDocumentRepository;

    /**
     * @var ProductDocumentFactoryInterface
     */
    private $productDocumentFactory;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Manager $elasticsearchManager
     * @param ProductDocumentFactoryInterface $productDocumentFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Manager $elasticsearchManager,
        ProductDocumentFactoryInterface $productDocumentFactory
    ) {
        $this->productRepository = $productRepository;
        $this->elasticsearchManager = $elasticsearchManager;
        $this->productDocumentRepository = $elasticsearchManager->getRepository(ProductDocument::class);
        $this->productDocumentFactory = $productDocumentFactory;

        parent::__construct('sylius:elastic-search:update-product-index');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Update products in Elasticsearch index.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $processedProductsCodes = [];

        $search = $this->productDocumentRepository->createSearch();
        $search->setScroll('10m');
        $search->addSort(new FieldSort('synchronised_at', 'ASC'));

        /** @var DocumentIterator|ProductDocument[] $productDocuments */
        $productDocuments = $this->productDocumentRepository->findDocuments($search);

        foreach ($productDocuments as $productDocument) {
            $productCode = $productDocument->getCode();

            if (in_array($productCode, $processedProductsCodes, true)) {
                continue;
            }

            $output->writeln(sprintf('Updating product with code "%s"', $productCode));

            $this->scheduleCreatingNewProductDocuments($productCode);
            $this->scheduleRemovingOldProductDocuments($productCode);

            $this->elasticsearchManager->commit();

            $processedProductsCodes[] = $productCode;
        }
    }

    private function scheduleCreatingNewProductDocuments(string $productCode): void
    {
        /** @var ProductInterface|null $product */
        $product = $this->productRepository->findOneBy(['code' => $productCode]);

        if (null === $product) {
            return;
        }

        /** @var ChannelInterface[] $channels */
        $channels = $product->getChannels();

        foreach ($channels as $channel) {
            /** @var LocaleInterface[] $locales */
            $locales = $channel->getLocales();

            foreach ($locales as $locale) {
                $this->elasticsearchManager->persist($this->productDocumentFactory->createFromSyliusSimpleProductModel(
                    $product,
                    $locale,
                    $channel
                ));
            }
        }
    }

    private function scheduleRemovingOldProductDocuments(string $productCode): void
    {
        /** @var DocumentIterator|ProductDocument[] $currentProductDocuments */
        $currentProductDocuments = $this->productDocumentRepository->findBy(['code' => $productCode]);

        foreach ($currentProductDocuments as $sameCodeProductDocument) {
            $this->elasticsearchManager->remove($sameCodeProductDocument);
        }
    }
}
