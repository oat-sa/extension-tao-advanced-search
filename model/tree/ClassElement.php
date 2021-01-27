<?php


namespace oat\taoAdvancedSearch\model\tree;


use Doctrine\Common\Collections\ArrayCollection;

class ClassElement
{
    /** @var string */
    private $uri;

    /** @var PropertyElement[] */
    private $properties;

    /** @var string */
    private $parentClass;

    public function __construct(string $uri, string $parentClass, array $properties)
    {
        $this->uri = $uri;
        $this->properties = new ArrayCollection($properties);
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
    public function getProperties(): ArrayCollection
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
