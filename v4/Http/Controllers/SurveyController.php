<?php

namespace v4\Http\Controllers;
use Illuminate\Auth\Access\Gate;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Ushahidi\App\Validator\LegacyValidator;
use v4\Http\Resources\SurveyCollection;
use v4\Http\Resources\SurveyResource;
use v4\Models\Attribute;
use v4\Models\Stage;
use v4\Models\Survey;
use Illuminate\Http\Request;
use v4\Models\Translation;


class SurveyController extends V4Controller
{

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(int $id)
    {
        $survey = Survey::with('translations')->find($id);
        if (!$survey) {
            return response()->json(
                [
                    'errors' => [ 'error' => 404, 'message' => 'Not found' ]
                ],
                404);
        }
        return new SurveyResource($survey);
    }

    /**
     * Display the specified resource.
     * @TODO add enabled_languages (the ones that we have translations for)
     * @return SurveyCollection
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        return new SurveyCollection(Survey::all());
    }

    /**
     * Display the specified resource.
     * @TODO add enabled_languages (the ones that we have translations for)
     * @TODO transactions =)
     * @param Request $request
     * @return SurveyResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request) {
        $authorizer = service('authorizer.form');
        // if there's no user the guards will kick them off already, but if there
        // is one we need to check the authorizer to ensure we don't let
        // users without admin perms create forms etc
        // this is an unfortunate problem with using an old version of lumen
        // that doesn't let me do guest user checks without adding more risk.
        $user = $authorizer->getUser();
        if ($user) {
            $this->authorize('store', Survey::class);
        }
        $this->validate($request, Survey::getRules(), Survey::validationMessages());
        $survey = Survey::create(
            array_merge(
                $request->input(),[ 'updated' => time(), 'created' => time()]
            )
        );
        $this->saveTranslations($request->input('translations'), $survey->id, 'survey');
        if ($request->input('tasks')) {
            foreach ($request->input('tasks') as $stage) {
                $stage_model = $survey->tasks()->create(
                    array_merge(
                        $stage, [ 'updated' => time(), 'created' => time()]
                    )
                );
                $this->saveTranslations($stage['translations'] ?? [], $stage_model->id, 'task');
                foreach ($stage['fields'] as $attribute) {
                    $uuid = Uuid::uuid4();
                    $attribute['key'] = $uuid->toString();
                    $field_model = $stage_model->fields()->create(
                        array_merge(
                            $attribute, [ 'updated' => time(), 'created' => time()]
                        )
                    );
                    $this->saveTranslations($attribute['translations'] ?? [], $field_model->id, 'field');
                }
            }
        }
        return new SurveyResource($survey);
    }

    /**
     * @param $input
     * @param $translatable_id
     * @param $type
     * @return bool
     */
    private function saveTranslations($input, int $translatable_id, string $type) {
        if (!is_array($input)) {
            return true;
        }
        foreach ($input as $language => $translations) {
            foreach ($translations as $key => $translated) {
                if (is_array($translated)){
                    $translated = json_encode($translated);
                }
                Translation::create([
                    'translatable_type' => $type,
                    'translatable_id' => $translatable_id,
                    'translated_key' => $key,
                    'translation' => $translated,
                    'language' => $language
                ]);
            }
        }
    }

    /**
     * @param $input
     * @param $translatable_id
     * @param $type
     * @return bool
     */
    private function updateTranslations($input, int $translatable_id, string $type) {
        if (!is_array($input)) {
            return true;
        }
        Translation::where('translatable_id', $translatable_id)
                                ->where('translatable_type', $type)
                                ->delete();
        foreach ($input as $language => $translations) {
            foreach ($translations as $key => $translated) {
                if (is_array($translated)){
                    $translated = json_encode($translated);
                }
                Translation::create([
                    'translatable_type' => $type,
                    'translatable_id' => $translatable_id,
                    'translated_key' => $key,
                    'translation' => $translated,
                    'language' => $language
                ]);
            }
        }
    }
    /**
     * Display the specified resource.
     * @TODO add enabled_languages (the ones that we have translations for)
     * @TODO transactions =)
     * @param int $id
     * @param Request $request
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(int $id, Request $request) {
        $survey = Survey::find($id);
        $this->authorize('update', $survey);
        if (!$survey) {
            return response()->json(
                [
                    'errors' => [ 'error' => 404, 'message' => 'Not found' ]
                ],
                404);
        }
        $this->validate($request, Survey::getRules(), Survey::validationMessages());
        $survey->update(
            array_merge(
                $request->input(),[ 'updated' => time()]
            )
        );
        $this->updateTranslations($request->input('translations'), $survey->id, 'survey');
        $this->updateTasks($request->input('tasks') ?? [], $survey);
        return new SurveyResource($survey);
    }

    /**
     * @param array $input_tasks
     * @param Survey $survey
     */
    private function updateTasks(array $input_tasks, Survey $survey) {
        $added_tasks = [];
        foreach ($input_tasks as $stage) {
            if (isset($stage['id'])) {
                $stage_model = $survey->tasks->find($stage['id']);
                if (!$stage_model){
                    continue;
                }
                $stage_model->update($stage);
                $stage_model = Stage::find($stage['id']);
            } else {
                $stage_model = $survey->tasks()->create(array_merge(
                    $stage, [ 'updated' => time()]
                ));
                $added_tasks[] = $stage_model->id;
            }
            $this->updateTranslations($stage['translations'] ?? [], $stage_model->id, 'task');
            $this->updateFields($stage['fields'] ?? [], $stage_model);
        }
        $input_tasks_collection = new Collection($input_tasks);
        $survey->load('tasks');

        $tasks_to_delete = $survey->tasks->whereNotIn(
            'id',array_merge($added_tasks, $input_tasks_collection->groupBy('id')->keys()->toArray())
        );
        foreach ($tasks_to_delete as $task_to_delete) {
            Stage::where('id',$task_to_delete->id)->delete();
        }
    }

    /**
     * @param array $input_fields
     * @param Survey $survey
     * @param Stage $stage
     */
    private function updateFields(array $input_fields, Stage $stage) {
        $added_fields = [];
        foreach ($input_fields as $field) {
            if (isset($field['id'])) {
                $field_model = $stage->fields->find($field['id']);
                if (!$field_model){
                    continue;
                }
                $field_model->update($field);
                $field_model = Attribute::find($field['id']);
            } else {
                $uuid = Uuid::uuid4();
                $field_model = $stage->fields()->create(array_merge(
                    $field, [ 'updated' => time(), 'key' => $uuid->toString()]
                ));
                $added_fields[] = $field_model->id;
            }
            $this->updateTranslations($field['translations'] ?? [], $field_model->id, 'field');
        }
        $input_fields_collection = new Collection($input_fields);
        $stage->load('fields');

        $fields_to_delete = $stage->fields->whereNotIn(
            'id',array_merge($added_fields, $input_fields_collection->groupBy('id')->keys()->toArray())
        );
        foreach ($fields_to_delete as $field_to_delete) {
            Attribute::where('id',$field_to_delete->id)->delete();
        }
    }
    /**
     * @param int $id
     */
    public function delete(int $id, Request $request) {
        $survey = Survey::find($id);
        $this->authorize('delete', $survey);
        return response()->json(['result' => ['deleted' => $id]]);
    }
}
