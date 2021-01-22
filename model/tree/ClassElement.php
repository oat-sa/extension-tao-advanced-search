<?php


namespace oat\taoAdvancedSearch\model\tree;


class ClassElement
{
    /** @var string */
    private $uri;

    /** @var PropertyElement[] */
    private $properties;

    /** @var string */
    private $parentClass;

    public function __construct(string $uri, array $properties, string $parentClass)
    {
        $this->uri = $uri;
        $this->properties = $properties;
        $this->parentClass = $parentClass;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return PropertyElement[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function getParentClass(): string
    {
        return $this->parentClass;
    }
}
