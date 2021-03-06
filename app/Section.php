<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'teacher_id',
        'section_code',
        'section_name'
    ];

    public function routeConfig()
    {
        return json_encode([
            'update' => route('section.update', '@id'),
            'store' => route('section.store'),
            'destroy' => route('section.destroy', '@id'),
            'index' => route('section.index'),
            'show' => route('section.show', '@id'),
            'teacher_section' => route('teacher.section', '@id'),
            'store_exam' => route('teacher.exam'),
            'update_exam' => route('teacher.exam.update'),
            'show_exam' => route('teacher.exam.show', '@id'),
            'destroy_exam' => route('teacher.exam.destroy', '@id'),
            'done_exam' => route('teacher.exam.done'),
            'show_exam_result' => route('teacher.exam.show.result', '@id'),
        ]);
    }

    public function teacher_id()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}
