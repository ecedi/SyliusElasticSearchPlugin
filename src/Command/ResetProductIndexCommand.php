<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Command;

use ONGR\ElasticsearchBundle\Service\Manager;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\ElasticSearchPlugin\Factory\ProductDocumentFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ResetProductIndexCommand extends Command
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var ProductDocumentFactoryInterface
     */
    private $productDocumentFactory;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Manager $manager
     * @param ProductDocumentFactoryInterface $productDocumentFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Manager $manager,
        ProductDocumentFactoryInterface $productDocumentFactory
    ) {
        $this->productRepository = $productRepository;
        $this->manager = $manager;
        $this->productDocumentFactory = $productDocumentFactory;

        parent::__construct('sylius:elastic-search:reset-product-index');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', null, 'To confirm running this command')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('force')) {
            $output->writeln('WARNING! This command will drop the existing index and rebuild it from scratch. To proceed, run with "--force" option.');

            return;
        }

        $this->manager->dropAndCreateIndex();

        $productDocumentsCreated = 0;

        /** @var ProductInterface[] $products */
        $products = $this->productRepository->findAll();
        foreach ($products as $product) {
            $channels = $product->getChannels();

            /** @var ChannelInterface $channel */
            foreach ($channels as $channel) {
                $locales = $channel->getLocales();
                foreach ($locales as $locale) {
                    $productDocument = $this->productDocumentFactory->createFromSyliusSimpleProductModel(
                        $product,
                        $locale,
                        $channel
                    );

                    $this->manager->persist($productDocument);

                    ++$productDocumentsCreated;
                    if (($productDocumentsCreated % 100) === 0) {
                        $this->manager->commit();
                    }
                }
            }
        }

        $this->manager->commit();

        $output->writeln('Product index was reset!');
    }
}
