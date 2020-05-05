<?php

namespace v4\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    public $timestamps = FALSE;

    protected $table = 'form_attributes';
    /**
     * The attributes that should be mutated to dates.
     * @var array
    */
    protected $dates = ['created', 'updated'];

    /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'key',
        'label',
        'instructions',
        'input',
        'type',
        'required',
        'default',
        'priority',
        'options',
        'cardinality',
        'config',
        'response_private',
        'form_stage_id'
    ];
    protected $casts = [
        'config' => 'json',
        'options' => 'json',
    ];
    public function stage () {
        return $this->belongsTo('v4\Models\Stage', 'form_stage_id');
    }

}
