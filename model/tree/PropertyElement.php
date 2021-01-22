<?php

namespace oat\taoAdvancedSearch\model\tree;

class PropertyElement
{
    /** @var string */
    private $uri;

    /** @var string */
    private $label;

    public function __construct(string $uri, string $label)
    {
        $this->uri = $uri;
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }


}