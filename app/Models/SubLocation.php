<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'facility_id'
    ];

    public function location()
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'sub_location_id');
    }
}
