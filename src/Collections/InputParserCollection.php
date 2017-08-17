<?php

namespace CatLab\Charon\Collections;

use CatLab\Base\Collections\Collection;
use CatLab\Charon\Exceptions\NoInputParsersSet;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\DescriptionBuilder;
use CatLab\Charon\Interfaces\InputParser;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Library\InputParserLibrary;
use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Models\Routing\Parameters\ResourceParameter;
use CatLab\Charon\Models\Routing\Route;

/**
 * Class InputParserCollection
 * @package CatLab\Charon\Collections
 */
class InputParserCollection extends Collection implements InputParser
{
    /**
     * Look for identifier input
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param null $request
     * @return IdentifierCollection|null
     */
    public function getIdentifiers(
        ResourceTransformer $resourceTransformer,
        ResourceDefinition $resourceDefinition,
        Context $context,
        $request = null
    ) {
        $this->checkExists();

        /** @var InputParser $inputParser */
        foreach ($this as $inputParser) {
            $inputParser = InputParserLibrary::make($inputParser);
            $content = $inputParser->getIdentifiers($resourceTransformer, $resourceDefinition, $context, $request);

            if ($content) {
                return $content;
            }
        }

        return null;
    }

    /**
     * Look for
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param null $request
     * @return ResourceCollection|null
     */
    public function getResources(
        ResourceTransformer $resourceTransformer,
        ResourceDefinition $resourceDefinition,
        Context $context,
        $request = null
    ) {
        $this->checkExists();

        /** @var InputParser $inputParser */
        foreach ($this as $inputParser) {
            $inputParser = InputParserLibrary::make($inputParser);
            $content = $inputParser->getResources($resourceTransformer, $resourceDefinition, $context, $request);
            if ($content) {
                return $content;
            }
        }

        return null;
    }

    /**
     * @param DescriptionBuilder $builder
     * @param Route $route
     * @param ResourceParameter $parameter
     * @param ResourceDefinition $resourceDefinition
     * @param null $request
     * @return ParameterCollection
     */
    public function getResourceRouteParameters(
        DescriptionBuilder $builder,
        Route $route,
        ResourceParameter $parameter,
        ResourceDefinition $resourceDefinition,
        $request = null
    ): ParameterCollection
    {
        $this->checkExists();

        $out = new ParameterCollection($route);

        foreach ($this as $inputParser) {
            $inputParser = InputParserLibrary::make($inputParser);

            $parameters = $inputParser->getResourceRouteParameters($builder, $route, $parameter, $resourceDefinition, $request);
            $out->merge($parameters);
        }

        return $out;
    }

    /**
     * @throws NoInputParsersSet
     */
    protected function checkExists()
    {
        if ($this->count() === 0) {
            throw NoInputParsersSet::make();
        }
    }
}