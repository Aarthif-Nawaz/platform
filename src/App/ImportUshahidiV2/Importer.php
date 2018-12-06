<?php

namespace Ushahidi\App\ImportUshahidiV2;

use Illuminate\Support\Collection;
use Ushahidi\Core\Entity\Repository\EntityCreateMany; // @todo correct interface?
use Ushahidi\App\ImportUshahidiV2;

class Importer
{
    protected $sourceType;
    protected $destType;
    protected $mapper;
    protected $mappingRepo;
    protected $destRepo;

    public function __construct(
        string $sourceType,
        Contracts\Mapper $mapper,
        Contracts\ImportMappingRepository $mappingRepo,
        EntityCreateMany $destRepo
    ) {
        $this->sourceType = $sourceType;
        $this->mapper = $mapper;
        $this->mappingRepo = $mappingRepo;
        $this->destRepo = $destRepo;

        // @todo depend on an interface that supplies getEntity?
        $this->destType = $this->destRepo->getEntity()->getResource();
    }

    /**
     * Run import
     *
     * @todo naming: run? handle? interact? process?
     *
     * @param  $importId            [<description>]
     * @param  Collection $source   source data
     * @return int                  records imported
     */
    public function run($importId, Collection $source) : int
    {
        $mapper = $this->mapper;

        // Transform users
        $destModels = $source->map(function ($item) use ($mapper) {
            return $mapper((array) $item);
        });

        // Save users
        $inserted = $this->destRepo->createMany($destModels);

        // Match source and destination ids
        $mappings = $source->pluck('id')->combine($inserted)->map(function ($item, $key) use ($importId) {
            return new ImportUshahidiV2\ImportMapping([
                'import_id' => $importId,
                'source_type' => $this->sourceType,
                'source_id' => $key,
                'dest_type' => $this->destType,
                'dest_id' => $item,
            ]);
        });

        // Save mappings
        $this->mappingRepo->createMany($mappings);

        // Return count
        return $destModels->count();
    }
}
