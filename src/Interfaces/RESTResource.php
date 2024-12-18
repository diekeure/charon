<?php

declare(strict_types=1);

namespace CatLab\Charon\Interfaces;

use CatLab\Charon\Collections\PropertyValueCollection;
use CatLab\Charon\Models\CurrentPath;

/**
 * Class Resource
 * @package CatLab\RESTResource\Contracts
 */
interface RESTResource extends SerializableResource
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @return mixed
     */
    public function toArray();

    /**
     * @return PropertyValueCollection
     */
    public function getProperties();

    /**
     * @return ResourceDefinition
     */
    public function getResourceDefinition();

    /**
     * Return the source object of the resource.
     * This way you can use the entity of a resource in a post processor.
     * The source should never leave the server.
     * @return mixed
     */
    public function getSource();

    /**
     * @param Context $context
     * @param null $original Original source entity
     * @param string $path
     * @return mixed
     */
    public function validate(Context $context, $original = null, CurrentPath $path = null);
}
