<?php

namespace spec\Sylius\ElasticSearchPlugin\Document;

use Sylius\ElasticSearchPlugin\Document\PriceDocument;
use PhpSpec\ObjectBehavior;

final class PriceDocumentSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(PriceDocument::class);
    }

    function it_has_amount()
    {
        $this->setAmount(1000);

        $this->getAmount()->shouldReturn(1000);
    }

    function it_has_currency()
    {
        $this->setCurrency('EUR');

        $this->getCurrency()->shouldReturn('EUR');
    }
}
