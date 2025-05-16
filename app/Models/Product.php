<?php

namespace App\Models;

use Dflydev\DotAccessData\Util;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();
        //created
        self::created(function ($m) {});

        //cerating
        self::creating(function ($m) {
            $pro_with_same_vid = Product::where('local_id', $m->local_id)->first();
            if ($pro_with_same_vid != null) {
                throw new \Exception("Product with same local_id already exists", 1);
            }
        });

        //updating
        self::updating(function ($m) {

            return $m;
        });
        //updated
        self::updated(function ($m) {
            $m->sync(Utils::get_stripe());
        });

        self::deleting(function ($m) {
            try {
                $imgs = Image::where('parent_id', $m->id)->orwhere('product_id', $m->id)->get();
                foreach ($imgs as $img) {
                    $img->delete();
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        });
    }

    //getter for feature_photo
    public function getFeaturePhotoAttribute($value)
    {

        //check if value contains images/
        if (str_contains($value, 'images/')) {
            return $value;
        }
        $value = 'images/' . $value;
        return $value;
    }

    //getter for price_2
    public function getPrice2Attribute($value)
    {
        if ($value == null || $value == 0 || strlen($value) < 1) {
            $p1 = ((int)($this->price_1));
            //10 of p1
            $discount = $p1 * 0.1;
            $value = $p1 + $discount;
        }
        return $value;
    }


    public function update_stripe_price($new_price)
    {

        $new_price = null;
        $stripe = Utils::get_stripe();
        set_time_limit(-1);
        try {
            $new_price = $stripe->prices->create([
                'currency' => 'cad',
                'unit_amount' => $this->price_1 * 100,
                'product' => $this->stripe_id,
            ]);
        } catch (\Throwable $th) {
            throw $th->getMessage();
        }
        if ($new_price == null) {
            throw new \Exception("Error Processing Request", 1);
        }

        $resp = null;
        try {
            $resp = $stripe->products->update(
                $this->stripe_id,
                [
                    'default_price' => $this->stripe_price,
                    'name' => 'Muhindo mubaraka test',
                ]
            );
        } catch (\Throwable $th) {
            throw $th->getMessage();
        }
        if ($resp == null) {
            throw new \Exception("Error Processing Request", 1);
        }


        if ($resp->default_price != null) {
            return $resp->default_price;
        } else {
            throw new \Exception("Error Processing Request", 1);
        }
    }

    public function sync($stripe)
    {

        return;
    }
    public function getRatesAttribute()
    {
        $imgs = Image::where('parent_id', $this->id)->orwhere('product_id', $this->id)->get();
        return json_encode($imgs);
    }


    protected $appends = ['category_text'];
    public function getCategoryTextAttribute()
    {
        $d = ProductCategory::find($this->category);
        if ($d == null) {
            return 'Not Category.';
        }
        return $d->category;
    }

    //getter for colors from json
    public function getColorsAttribute($value)
    {
        $resp = str_replace('\"', '"', $value);
        $resp = str_replace('[', '', $resp);
        $resp = str_replace(']', '', $resp);
        $resp = str_replace('"', '', $resp);
        return $resp;
    }

    //setter for colors to json
    public function setColorsAttribute($value)
    {
        if ($value != null) {
            if (strlen($value) > 2) {
                $value = json_encode($value);
                $this->attributes['colors'] = $value;
            }
        }
    }

    //sett keywords to json
    public function setKeywordsAttribute($value)
    {
        if ($value != null) {
            if (strlen($value) > 2) {
                $value = json_encode($value);
                $this->attributes['keywords'] = $value;
            }
        }
    }

    //getter for keywords from json
    public function getKeywordsAttribute($value)
    {
        if ($value == null) {
            return [];
        }

        try {
            $resp = json_decode($value);
            return $resp;
        } catch (\Throwable $th) {
            return [];
        }

        return $resp;
    }


    //getter for sizes
    public function getSizesAttribute($value)
    {
        $resp = str_replace('\"', '"', $value);
        $resp = str_replace('[', '', $resp);
        $resp = str_replace(']', '', $resp);
        $resp = str_replace('"', '', $resp);
        return $resp;
    }

    //setter for sizes
    public function setSizesAttribute($value)
    {
        if ($value != null) {
            if (strlen($value) > 2) {
                $value = json_encode($value);
                $this->attributes['sizes'] = $value;
            }
        }
    }

    //has many Image
    public function images()
    {
        return $this->hasMany(Image::class, 'product_id', 'id');
    }


    protected $casts = [
        'summary' => 'json',
    ];
}
