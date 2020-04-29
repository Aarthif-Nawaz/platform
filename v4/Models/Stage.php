<?php

namespace v4\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $table = 'form_stages';
    /**
     * The attributes that should be mutated to dates.
     * @var array
    */
    protected $dates = ['created', 'updated'];
    protected $with = ['attributes'];
    /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'form_id',
        'label',
        'priority',
        'icon',
        'required',
        'type',
        'description',
        'show_when_published',
        'task_is_internal_only'
    ];

    public function attributes()
    {
        return $this->hasMany('v4\Models\Attribute', 'form_stage_id');
    }

    public function survey() {
        return $this->belongsTo('v4\Models\Survey', 'form_id');
    }

}
