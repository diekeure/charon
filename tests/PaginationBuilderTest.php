<?php

declare(strict_types=1);

namespace Tests;

use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\Charon\Processors\PaginationProcessor;
use CatLab\Charon\ResourceTransformer;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\Context;

use CatLab\CursorPagination\CursorPaginationBuilder;
use PHPUnit_Framework_TestCase;
use Tests\Petstore\Definitions\PetDefinitionWithDate;
use Tests\Petstore\Models\Category;
use Tests\Petstore\Models\Pet;
use Tests\Petstore\Models\Tag;

/**
 * Class MaxDepthTest
 *
 * Tests the max depth parameter for recursive relationship fields.
 *
 * @package CatLab\RESTResource\Tests
 */
final class PaginationBuilderTest extends BaseTest
{
    /**
     *
     */
    public function testDisplayName(): void
    {
        $cursors = $this->getCursorsToTest('pet-id');
        $this->assertEquals('{"pet-id":1}', base64_decode($cursors['before']));
        $this->assertEquals('{"pet-id":3}', base64_decode($cursors['after']));
    }

    /**
     *
     */
    public function testDisplayNameReverse(): void
    {
        $cursors = $this->getCursorsToTest('!pet-id');
        $this->assertEquals('{"!pet-id":1}', base64_decode($cursors['before']));
        $this->assertEquals('{"!pet-id":3}', base64_decode($cursors['after']));
    }

    /**
     *
     */
    public function testUnknownSortOrder(): void
    {
        $cursors = $this->getCursorsToTest('fubaro');
        $this->assertEquals('{"pet-id":1}', base64_decode($cursors['before']));
        $this->assertEquals('{"pet-id":3}', base64_decode($cursors['after']));
    }

    /**
     *
     */
    public function testDate(): void
    {
        // temporary disabled
        return;
        $cursors = $this->getCursorsToTest('someDate');

        $this->assertEquals('{"someDate":"Wed, 02 Apr 86 10:00:00 +0000","pet-id":1}', base64_decode($cursors['before']));
        $this->assertEquals('{"someDate":"Fri, 04 Apr 86 10:00:00 +0000","pet-id":3}', base64_decode($cursors['after']));

        /**
         * Test after
         */
        $cursors = $this->getCursorsToTest('someDate', $cursors['after']);

        /** @var SelectQueryParameters $filters */
        $filters = $cursors['filters'];

        $whereQueries = $filters->getWhere();
        $dateWhere = $whereQueries[0];

        $this->assertEquals('1986-04-04 10:00:00', $dateWhere->getComparison()->getValue()->format('Y-m-d H:i:s'));

        /**
         * Test before
         */
        $this->assertEquals('>=', $dateWhere->getComparison()->getOperator());
        $cursors = $this->getCursorsToTest('someDate', null, $cursors['before']);

        /** @var SelectQueryParameters $filters */
        $filters = $cursors['filters'];

        $whereQueries = $filters->getWhere();
        $dateWhere = $whereQueries[0];

        $this->assertEquals('<=', $dateWhere->getComparison()->getOperator());
        $this->assertEquals('1986-04-02 10:00:00', $dateWhere->getComparison()->getValue()->format('Y-m-d H:i:s'));
    }

    private function getCursorsToTest(string $sortOrder, $afterCursor = null, $beforeCursor = null): array
    {
        $petDefinition = new PetDefinitionWithDate();

        $category = new Category();
        $category
            ->setId(1)
            ->setName('Felidae')
            ->setDescription('All cat-like animals.')
        ;

        $pet1 = new Pet();
        $pet1
            ->setId(1)
            ->setName('Cat')
            ->setCategory($category)
            ->setStatus(Pet::STATUS_AVAILABLE)
            ->setSomeDate(\DateTime::createFromFormat('d/m/Y H:i', '2/4/1986 10:00'))
        ;

        $pet2 = new Pet();
        $pet2
            ->setId(2)
            ->setName('Cat')
            ->setCategory($category)
            ->setStatus(Pet::STATUS_AVAILABLE)
            ->setSomeDate(\DateTime::createFromFormat('d/m/Y H:i', '3/4/1986 10:00'))
        ;

        $pet3 = new Pet();
        $pet3
            ->setId(3)
            ->setName('Cat')
            ->setCategory($category)
            ->setStatus(Pet::STATUS_AVAILABLE)
            ->setSomeDate(\DateTime::createFromFormat('d/m/Y H:i', '4/4/1986 10:00'))
        ;

        $context = new Context(Action::VIEW);
        $context->addProcessor(new PaginationProcessor(CursorPaginationBuilder::class));

        $context->showFields([
            'pet-id',
            'name',
            'category.category-id',
            'category.category-description',
            'tags.tag-id',
            'someDate'
        ]);

        $context->expandFields([
            'category',
            'tags'
        ]);

        $resourceTransformer = $this->getResourceTransformer();

        $queryBuilder = new SelectQueryParameters();

        $filters = $resourceTransformer->getFilters(
            [
                'sort' => $sortOrder,
                'after' => $afterCursor,
                'before' => $beforeCursor
            ],
            $petDefinition,
            $context
        );

        $filters = $resourceTransformer->applyFilters(
            [
                'sort' => $sortOrder,
                'after' => $afterCursor,
                'before' => $beforeCursor
            ],
            $filters,
            $context,
            $queryBuilder
        );

        $resources = $resourceTransformer->toResources($petDefinition, [ $pet1, $pet2, $pet3 ], $context);

        $data = $resources->toArray();

        $this->assertArrayHasKey('meta', $data);
        $metaData = $data['meta'];

        $this->assertArrayHasKey('pagination', $metaData);
        $paginationData = $metaData['pagination'];

        $this->assertArrayHasKey('next', $paginationData);
        $this->assertArrayHasKey('previous', $paginationData);
        $this->assertArrayHasKey('cursors', $paginationData);


        $cursors = $paginationData['cursors'];
        $this->assertArrayHasKey('before', $cursors);
        $this->assertArrayHasKey('after', $cursors);

        return [
            'before' => $cursors['before'],
            'after' => $cursors['after'] ,
            'filters' => $filters->getQueryBuilder()
        ];
    }
}
