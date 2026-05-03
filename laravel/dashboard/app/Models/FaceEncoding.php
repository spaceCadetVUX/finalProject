<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceEncoding extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'encoding', 'image_path', 'created_at'];

    protected $casts = [
        'encoding'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
