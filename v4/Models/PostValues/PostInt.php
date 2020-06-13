<?php

namespace v4\Models\PostValues;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class PostInt extends PostValue
{
    public $table = 'post_int';
    /**
     * Scope helper to only pull tags we are allowed to get from the db
     * @param $query
     * @return mixed
     */
    public function scopeAllowed($query)
    {
        return $query;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public static function validationMessages()
    {
        return [
        ];
    }//end validationMessages()

    /**
     * Return all validation rules
     *
     * @return array
     */
    protected function getRules()
    {
        $rules = [
            'value' => ['integer'],
        ];
        return [parent::getRules(), $rules];
    }//end getRules()
}//end class
