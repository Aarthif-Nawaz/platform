<?php

namespace Tests\Unit\App\ImportUshahidiV2\Mappers;

use Ushahidi\App\ImportUshahidiV2\Importer;
use Ushahidi\App\ImportUshahidiV2\Contracts\ImportMappingRepository;
use Ushahidi\App\ImportUshahidiV2\Contracts\Mapper;
use Ushahidi\Core\Entity\Repository\EntityCreateMany;
use Ushahidi\Core\Entity;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Mockery as M;
use Faker;

/**
 * @backupGlobals disabled
 * @preserveGlobalState disabled
 */
class ImporterTest extends TestCase
{
    public function testImporter()
    {
        $faker = Faker\Factory::create();
        $mapper = M::mock(Mapper::class);
        $mappingRepo = M::mock(ImportMappingRepository::class);
        $destRepo = M::mock(EntityCreateMany::class);

        $entity = M::mock(Entity::class);
        $entity->shouldReceive('getResource')
            ->andReturn('anEntity');

        $mapper->shouldReceive('__invoke')
            ->times(20)
            ->andReturn($entity);

        $destRepo->shouldReceive('createMany')
            ->once()
            ->with(M::type(Collection::class))
            ->andReturn(array_fill(0, 20, 7));

        $mappingRepo->shouldReceive('createMany')
            ->once()
            ->with(M::type(Collection::class));

        $importer = new Importer(
            'someTable',
            $mapper,
            $mappingRepo,
            $destRepo
        );

        // Build some junk data
        $source = collect(array_fill(0, 20, null))
            ->map(function () use ($faker) {
                return [
                    'field1' => $faker->word,
                    'field2' => $faker->sentence,
                ];
            });

        $imported = $importer->run(1, $source);

        $this->assertEquals(20, $imported);
    }
}
