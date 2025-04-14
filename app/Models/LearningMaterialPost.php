<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningMaterialPost extends Model
{
    use HasFactory;
    //boot
    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->title = str_replace('Resources | Sharebility Uganda', '', $model->title);
            $model->title = str_replace('Resources', '', $model->title);
            $model->title = str_replace('Sharebility Uganda', '', $model->title);
            $model->title = str_replace('| Sharebility', '', $model->title);
            $model->title = str_replace('Sharebility', '', $model->title);
            $model->title = trim($model->title);

            //description replace Sharebility with Schooldynamics
            $model->description = str_replace('Sharebility', 'Schooldynamics', $model->description);
            $model->short_description = str_replace('Sharebility', 'Schooldynamics', $model->short_description);

            //html decode
            $model->description = html_entity_decode($model->description);
            $model->short_description = html_entity_decode($model->short_description);
            $model->title = html_entity_decode($model->title);

            //check if external_url already exists
            if (self::where('external_url', $model->external_url)->exists()) {
                return false;
            }
            //check if external_id already exists
            if (self::where('external_id', $model->external_id)->exists()) {
                return false;
            }
        });
    }
}
