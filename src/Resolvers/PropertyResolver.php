<?php

declare(strict_types=1);

namespace CatLab\Charon\Resolvers;

use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Exceptions\ValueUndefined;
use CatLab\Charon\Exceptions\VariableNotFoundInContext;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Models\Identifier;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\Base\RelationshipValue;

/**
 * Class PropertyResolver
 * @package CatLab\RESTResource\Resolvers
 */
abstract class PropertyResolver extends ResolverBase implements \CatLab\Charon\Interfaces\PropertyResolver
{
    /**
     * @param ResourceTransformer $transformer
     * @param mixed $entity
     * @param Field $field
     * @param Context $context
     * @return mixed
     * @throws InvalidPropertyException
     * @throws VariableNotFoundInContext
     */
    public function resolveProperty(ResourceTransformer $transformer, $entity, Field $field, Context $context)
    {
        $path = $this->splitPathParameters($field->getName());
        return $this->resolveChildPath($transformer, $entity, $path, $field, $context);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param mixed $entity
     * @param RelationshipField $field
     * @param Context $context
     * @return ResourceCollection
     * @throws InvalidPropertyException
     * @throws VariableNotFoundInContext
     */
    public function resolveManyRelationship(
        ResourceTransformer $transformer,
        $entity,
        RelationshipField $field,
        Context $context
    ) {
        return $this->resolveProperty($transformer, $entity, $field, $context);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param mixed $entity
     * @param RelationshipValue $value
     * @param Context $context
     * @return \CatLab\Charon\Interfaces\RESTResource
     * @throws VariableNotFoundInContext
     */
    public function resolveOneRelationship(
        ResourceTransformer $transformer,
        $entity,
        RelationshipField $field,
        Context $context
    ) {
        $child = null;
        try {
            $child = $this->resolveProperty($transformer, $entity, $field, $context);
        } catch (InvalidPropertyException $invalidPropertyException) {
            return null;
        }

        return $child;
    }

    /**
     * @param ResourceTransformer $transformer
     * @param &$input
     * @param Field $field
     * @param Context $context
     * @return mixed
     * @throws ValueUndefined
     */
    public function resolvePropertyInput(
        ResourceTransformer $transformer,
        &$input,
        Field $field,
        Context $context
    ) {
        // resolve the dot notation.
        $displayNamePath = explode('.', $field->getDisplayName());
        $displayName = array_pop($displayNamePath);

        $tmp = &$input;
        foreach ($displayNamePath as $v) {
            if (!isset($tmp[$v])) {
                throw ValueUndefined::make($field->getDisplayName());
            }

            $tmp = &$tmp[$v];
        }

        if (!is_array($tmp) || !array_key_exists($displayName, $tmp)) {
            throw ValueUndefined::make($displayName);
        }

        return $tmp[$displayName];
    }

    /**
     * Check if input contains data.
     * @param ResourceTransformer $transformer
     * @param $input
     * @param Field $field
     * @param Context $context
     * @return bool
     */
    public function hasPropertyInput(
        ResourceTransformer $transformer,
        &$input,
        Field $field,
        Context $context
    ): bool {
        return array_key_exists($field->getDisplayName(), $input);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param mixed &$input ,
     * @param RelationshipField $field
     * @param Context $context
     * @return \CatLab\Charon\Interfaces\ResourceCollection
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function resolveManyRelationshipInput(
        ResourceTransformer $transformer,
        &$input,
        RelationshipField $field,
        Context $context
    ) : ResourceCollection {

        $out = $transformer->getResourceFactory()->createResourceCollection();

        $children = $this->resolveChildrenListInput($transformer, $input, $field, $context);
        if ($children) {
            foreach ($children as $child) {
                $childContext = $this->getInputChildContext($transformer, $field, $context);
                $out->add($transformer->fromArray($field->getChildResourceDefinitionFactory(), $child, $childContext));
            }
        }

        return $out;
    }

    /**
     * Check if relationship data exists in input.
     * @param ResourceTransformer $transformer
     * @param $input
     * @param RelationshipField $field
     * @param Context $context
     * @return bool
     */
    public function hasRelationshipInput(
        ResourceTransformer $transformer,
        &$input,
        RelationshipField $field,
        Context $context
    ) : bool {
        return $this->hasPropertyInput($transformer, $input, $field, $context);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param mixed $input
     * @param RelationshipField $field
     * @param Context $context
     * @return \CatLab\Charon\Interfaces\RESTResource|RESTResource|null
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function resolveOneRelationshipInput(
        ResourceTransformer $transformer,
        &$input,
        RelationshipField $field,
        Context $context
    ) {
        try {
            $child = $this->resolvePropertyInput($transformer, $input, $field, $context);

            if (is_array($child)) {
                $childContext = $this->getInputChildContext($transformer, $field, $context);
                return $transformer->fromArray($field->getChildResource(), $child, $childContext);
            }
        } catch (ValueUndefined $valueUndefined) {
            // Don't worry be happy.
        }

        return null;
    }

    /**
     * @param ResourceTransformer $transformer
     * @param RelationshipField $field
     * @param Context $context
     * @return Context
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    private function getInputChildContext(ResourceTransformer $transformer, RelationshipField $field, Context $context): \CatLab\Charon\Interfaces\Context
    {
        $childResourceDefinition = $field->getChildResource();

        // Check if we want to create a new child or edit an existing child
        if (
            $context->getAction() !== Action::CREATE &&
            $field->canCreateNewChildren($context) &&
            $this->hasInputIdentifier($transformer, $childResourceDefinition, $context, $input)
        ) {
            $action = Action::EDIT;
        } else {
            $action = Action::CREATE;
        }

        return $context->getChildContext($field, $action);
    }

    /**
     * @param ResourceTransformer $transformer
     * @param RelationshipField $field
     * @param mixed $parentEntity
     * @param Identifier $identifier
     * @param Context $context
     * @return mixed|null
     * @throws InvalidPropertyException
     * @throws VariableNotFoundInContext
     */
    public function getChildByIdentifiers(
        ResourceTransformer $transformer,
        RelationshipField $field,
        $parentEntity,
        Identifier $identifier,
        Context $context
    ) {
        $entities = $this->resolveProperty($transformer, $parentEntity, $field, $context);
        foreach ($entities as $entity) {
            if ($this->entityEquals($transformer, $entity, $identifier, $context)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param ResourceTransformer $transformer
     * @param $entity
     * @param RESTResource $resource
     * @param Context $context
     * @return bool
     * @throws InvalidPropertyException
     * @throws VariableNotFoundInContext
     */
    public function doesResourceRepresentEntity(
        ResourceTransformer $transformer,
        $entity,
        RESTResource $resource,
        Context $context
    ) : bool {
        return $this->entityEquals($transformer, $entity, $resource->getIdentifier(), $context);
    }

    /**
     * @param Field $field
     * @return string
     */
    public function getQualifiedName(Field $field)
    {
        return $field->getResourceDefinition()->getEntityClassName() . '.' . $field->getName();
    }

    /**
     * Return TRUE if the input has an id, and thus is an edit of an existing field.
     * @param ResourceTransformer $transformer
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param $input
     * @return bool
     */
    protected function hasInputIdentifier(
        ResourceTransformer $transformer,
        ResourceDefinition $resourceDefinition,
        Context $context,
        &$input
    ) {
        $identifiers = $resourceDefinition->getFields()->getIdentifiers();
        if (count($identifiers) > 0) {
            foreach ($identifiers as $field) {
                try {
                    $value = $this->resolvePropertyInput($transformer, $input, $field, $context);
                    if (!$value) {
                        return false;
                    }
                } catch (ValueUndefined $e) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param ResourceTransformer $transformer
     * @param $input
     * @param Field $field
     * @param Context $context
     * @return null
     */
    protected function resolveChildrenListInput(
        ResourceTransformer $transformer,
        &$input,
        Field $field,
        Context $context
    ) {
        try {
            $children = $this->resolvePropertyInput($transformer, $input, $field, $context);
        } catch (ValueUndefined $valueUndefined) {
            return null;
        }

        if (!$children) {
            return null;
        }

        if (!isset($children[ResourceTransformer::RELATIONSHIP_ITEMS])) {
            return null;
        }

        if (!is_array($children[ResourceTransformer::RELATIONSHIP_ITEMS])) {
            return null;
        }

        return $children[ResourceTransformer::RELATIONSHIP_ITEMS];
    }

    /**
     * @param $request
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function getParameterFromRequest(array $request, string $key, $default = null)
    {
        return $request[$key] ?? $default;
    }
}
