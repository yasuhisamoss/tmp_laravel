<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jockey extends Model
{
    use HasFactory;
    // タイムスタンプなし
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'jockey_id',
        'jockey_name'
    ];
    protected $primaryKey = 'jockey_id';
    protected $keyType = 'string';
}
