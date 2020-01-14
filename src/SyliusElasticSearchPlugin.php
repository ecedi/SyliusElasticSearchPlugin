<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\ElasticSearchPlugin\DependencyInjection\Compiler\RegisterFilterTypePass;
use Sylius\ElasticSearchPlugin\DependencyInjection\Compiler\RegisterSearchCriteriaApplicatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SyliusElasticSearchPlugin extends Bundle
{
    use SyliusPluginTrait;

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}
