<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $table = 'product_categories';
    protected $casts = [
        'attributes' => 'json',
    ];

    //getter for updated_at
    /* public function getUpdatedAtAttribute($value)
    {
        return Product::where('category_id', $this->id)->count();
    } */

    public function getCategoryTextAttribute($value)
    {
        return Product::where('category', $this->id)->count();
    }

    //appends category_text
    protected $appends = ['category_text'];
}
