<?php

namespace CatLab\Charon\Models\Routing;

use CatLab\Base\Interfaces\Database\OrderParameter;
use CatLab\Charon\Collections\HeaderCollection;
use CatLab\Charon\Collections\ParameterCollection;
use CatLab\Charon\Enums\Cardinality;
use CatLab\Charon\Interfaces\DescriptionBuilder;
use CatLab\Charon\Interfaces\RouteMutator;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Enums\Method;
use CatLab\Requirements\Enums\PropertyType;
use CatLab\Charon\Library\ResourceDefinitionLibrary;

/**
 * Class ReturnValue
 * @package CatLab\RESTResource\Models\Routing
 */
class ReturnValue implements RouteMutator
{
    /**
     * @var Route
     */
    private $parent;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $cardinality = Cardinality::ONE;

    /**
     * @var string
     */
    private $context;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $description;

    /**
     * @var HeaderCollection
     */
    private $headers;

    /**
     * ReturnValue constructor.
     * @param RouteMutator $route
     * @param string $type
     * @param string $context
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public function __construct(RouteMutator $route, $type = null, $context = null)
    {
        $this->parent = $route;

        if (isset($type)) {
            $this->type = $type;
        }

        $this->statusCode = 200;
        $this->headers = new HeaderCollection();
    }

    /**
     * @param string $type
     * @param null $context
     * @return ReturnValue
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public function one($type = null, $context = null) : ReturnValue
    {
        $this->cardinality = Cardinality::ONE;
        if (isset($type)) {
            $this->type = $type;
        }

        if (isset($context)) {
            Action::checkValid($context);
            $this->context = $context;
        }

        return $this;
    }

    /**
     * @param string $type
     * @param null $context
     * @return ReturnValue
     * @throws \CatLab\Charon\Exceptions\InvalidContextAction
     */
    public function many($type = null, $context = null) : ReturnValue
    {
        $this->cardinality = Cardinality::MANY;
        if (isset($type)) {
            $this->type = $type;
        }

        if (isset($context)) {
            Action::checkValid($context);
            $this->context = $context;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCardinality() : string
    {
        return $this->cardinality;
    }

    /**
     * @return null|string
     */
    public function getType() : string
    {
        if (isset($this->type)) {
            return $this->type;
        } else {
            return PropertyType::STRING;
        }
    }

    /**
     * @return string
     */
    public function getContext() : string
    {
        if (!isset($this->cardinality)) {
            $this->cardinality = Cardinality::ONE;
        }

        if (isset($this->context)) {
            return $this->context;
        }

        return Method::toAction($this->parent->getMethod(), $this->cardinality);
    }

    /**
     * @param string $type
     * @param string $action
     * @return ReturnValue
     */
    public function returns(string $type = null, string $action = null) : ReturnValue
    {
        return $this->parent->returns($type, $action);
    }

    /**
     * @param string $tag
     * @return RouteMutator
     */
    public function tag(string $tag) : RouteMutator
    {
        return $this->parent->tag($tag);
    }

    /**
     * @return ParameterCollection
     */
    public function parameters() : ParameterCollection
    {
        return $this->parent->parameters();
    }

    /**
     * @param string $summary
     * @return RouteMutator
     */
    public function summary($summary) : RouteMutator
    {
        return $this->parent->summary($summary);
    }

    /**
     * @param $status
     * @return $this
     */
    public function statusCode($status)
    {
        $this->statusCode = $status;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function describe(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return HeaderCollection
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * @return \CatLab\Charon\Interfaces\ResourceDefinition|null
     */
    public function getResourceDefinition()
    {
        if (PropertyType::isNative($this->getType())) {
            return null;
        } else {
            return ResourceDefinitionLibrary::make($this->getType());
        }
    }

    /**
     * @param DescriptionBuilder $builder
     * @return array
     */
    public function toSwagger(DescriptionBuilder $builder)
    {
        $response = [];

        // Is this a native type?
        if (PropertyType::isNative($this->getType())) {
            // Do nothing.
        } else {
            $schema = $builder->getRelationshipSchema(
                ResourceDefinitionLibrary::make($this->getType()),
                $this->getContext(),
                $this->getCardinality()
            );

            $response = [
                'schema' => $schema
            ];
        }

        if (isset($this->description)) {
            $response['description'] = $this->description;
        } else {
            $response['description'] = $this->getDescriptionFromType();
        }

        if ($this->headers->count() > 0) {
            $response['headers'] = $this->headers->toSwagger($builder);
        }

        return $response;
    }

    /**
     * @return string
     */
    protected function getDescriptionFromType()
    {
        if (!$this->getType()) {
            return 'No description set.';
        }

        if (PropertyType::isNative($this->getType())) {
            return 'Returns ' . $this->getType();
        } else {
            $type = ResourceDefinitionLibrary::make($this->getType());
            return 'Returns ' . $this->getCardinality() . ' ' . $type->getEntityClassName();
        }
    }

    /**
     * @param string $mimetype
     * @return RouteMutator
     */
    public function consumes(string $mimetype) : RouteMutator
    {
        return call_user_func_array([ $this->parent, 'consumes' ], func_get_args());
    }

    /**
     * @param string $order
     * @param string $direction
     * @return RouteMutator
     */
    public function defaultOrder(string $order, $direction = OrderParameter::ASC) : RouteMutator
    {
        return $this->parent->defaultOrder($order, $direction);
    }
}