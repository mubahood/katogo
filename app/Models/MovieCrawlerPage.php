<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

include_once('simple_html_dom.php');


class MovieCrawlerPage extends Model
{
    use HasFactory;

    public function fetch_page_content()
    {
        // dd($this->page_content);
        $data = null;
        $this->status = 'pending';
        try {
            $data = Utils::get_url($this->url);
        } catch (\Throwable $th) {
            $this->error_message = $th->getMessage();
            $this->status = 'error';
            $this->save();
            return;
        }
        $this->page_content = $data;
        $this->save();
        $this->process_page_content();
    }

    public function process_page_content()
    {
        if ($this->movie_crawler_website == null) {
            $this->status = 'error';
            $this->error_message = "Movie site not found";
            $this->save();
            return;
        }
        if ($this->page_content == null) {
            $this->status = 'error';
            $this->error_message = "Page content is empty";
            $this->save();
            return;
        }
        if ($this->movie_crawler_website->slug == MovieCrawlerWebsite::MY_VJ) {
            $this->process_my_vj();
        } else {
            $this->status = 'error';
            $this->error_message = "Slug not found When processing page content";
            $this->save();
        }
    }

    //belongs to 
    public function job_web_site()
    {
        return $this->belongsTo(MovieCrawlerWebsite::class);
    }

    public function movie_crawler_website()
    {
        return $this->belongsTo(MovieCrawlerWebsite::class);
    }



    public function process_my_vj()
    {



        $this->status = 'error';
        $existing_post = MovieModel::where('external_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }
        $existing_post = MovieModel::where('external_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('page_source_url', $this->url)->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this external URL';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('title', $this->title)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this title';
            $this->save();
            return;
        }

        $existing_post = MovieModel::where('title', $this->title . ' - ' . $this->vj)
            ->where('status', 'Active')
            ->first();
        if ($existing_post != null) {
            $this->error_message = 'Movie already exists with this title';
            $this->save();
            return;
        }


        //clean page_content html
        $page_content = trim($this->page_content);
        $page_content = preg_replace('/\s+/', ' ', $page_content);
        $page_content = str_replace("\n", ' ', $page_content);
        $page_content = str_replace("\r", ' ', $page_content);
        $page_content = str_replace("\t", ' ', $page_content);
        $this->page_content = $page_content;

        $html = null;
        try {
            $html = str_get_html(trim($this->page_content), true, true);
        } catch (\Throwable $th) {
            throw $th;
        }
        if ($html == null) {
            $this->status = 'error';
            $this->error_message = "Failed to parse HTML content";
            $this->save();
            return;
        }

        if ($html == false) {
            $this->status = 'error';
            $this->error_message = "Failed to parse HTML content";
            $this->save();
            return;
        }

        $newMovie = new MovieModel();
        /* 
            "id" => 5
    "created_at" => "2025-09-30 03:54:01"
    "updated_at" => "2025-09-30 04:38:39"
    "movie_crawler_website_id" => 1
    "title" => "Before I Go to Sleep"
    "slug" => "before-i-go-to-sleep0"
    "url" => "https://ugawatch.com/watch/before-i-go-to-sleep0"
    "movie_id" => null
    "page_content" => "<!doctype html> <html lang="en-US"> <!-- 23:55:48 GMT --> <head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1, maxi ▶"
    "error_message" => "Movie site not found"
    "status" => "error"
    "last_fetched_at" => null
    "type" => "Movie"
    "row_id" => "-999759"
    "img_port_muno_file_name" => "545855430067.jpg"
    "bunny_file_name" => "m_mn_22699.mp4"
    "tmdb_poster_path" => "/1sSWpTupVixOanIpZdLGzsKvcZS.jpg"
    "vj" => "Vj Junior"
        */
        // Initialize movie fields from scraped HTML content
        try {
            $video_url = null;

            // Method 1: Look for video tag with source
            $videoObj = $html->find('video source', 0);
            if ($videoObj != null) {
                $video_url = $videoObj->src;
            }

            // Method 2: Look for download button
            if ($video_url == null) {
                $buttons = $html->find('button');
                foreach ($buttons as $button) {
                    if (str_contains(strtolower($button->plaintext), 'download')) {
                        try {
                            $video_url = $button->parent()->href;
                        } catch (\Throwable $th) {
                            // Continue searching
                        }
                        if ($video_url != null) break;
                    }
                }
            }

            // Method 3: Look for direct video file links
            if ($video_url == null) {
                $links = $html->find('a');
                foreach ($links as $link) {
                    $href = $link->href;
                    if ($href && (
                        str_contains($href, '.mp4') ||
                        str_contains($href, '.mkv') ||
                        str_contains($href, '.avi') ||
                        str_contains($href, '.mov') ||
                        str_contains($href, '.webm')
                    )) {
                        $video_url = $href;
                        break;
                    }
                }
            }

            if ($video_url != null) {
                // Ensure absolute URL
                if (!str_contains($video_url, 'http')) {
                    $parsed_url = parse_url($this->url);
                    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    $video_url = $base_url . '/' . ltrim($video_url, '/');
                }
                $newMovie->url = $video_url;
                $newMovie->external_url = $video_url;
            }
            $newMovie->title = trim($this->title);
            $newMovie->page_source_url = trim($this->url);
            $newMovie->image_url = 'https://image.tmdb.org/t/p/w500/' . trim($this->tmdb_poster_path);
            $newMovie->thumbnail_url = 'https://image.tmdb.org/t/p/w500/' . trim($this->tmdb_poster_path);
            $description = '';
            $descriptions = $html->find('.movie__short-description');
            foreach ($descriptions as $desc) {
                $text = trim($desc->plaintext);
                if ($text) {
                    if (strlen($text) > 5) {
                        if (strlen($text) > strlen($description)) {
                            $description = $text;
                        }
                    }
                }
            }
            $newMovie->description = $description;
            //.movie__meta--release-year
            $year = null;
            $yearObjs = $html->find('.movie__meta--release-year');
            if ($yearObjs != null) {
                foreach ($yearObjs as $yearObj) {
                    $year_text = trim($yearObj->plaintext);
                    if (strlen($year_text) > 3) {
                        if (is_numeric($year_text) && strlen($year_text) == 4) {
                            $year = (int)$year_text;
                            break;
                        }
                    }
                }
            }
            $newMovie->year = $year;
            $newMovie->vj = $this->vj;
            $temp_title = strtolower($newMovie->title);
            //check if tille has no word vj and add it
            if (strpos($temp_title, 'vj') === false && strpos($newMovie->title, 'Vj') === false) {
                if ($this->vj != null) {
                    $newMovie->title = $newMovie->title . ' - ' . $this->vj;
                }
            }

            //.movie__meta--genre
            $movie__meta_genres = $html->find('.movie__meta--genre');
            $genre = null;
            $newMovie->genre = $newMovie->vj;
            if ($movie__meta_genres != null) {
                foreach ($movie__meta_genres as $genreObj) {
                    $genre_text = trim($genreObj->plaintext);
                    if (strlen($genre_text) > 2) {
                        $genre = $genre_text;
                        //check if it contains , and split, take the first only trimed
                        if (strpos($genre, ',') !== false) {
                            $parts = explode(',', $genre);
                            if (count($parts) > 0) {
                                $genre = trim($parts[0]);
                            }
                        }
                        break;
                    }
                }
            }
            $newMovie->genre = $genre;
            $newMovie->category = $genre;
            $newMovie->status = 'Active';
            $newMovie->imdb_id = $this->row_id;
            $newMovie->stars = 'MyVj';
            $newMovie->imdb_url = 'MyVj';
            $newMovie->is_processed = 'No';
            $newMovie->downloads_count = 0;
            $newMovie->views_count = 0;
            $newMovie->likes_count = 0;
            $newMovie->dislikes_count = 0;
            $newMovie->comments_count = 0;
            $newMovie->video_url_tested_by_curl = 'Yes';
            $newMovie->video_url_tested_by_curl_works = 'Yes';
            $newMovie->video_url_tested_by_human = 'Yes';
            $newMovie->video_url_tested_by_human_works = 'Yes';
            $newMovie->firebase_transfer_attempted = 'No';

            try {
                $newMovie->save();
            } catch (\Throwable $th) {
                throw $th;
            }
            // Update crawler page status
            $this->status = 'success';
            $this->error_message = null;
            $this->save();
        } catch (\Throwable $th) {
            $this->status = 'error';
            $this->error_message = 'Error processing movie data: ' . $th->getMessage();
            $this->save();
            throw $th;
        }
    }
}
