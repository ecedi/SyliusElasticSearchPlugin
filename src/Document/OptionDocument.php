<?php

declare(strict_types=1);

namespace Sylius\ElasticSearchPlugin\Document;

use ONGR\ElasticsearchBundle\Annotation as ElasticSearch;

/**
 * @ElasticSearch\Nested
 */
class OptionDocument
{
    /**
     * @var string
     *
     * @ElasticSearch\Property(type="keyword")
     */
    private $code;

    /**
     * @var string
     *
     * @ElasticSearch\Property(
     *  type="text",
     *  name="name",
     *  options={
     *    "analyzer"="keywordAnalyzer",
     *    "fields"={
     *        "raw"={"type"="keyword"},
     *        "standard"={"type"="text", "analyzer"="incrementalAnalyzer"}
     *    }
     *  }
     * )
     */
    private $name;

    /**
     * @var string
     *
     * @ElasticSearch\Property(
     *  type="text",
     *  name="value",
     *  options={
     *    "analyzer"="keywordAnalyzer",
     *    "fields"={
     *        "raw"={"type"="keyword"},
     *        "standard"={"type"="text", "analyzer"="incrementalAnalyzer"}
     *    }
     *  }
     * )
     */
    private $value;

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

}
