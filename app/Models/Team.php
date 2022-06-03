<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;
    protected $fillable = [
        'team_name',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function hasUsers()
    {
        return $this->hasMany(User::class);
    }
}
