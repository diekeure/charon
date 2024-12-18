<?php

declare(strict_types=1);

namespace Tests;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\InputParsers\JsonBodyInputParser;
use CatLab\Charon\Models\Context;
use CatLab\Charon\OpenApi\Authentication\OAuth2Authentication;
use CatLab\Charon\OpenApi\V2\OpenApiV2Builder;
use PHPUnit_Framework_TestCase;

/**
 * Class ValidatorTest
 * @package CatLab\RESTResource\Tests
 */
final class DescriptionTest extends BaseTest
{
    /**
     *
     */
    public function testSwaggerDescription(): void
    {
        $routes = require __DIR__ . '/Petstore/routes.php';

        $builder = new OpenApiV2Builder('localhost', '/');

        $builder
            ->setTitle('Pet store API')
            ->setDescription('This pet store api allows you to buy pets')
            ->setContact('CatLab Interactive', 'https://www.catlab.eu/', 'info@catlab.eu')
            ->setVersion('1.0');

        $oauth = new OAuth2Authentication('oauth2');
        $oauth
            ->setAuthorizationUrl('oauth/authorize')
            ->setFlow('implicit')
            ->addScope('full', 'Full access')
        ;

        $builder->addAuthentication($oauth);

        foreach ($routes->getRoutes() as $route) {
            $builder->addRoute($route);
        }

        $context = new Context(Action::INDEX);
        $context->addInputParser(JsonBodyInputParser::class);

        // For description we only want one input parser!
        //$context->addInputParser(PostInputParser::class);

        $actual = $builder->build($context);

        $expected = json_decode(file_get_contents(__DIR__ . '/swagger/description.json'), true);

        /*
        echo json_encode($actual, JSON_PRETTY_PRINT);
        exit;
        */

        $this->assertEquals($expected, $actual);
    }
}
