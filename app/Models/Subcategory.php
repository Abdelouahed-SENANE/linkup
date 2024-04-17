<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
    ];
    public function category() {
        return $this->belongsTo(Category::class);
    }
    public function gigs(){
        $this->hasMany(Gig::class , 'subcategory_id');
    }
}
