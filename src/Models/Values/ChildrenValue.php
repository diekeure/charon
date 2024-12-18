<?php

declare(strict_types=1);

namespace CatLab\Charon\Models\Values;

use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\PropertyResolver;
use CatLab\Charon\Interfaces\PropertySetter;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Identifier;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\Base\RelationshipValue;

/**
 * Class ChildrenValue
 * @package CatLab\RESTResource\Models\Values
 */
class ChildrenValue extends RelationshipValue
{
    private ?\CatLab\Charon\Collections\ResourceCollection $children = null;

    /**
     * @param ResourceCollection $children
     * @return $this
     */
    public function setChildren(ResourceCollection $children): static
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return ResourceCollection
     */
    public function getChildren(): ?\CatLab\Charon\Collections\ResourceCollection
    {
        return $this->children;
    }

    /**
     * @param string|null $path
     * @return array
     */
    public function getValue(string $path = null)
    {
        $items = $this->children->toArray();
        return $items[ResourceTransformer::RELATIONSHIP_ITEMS];
    }

    /**
     * @inheritDoc
     * @return mixed[]
     */
    public function getTransformedEntityValue(Context $context = null): array
    {
        $out = [];
        foreach ($this->children as $child) {
            /** @var RESTResource $child */
            $out[] = $child->getProperties()->transformToEntityValuesMap($context);
        }

        return $out;
    }

    /**
     * @return mixed
     */
    public function toArray()
    {
        return $this->children->toArray();
    }

    /**
     * @return ResourceCollection
     */
    protected function getChildrenToProcess(): ?\CatLab\Charon\Collections\ResourceCollection
    {
        return $this->children;
    }

    /**
     * Add a child to a colleciton
     * @param ResourceTransformer $transformer
     * @param PropertySetter $propertySetter
     * @param $entity
     * @param RelationshipField $field
     * @param array $childEntities
     * @param Context $context
     * @return void
     */
    protected function addChildren(
        ResourceTransformer $transformer,
        PropertySetter $propertySetter,
        $entity,
        RelationshipField $field,
        array $childEntities,
        Context $context
    ) {
        $propertySetter->addChildren(
            $transformer,
            $entity,
            $this->getField(),
            $childEntities,
            $context
        );
    }

    /**
     * Add a child to a colleciton
     * @param ResourceTransformer $transformer
     * @param PropertySetter $propertySetter
     * @param $entity
     * @param RelationshipField $field
     * @param array $childEntities
     * @param Context $context
     * @return void
     */
    protected function editChildren(
        ResourceTransformer $transformer,
        PropertySetter $propertySetter,
        $entity,
        RelationshipField $field,
        array $childEntities,
        Context $context
    ) {
        $propertySetter->editChildren(
            $transformer,
            $entity,
            $this->getField(),
            $childEntities,
            $context
        );
    }

    /**
     * @param ResourceTransformer $transformer
     * @param PropertyResolver $propertyResolver
     * @param $parent
     * @param PropertyValueCollection $identifiers
     * @param Context $context
     * @return mixed
     */
    protected function getChildByIdentifiers(
        ResourceTransformer $transformer,
        PropertyResolver $propertyResolver,
        &$parent,
        Identifier $identifier,
        Context $context
    ) {
        return $propertyResolver->getChildByIdentifiers(
            $transformer,
            $this->getField(),
            $parent,
            $identifier,
            $context
        );
    }

    /**
     * @param ResourceTransformer $transformer
     * @param PropertyResolver $propertyResolver
     * @param PropertySetter $propertySetter
     * @param $entity
     * @param RelationshipField $field
     * @param PropertyValueCollection[] $identifiers
     * @param Context $context
     * @return mixed
     */
    protected function removeAllChildrenExcept(
        ResourceTransformer $transformer,
        PropertyResolver $propertyResolver,
        PropertySetter $propertySetter,
        $entity,
        RelationshipField $field,
        array $identifiers,
        Context $context
    ): void {
        $propertySetter->removeAllChildrenExcept(
            $transformer,
            $propertyResolver,
            $entity,
            $field,
            $identifiers,
            $context
        );
    }
}
