<?php

namespace oat\taoAdvancedSearch\model\Index;

class IndexResource
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $label;

    /**
     * @var array
     */
    private $data;

    public function __construct(string $id, string $label, array $data)
    {
        $this->id = $id;
        $this->label = $label;
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
