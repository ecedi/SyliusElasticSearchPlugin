<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusElasticSearchExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $container->setParameter('sylius_elastic_search.attribute_whitelist', $config['attribute_whitelist']);

        foreach ($config['document_classes'] as $document => $class) {
            $container->setParameter(sprintf('sylius_elastic_search.document.%s.class', $document), $class);
        }

        foreach ($config['view_classes'] as $view => $class) {
            $container->setParameter(sprintf('sylius_elastic_search.view.%s.class', $view), $class);
        }

        $loader->load('services.xml');
    }
}
