<?php

namespace CatLab\Charon\Interfaces;

/**
 * Interface EntityFactory
 * @package CatLab\RESTResource\Contracts
 */
interface EntityFactory
{
    /**
     * @param $entityClassName
     * @param Context $context
     * @return mixed
     */
    public function createEntity($entityClassName, Context $context);

    /**
     * @param $parent
     * @param $entityClassName
     * @param array $identifiers
     * @param Context $context
     * @return mixed
     */
    public function resolveLinkedEntity($parent, string $entityClassName, array $identifiers, Context $context);

    /**
     * @param string $entityClassName
     * @param array $identifiers
     * @param Context $context
     * @return mixed
     */
    public function resolveFromIdentifier(string $entityClassName, array $identifiers, Context $context);
}