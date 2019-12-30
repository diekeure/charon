<?php

namespace CatLab\Charon;

use CatLab\Base\Enum\Operator;
use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\Charon\Collections\InputParserCollection;
use CatLab\Charon\Collections\ParentEntityCollection;
use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Charon\Exceptions\NotImplementedException;
use CatLab\Charon\Exceptions\ValueUndefined;
use CatLab\Charon\Exceptions\IterableExpected;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\DynamicContext;
use CatLab\Charon\Interfaces\IdentifierCollection;
use CatLab\Charon\Interfaces\PropertyResolver;
use CatLab\Charon\Interfaces\PropertySetter;
use CatLab\Charon\Interfaces\RequestResolver;
use CatLab\Charon\Interfaces\ResourceCollection;
use CatLab\Charon\Interfaces\ResourceFactory as ResourceFactoryInterface;
use CatLab\Charon\Interfaces\RESTResource as ResourceContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\EntityFactory as EntityFactoryContract;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Exceptions\InvalidContextAction;
use CatLab\Charon\Models\CurrentPath;
use CatLab\Charon\Models\FilterResults;
use CatLab\Charon\Models\Identifier;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Exceptions\InvalidEntityException;
use CatLab\Charon\Exceptions\InvalidPropertyException;
use CatLab\Charon\Library\ResourceDefinitionLibrary;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Interfaces\ResourceTransformer as ResourceTransformerContract;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\Charon\Interfaces\ResourceFactory;

/**
 * Class ResourceTransformer
 * @package CatLab\RESTResource\Transformers
 */
abstract class ResourceTransformer implements ResourceTransformerContract
{
    /**
     * Apply processor filters (= filters that are created by processors) and translate them to the system specific
     * query builder.
     * @param $queryBuilder
     * @param SelectQueryParameters $parameters
     * @return void
     */
    abstract public function applyCatLabFilters($queryBuilder, SelectQueryParameters $parameters);

    /**
     * @var PropertyResolver
     */
    protected $propertyResolver;

    /**
     * @var PropertySetter
     */
    protected $propertySetter;

    /**
     * @var RequestResolver
     */
    protected $requestResolver;

    /**
     * @var ResourceFactoryInterface
     */
    protected $resourceFactory;

    /**
     * @var CurrentPath
     */
    protected $currentPath;

    /**
     * @var mixed[]
     */
    protected $parents;

    /**
     * @var int
     */
    protected $maxDepth = 50;

    /**
     * @var InputParserCollection
     */
    protected $inputParsers;

    /**
     * ResourceTransformer constructor.
     * @param PropertyResolver|null $propertyResolver
     * @param PropertySetter|null $propertySetter
     * @param RequestResolver|null $requestResolver
     * @param ResourceFactoryInterface|null $resourceFactory
     */
    public function __construct(
        PropertyResolver $propertyResolver = null,
        PropertySetter $propertySetter = null,
        RequestResolver $requestResolver = null,
        ResourceFactoryInterface $resourceFactory = null
    ) {
        if (!isset($propertyResolver)) {
            $propertyResolver = new \CatLab\Charon\Resolvers\PropertyResolver();
        }

        if (!isset($propertySetter)) {
            $propertySetter = new \CatLab\Charon\Resolvers\PropertySetter();
        }

        if (!isset($resourceFactory)) {
            $resourceFactory = new \CatLab\Charon\Factories\ResourceFactory();
        }

        if (!isset($requestResolver)) {
            $requestResolver = new \CatLab\Charon\Resolvers\RequestResolver();
        }

        $this->propertyResolver = $propertyResolver;
        $this->propertySetter = $propertySetter;
        $this->resourceFactory = $resourceFactory;
        $this->requestResolver = $requestResolver;

        $this->currentPath = new CurrentPath();
        $this->parents = new ParentEntityCollection();
        $this->inputParsers = new InputParserCollection();
    }

    /**
     * @return mixed
     */
    public function getParentEntity()
    {
        return $this->parents->getParent();
    }

    /**
     * @param ResourceDefinition|string $resourceDefinition
     * @param mixed $entities
     * @param ContextContract $context
     * @param FilterResults|null $filterResults
     * @param RelationshipValue $parent
     * @param null $parentEntity
     * @return ResourceCollection
     * @throws Exceptions\InvalidTransformer
     * @throws Exceptions\VariableNotFoundInContext
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws InvalidPropertyException
     * @throws IterableExpected
     */
    public function toResources(
        $resourceDefinition,
        $entities,
        ContextContract $context,
        FilterResults $filterResults = null,
        RelationshipValue $parent = null,
        $parentEntity = null
    ) : \CatLab\Charon\Interfaces\ResourceCollection {
        
        if (!ArrayHelper::isIterable($entities)) {
            throw new InvalidEntityException(__CLASS__ . '::toResources expects an iterable object of entities at ' . $this->currentPath);
        }

        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);

        $out = $this->getResourceFactory()->createResourceCollection();

        foreach ($entities as $entity) {
            $out->add($this->toResource($resourceDefinition, $entity, $context, $parent, $parentEntity));
        }

        $context->getProcessors()->processCollection(
            $this,
            $out,
            $resourceDefinition,
            $context,
            $filterResults,
            $parent,
            $parentEntity
        );

        return $out;
    }

    /**
     * @param ResourceDefinition|string $resourceDefinition
     * @param mixed $entity
     * @param ContextContract $context
     * @param RelationshipValue $parent
     * @param null $parentEntity
     * @return ResourceContract
     * @throws InvalidContextAction
     * @throws InvalidEntityException
     * @throws InvalidPropertyException
     * @throws IterableExpected
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     * @throws \CatLab\Charon\Exceptions\VariableNotFoundInContext
     */
    public function toResource(
        $resourceDefinition,
        $entity,
        ContextContract $context,
        RelationshipValue $parent = null,
        $parentEntity = null
    ) : ResourceContract {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);
        $this->checkEntityType($resourceDefinition, $entity);

        if (!Action::isReadContext($context->getAction())) {
            throw InvalidContextAction::expectedReadable($context->getAction());
        }

        // Dynamic context required?
        if (
            $resourceDefinition instanceof DynamicContext ||
            $entity instanceof DynamicContext
        ) {
            // In case of dynamic context we must start from a fork of the context
            $context = $context->fork();

            if ($resourceDefinition instanceof DynamicContext) {
                $resourceDefinition->transformContext($context, $entity);
            }

            if ($entity instanceof DynamicContext) {
                $entity->transformContext($context, $entity);
            }
        }

        $resource = new RESTResource($resourceDefinition);
        $resource->setSource($entity);

        $fields = $resourceDefinition->getFields();

        $this->parents->push($entity);

        /** @var Field $field */
        foreach ($fields as $field) {
            $this->currentPath->push($field);
            $visible = $this->shouldInclude($field, $context);
            if ($visible || $field->isSortable()) {
                if ($field instanceof RelationshipField) {
                    if ($this->shouldExpand($field, $context)) {
                        $this->expandRelationship($field, $entity, $resource, $context, $visible);
                    } else {
                        $this->linkRelationship($field, $entity, $resource, $context, $visible);
                    }
                } elseif ($field instanceof ResourceField) {
                    $value = $this->propertyResolver->resolveProperty($this, $entity, $field, $context);

                    if ($field->isArray()) {
                        // Null values = emtpy arrays.
                        if ($value === null) {
                            $value = [];
                        }

                        if (!ArrayHelper::isIterable($value)) {
                            throw IterableExpected::make($field, $value);
                        }

                        // Translate to regular array (otherwise we might get in trouble)
                        $transformedValue = [];
                        $transformer = $field->getTransformer();

                        if ($transformer) {
                            foreach ($value as $k => $v) {
                                $transformedValue[$k] = $transformer->toResourceValue($v, $context);
                            }
                        } else {
                            foreach ($value as $k => $v) {
                                $transformedValue[$k] = $v;
                            }
                        }
                        $value = $transformedValue;

                    } else {
                        if ($transformer = $field->getTransformer()) {
                            $value = $transformer->toResourceValue($value, $context);
                        }
                    }

                    $resource->setProperty(
                        $field,
                        $value,
                        $visible
                    );
                } else {
                    throw new \InvalidArgumentException("Unexpected field type found: " . get_class($field));
                }
            }
            $this->currentPath->pop();
        }

        $context->getProcessors()->processResource(
            $this,
            $resource,
            $resourceDefinition,
            $context,
            $parent,
            $parentEntity
        );

        $this->parents->pop();
        return $resource;
    }

    /**
     * @param ResourceContract $resource
     * @param $resourceDefinition
     * @param EntityFactoryContract $factory
     * @param mixed|null $entity
     * @param ContextContract $context
     * @return mixed $entity
     * @throws \CatLab\Charon\Exceptions\InvalidTransformer
     */
    public function toEntity(
        ResourceContract $resource,
        $resourceDefinition,
        EntityFactoryContract $factory,
        ContextContract $context,
        $entity = null
    ) {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);

        if ($entity === null) {
            $entity = $factory->createEntity($resourceDefinition->getEntityClassName(), $context);
        }

        $this->parents->push($entity);

        $values = $resource->getProperties()->getValues();
        foreach ($values as $property) {
            $property->toEntity($entity, $this, $this->propertyResolver, $this->propertySetter, $factory, $context);
        }

        $this->parents->pop();

        return $entity;
    }

    /**
     * Create a resource from a data array
     * @param $resourceDefinition
     * @param array $body
     * @param ContextContract $context
     * @return ResourceContract
     * @throws InvalidPropertyException
     * @throws InvalidContextAction
     */
    public function fromArray($resourceDefinition, array $body, ContextContract $context) : ResourceContract
    {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);
        if (!Action::isWriteContext($context->getAction())) {
            throw InvalidContextAction::expectedWriteable($context->getAction());
        }

        $resource = new RESTResource($resourceDefinition);
        $resource->setSource($body);

        $fields = $resourceDefinition->getFields();

        foreach ($fields as $field) {
            $this->currentPath->push($field);

            if ($this->isWritable($field, $context)) {
                if ($field instanceof RelationshipField) {
                    $this->relationshipFromArray($field, $body, $resource, $context);
                } else {
                    try {
                        $value = $this->propertyResolver->resolvePropertyInput(
                            $this,
                            $body,
                            $field,
                            $context
                        );

                        $resource->setProperty($field, $value, true);
                    } catch (ValueUndefined $e) {
                        // Don't worry, be happy.
                    }
                }
            }
            $this->currentPath->pop();
        }

        return $resource;
    }

    /**
     * @param $resourceDefinition
     * @param $content
     * @param EntityFactoryContract $factory
     * @param ContextContract $context
     * @return array
     * @throws InvalidContextAction
     */
    public function entitiesFromIdentifiers(
        $resourceDefinition,
        $content,
        EntityFactoryContract $factory,
        ContextContract $context
    ) {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);
        if (!Action::isWriteContext($context->getAction())) {
            throw InvalidContextAction::expectedWriteable($context->getAction());
        }

        $out = [];
        if ($content instanceof IdentifierCollection) {
            // Collection of Identifier objects
            foreach ($content as $v) {
                $entity = $this->fromIdentifier($resourceDefinition, $v, $factory, $context);
                if ($entity) {
                    $out[] = $entity;
                }
            }
        } elseif (isset($content[self::RELATIONSHIP_ITEMS])) {
            // This is a list of items
            foreach ($content[self::RELATIONSHIP_ITEMS] as $item) {
                $entity = $this->fromIdentifier($resourceDefinition, $item, $factory, $context);
                if ($entity) {
                    $out[] = $entity;
                }
            }
        } else {
            $entity = $this->fromIdentifier($resourceDefinition, $content, $factory, $context);
            if ($entity) {
                $out[] = $entity;
            }
        }
        return $out;
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @param $identifier
     * @param EntityFactoryContract $factory
     * @param ContextContract $context
     * @return mixed
     */
    private function fromIdentifier(
        ResourceDefinition $resourceDefinition,
        $identifier,
        EntityFactoryContract $factory,
        ContextContract $context
    ) {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);

        if (! ($identifier instanceof Identifier)) {
            $identifier = Identifier::fromArray($resourceDefinition, $identifier);
        }

        return $factory->resolveFromIdentifier(
            $resourceDefinition->getEntityClassName(),
            $identifier,
            $context
        );
    }

    /**
     * Given a querybuilder or a list of items, process eager loading for each relationship that should be visible.
     * This method should be called before calling toEntities, and is also called for each relationship that needs
     * to be loaded.
     * @param $entities
     * @param $resourceDefinition
     * @param ContextContract $context
     */
    public function processEagerLoading($entities, $resourceDefinition, ContextContract $context)
    {
        $definition = ResourceDefinitionLibrary::make($resourceDefinition);

        // Now check for query parameters
        foreach ($definition->getFields() as $field) {

            $this->currentPath->push($field);

            if (
                $field instanceof RelationshipField &&
                $this->shouldInclude($field, $context) &&
                $this->shouldExpand($field, $context)
            ) {
                $this->propertyResolver->eagerLoadRelationship($this, $entities, $field, $context);
            }

            $this->currentPath->pop();
        }
    }

    /**
     * Apply any filterable/searchable fields
     * @param $request
     * @param string|ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @param $queryBuilder
     * @return FilterResults
     */
    public function applyFilters(
        $request,
        $resourceDefinition,
        Context $context,
        $queryBuilder
    ) {
        $filterResults = new FilterResults();
        $filterResults->setQueryBuilder($queryBuilder);

        $definition = ResourceDefinitionLibrary::make($resourceDefinition);

        // First process all filtersable and searchable fields.
        foreach ($definition->getFields() as $field) {
            if ($field instanceof ResourceField) {

                // Filterable fields
                if ($field->isFilterable()) {
                    $value = $this->getRequestResolver()->getFilter($request, $field);
                    if ($value) {
                        $this->getPropertyResolver()->applyPropertyFilter($this, $definition, $context, $field, $queryBuilder, $value, Operator::EQ);
                    }
                } elseif ($field->isSearchable()) {
                    $value = $this->getRequestResolver()->getFilter($request, $field);
                    if ($value) {
                        $this->getPropertyResolver()->applyPropertyFilter($this, $definition, $context, $field, $queryBuilder, $value, Operator::SEARCH);
                    }
                }

            }
        }

        // Now go through all processors and apply any filters or parameters they might want to set.
        $context->getProcessors()->processFilters(
            $this,
            $queryBuilder,
            $request,
            $resourceDefinition,
            $context,
            $filterResults
        );

        return $filterResults;
    }

    /**
     * @param RelationshipField $field
     * @param mixed $entity
     * @param RESTResource $resource
     * @param Context $context
     * @param bool $visible
     * @throws InvalidPropertyException
     * @throws Exceptions\VariableNotFoundInContext
     */
    private function expandRelationship(
        RelationshipField $field,
        $entity,
        RESTResource $resource,
        Context $context,
        $visible = true
    ) {
        if (count($this->parents) > $this->maxDepth) {
            $this->linkRelationship($field, $entity, $resource, $context, $visible);
            return;
        }

        $url = $this->propertyResolver->resolvePathParameters($this, $entity, $field->getUrl(), $context);
        switch ($field->getCardinality()) {
            case Cardinality::MANY:
                $children = $this->propertyResolver->resolveManyRelationship(
                    $this,
                    $entity,
                    $resource->touchChildrenProperty($field),
                    $context
                );

                $resource->setChildrenProperty($field, $url, $children, $visible);
                break;

            case Cardinality::ONE:
                $child = $this->propertyResolver->resolveOneRelationship(
                    $this,
                    $entity,
                    $resource->touchChildProperty($field),
                    $context
                );

                if ($child) {
                    $resource->setChildProperty($field, $url, $child, $visible);
                } else {
                    $resource->clearProperty($field, $url);
                }
                break;

            default:
                throw new InvalidPropertyException("Relationship has invalid type.");
        }
    }

    /**
     * @param RelationshipField $field
     * @param &$body
     * @param RESTResource $resource
     * @param ContextContract $context
     * @throws InvalidPropertyException
     */
    private function relationshipFromArray(RelationshipField $field, &$body, RESTResource $resource, ContextContract $context)
    {
        // If no data is provided, don't set the property.
        if (!$this->propertyResolver->hasRelationshipInput($this, $body, $field, $context)) {
            return;
        }

        switch ($field->getCardinality()) {
            case Cardinality::MANY:
                $children = $this->propertyResolver->resolveManyRelationshipInput(
                    $this,
                    $body,
                    $field,
                    $context
                );

                $resource->setChildrenProperty($field, null, $children, true);
                break;

            case Cardinality::ONE:
                $child = $this->propertyResolver->resolveOneRelationshipInput($this, $body, $field, $context);
                if ($child) {
                    $resource->setChildProperty($field, null, $child, true);
                } else {
                    $resource->setChildProperty($field, null, null, true);
                }
                break;

            default:
                throw new InvalidPropertyException("Relationship has invalid type.");
        }
    }

    /**
     * @param RelationshipField $field
     * @param $entity
     * @param RESTResource $resource
     * @param ContextContract $context
     * @param bool $visible
     * @return array
     * @throws InvalidPropertyException
     */
    private function linkRelationship(
        RelationshipField $field,
        $entity,
        RESTResource $resource,
        ContextContract $context,
        $visible
    ) {
        $url = $this->propertyResolver->resolvePathParameters($this, $entity, $field->getUrl(), $context);
        $resource->setLink($field, $url, $visible);
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @param $entity
     * @throws InvalidEntityException
     */
    private function checkEntityType(ResourceDefinition $resourceDefinition, $entity)
    {
        $entityClassName = $resourceDefinition->getEntityClassName();

        if ($entityClassName === null) {
            // Null given? Ok!
            return;
        }

        if (! ($entity instanceof $entityClassName)) {

            if (is_object($entity)) {
                $providedType = get_class($entity);
            } else {
                $providedType = gettype($entity);
            }

            throw new InvalidEntityException(
                "ResourceTransformer expects $entityClassName, " . $providedType . " given."
            );
        }
    }

    /**
     * @param Field $field
     * @param ContextContract $context
     * @return bool
     */
    private function shouldInclude(Field $field, ContextContract $context)
    {
        return $field->shouldInclude($context, $this->currentPath);
    }

    /**
     * @param Field $field
     * @param ContextContract $context
     * @return bool
     */
    private function isWritable(Field $field, ContextContract $context)
    {
        return $field->shouldInclude($context, $this->currentPath);
    }

    /**
     * @param RelationshipField $field
     * @param ContextContract $context
     * @return bool
     */
    private function shouldExpand(RelationshipField $field, ContextContract $context)
    {
        return $field->shouldExpand($context, $this->currentPath);
    }

    /**
     * @return PropertyResolver
     */
    public function getPropertyResolver() : PropertyResolver
    {
        return $this->propertyResolver;
    }

    /**
     * @return PropertySetter
     */
    public function getPropertySetter() : PropertySetter
    {
        return $this->propertySetter;
    }

    /**
     * @return RequestResolver
     */
    public function getRequestResolver(): RequestResolver
    {
        return $this->requestResolver;
    }

    /**
     * @return ResourceFactoryInterface
     */
    public function getResourceFactory(): ResourceFactoryInterface
    {
        return $this->resourceFactory;
    }

    /**
     * @param Field $field
     * @return string
     */
    public function getQualifiedName(Field $field) : string
    {
        return $this->getPropertyResolver()->getQualifiedName($field);
    }

    /**
     * Create resources from whatever is in the inputs defined from the input parsers.
     * @param $resourceDefinition
     * @param ContextContract $context
     * @param null $request
     * @return ResourceCollection
     * @throws NoInputDataFound
     */
    public function fromInput(
        $resourceDefinition,
        ContextContract $context,
        $request = null
    ): ResourceCollection
    {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);

        $resources = $context->getInputParser()->getResources(
            $this,
            $resourceDefinition,
            $context,
            $request
        );

        if (!$resources) {
            throw NoInputDataFound::make();
        }

        return $resources;
    }

    /**
     * Create resource identifiers from whatever is in the inputs defined from the input parsers
     * @param $resourceDefinition
     * @param ContextContract $context
     * @param null $request
     * @return IdentifierCollection
     */
    public function identifiersFromInput(
        $resourceDefinition,
        ContextContract $context,
        $request = null
    ) : IdentifierCollection {
        $resourceDefinition = ResourceDefinitionLibrary::make($resourceDefinition);

        $identifiers = $context->getInputParser()->getIdentifiers(
            $this,
            $resourceDefinition,
            $context,
            $request
        );

        if (!$identifiers) {
            throw new \InvalidArgumentException("No data found in body");
        }

        return $identifiers;
    }

    /**
     * Return filters that were created by processors. These are always in the (old) catlab base framework,
     * since this is platform independent. The resourcetransformer then needs to translate these queries and
     * apply them to the provided querybuilder.
     *
     * @param $request
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param int|null $records
     * @return SelectQueryParameters
     */
    protected function getProcessorFilters(
        $request,
        ResourceDefinition $resourceDefinition,
        Context $context,
        int $records = null
    ) {
        $selectQueryParameters = new SelectQueryParameters();

        $context->getProcessors()->processFilters(
            $this,
            $selectQueryParameters,
            $request,
            $resourceDefinition,
            $context,
            $records
        );

        return $selectQueryParameters;
    }
}
