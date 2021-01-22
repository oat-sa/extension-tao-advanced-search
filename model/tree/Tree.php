<?php


namespace oat\taoAdvancedSearch\model\tree;


class Tree
{
    /** @var ClassElement */
    private $classes;

    public function __construct(ClassElement ...$classes)
    {
        $this->classes = $classes;
    }

    /**
     * @return ClassElement
     */
    public function getClasses(): ClassElement
    {
        return $this->classes;
    }

    public function addClassElement(ClassElement ...$classes)
    {
        $this->classes = array_push($this->classes, $classes);
    }
}
