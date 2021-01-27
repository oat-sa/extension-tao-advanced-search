<?php


namespace oat\taoAdvancedSearch\model\tree;


use Doctrine\Common\Collections\ArrayCollection;

class Tree extends ArrayCollection
{
    public function __construct(ClassElement ...$classes)
    {
        parent::__construct($classes);
    }

    public function toArray()
    {
        $arrayTree = [];
        foreach ($this->getIterator() as $element) {
            $arrayTree[$element->getUri()] = [
                'parentClass' => $element->getParentClass(),
                'properties' => $element->getProperties()->toArray()
            ];
        }

        return $arrayTree;
    }
}
