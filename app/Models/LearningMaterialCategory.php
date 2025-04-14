<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningMaterialCategory extends Model
{
    use HasFactory;

    //boot
    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->name = str_replace('Resources | Sharebility Uganda', '', $model->name);
            $model->name = str_replace('Resources', '', $model->name);
            $model->name = str_replace('Sharebility Uganda', '', $model->name);
            $model->name = str_replace('| Sharebility', '', $model->name);
            $model->name = str_replace('Sharebility', '', $model->name);
            $model->name = trim($model->name);

            //description replace Sharebility with Schooldynamics
            $model->description = str_replace('Sharebility', 'Schooldynamics', $model->description);
            $model->short_description = str_replace('Sharebility', 'Schooldynamics', $model->short_description);

            //html decode
            $model->description = html_entity_decode($model->description);
            $model->short_description = html_entity_decode($model->short_description);
            $model->name = html_entity_decode($model->name);


            //check if external_url already exists
            if (self::where('external_url', $model->external_url)->exists()) {
                return false;
            }
            //check if external_id already exists
            if (self::where('external_id', $model->external_id)->exists()) {
                return false;
            } 
            //same name
            if (self::where('name', $model->name)->exists()) {
                throw new \Exception('Name already exists');
                return false;
            }
        });


    }
}
