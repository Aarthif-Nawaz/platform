<?php

/**
 * Import Mapping Repo
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2018 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\App\ImportUshahidiV2\Repositories;

use Ushahidi\App\ImportUshahidiV2\ImportMapping;
use Ushahidi\App\ImportUshahidiV2\Contracts\ImportMappingRepository as ImportMappingRepositoryContract;
use Illuminate\Support\Collection;

class ImportMappingRepository /*extends EloquentRepository*/ implements ImportMappingRepositoryContract
{

    public function create(ImportMapping $model) : int
    {
        return $model->save() ? $model->id : false;
    }

    public function createMany(Collection $collection) : array
    {
        $insertId = ImportMapping::insert(
            $collection->map(function ($model) {
                return $model->toArray();
            })->all()
        );

        $insertId = ImportMapping::resolveConnection()->getPdo()->lastInsertId();

        return range($insertId, $insertId + $collection->count() - 1);
    }

    public function getDestId(int $importId, string $sourceType, $sourceId)
    {
        return ImportMapping::where([
            'import_id' => $importId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ])->value('dest_id');
    }
}
