<?php

namespace spec\Sylius\ElasticSearchPlugin\Document;

use Sylius\ElasticSearchPlugin\Document\AttributeDocument;
use PhpSpec\ObjectBehavior;

final class AttributeDocumentSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(AttributeDocument::class);
    }

    function it_has_code()
    {
        $this->setCode('color');

        $this->getCode()->shouldReturn('color');
    }

    function it_has_name()
    {
        $this->setName('color');

        $this->getName()->shouldReturn('color');
    }

    function it_has_value()
    {
        $this->setValue('red');

        $this->getValue()->shouldReturn('red');
    }
}
