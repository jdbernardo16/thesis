<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [
        'stage_id',
        'type',
        'title',
        'body_html',
        'cover_path',
        'youtube_video_id',
        'transcript',
        'must_watch_pct',
    ];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }
}
