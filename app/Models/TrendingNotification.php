<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendingNotification extends Model
{
    use HasFactory;


    public static function getTendingMovie(){
        $now = Carbon::now();
        $day_time = '';
        //get current time
        if ($now->hour >= 6 && $now->hour < 12) {
            $day_time = 'morning';
        } elseif ($now->hour >= 12 && $now->hour < 18) {
            $day_time = 'afternoon';
        } elseif ($now->hour >= 18 && $now->hour < 24) {
            $day_time = 'evening';
        } else {
            $day_time = 'night';
        }
    
        //get trending of this time for today
        $trending = TrendingNotification::whereDate('created_at', Carbon::today())
            ->where('day_time', $day_time)
            ->first();
        if($trending == null){
            $newTrending = new TrendingNotification();
            $newTrending->day_time = $day_time;
            $newTrending->created_at = Carbon::now();
            $newTrending->updated_at = Carbon::now();
            $newTrending->is_sent = 'No';
            $newTrending->save();
            $trending = TrendingNotification::whereDate('created_at', Carbon::today())
            ->where('type', 'Movie')
            ->where('day_time', $day_time)
            ->first();
        }
        $min_secs = 30*60;

        if($trending->movie == null){
            $movie = MovieModel::where('is_trending','!=', 'Yes')
            ->where('type', 'Movie')
            ->where('views_time_count', '>=', $min_secs)
            ->orderBy('views_time_count', 'desc')
            ->first();
            if($movie == null){
                //set all movies to is_trending = No
                MovieModel::where('is_trending', 'Yes')
                ->update(['is_trending' => 'No']);
                $movie = MovieModel::where('is_trending','!=', 'Yes')
                ->where('views_time_count', '>=', $min_secs)
                ->where('type', 'Movie')
                ->orderBy('views_time_count', 'desc')
                ->first();
                if($movie == null){
                    //GET ANY LATEST MOVIE
                    $movie = MovieModel::where('is_trending','!=', 'Yes')
                            ->where('type', 'Movie')
                            ->orderBy('created_at', 'desc')
                            ->first();
                }
            }
            if($movie!= null){
                $movie->is_trending = 'Yes';
                $movie->trending_time = Carbon::now();
                $movie->trending_id = $trending->id;
                $movie->save();
                $trending->movie_model_id = $movie->id;
                $trending->title = $movie->title;
                $trending->type = $movie->type;
                $trending->image_url = $movie->thumbnail_url;
                $trending->description = $movie->description;
                $trending->views_count = $movie->views_count;
                $trending->views_time = $movie->views_time_count;
                $trending->url = $movie->url;
                $trending->trending_time = Carbon::now();
                $trending->save();
            }
        }

        if($trending->is_sent != 'Yes'){
            Utils::sendNotificationToAll([
                'title' => $trending->title,
                'body' => 'Don\'t miss the trending movie of today\'s ' . $day_time. '! - ' . $trending->title,
                'image' => $trending->image_url,
                'url' => $trending->url,
                'type' => $trending->type,
                'movie_id' => $trending->movie_model_id,
                'is_trending' => 'Yes',
                'data' => [
                    'movie_id' => $trending->movie_model_id,
                    'is_trending' => 'Yes',
                    'type' => $trending->type,
                    'url' => $trending->url,
                    'image_url' => $trending->image_url,
                ],
            ]);
        }
        die("done");
        /* 
           "is_sent" => "No"
    "sent_time" => null
        */

        dd($trending);
/* 
            $table->string('is_trending')->default('No')->nullable();
            $table->dateTime('trending_time')->nullable();
            $table->integer('trending_id')->nullable();
*/

        dd('getTendingMovie');
    }

    //belongs to movie
    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_model_id');
    }
}
