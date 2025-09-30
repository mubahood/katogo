<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovieCrawlerWebsite extends Model
{
    use HasFactory;

    //constant for fetch status
    const MY_VJ = 'my-vj';

    public function get_next_page_content()
    {

        /*         $last = MovieCrawlerWebsite::where('id', $this->id)->first();
        $last->process_pages();
 */
        $this->fetch_status = 'in_progress';
        $this->error_message = null;
        $this->fetch_status = 'in_progress';
        $this->last_fetched_at = Carbon::now();
        $this->get_next_page_link();
        try {
            $my_html = Utils::get_url($this->last_page_url);
        } catch (\Throwable $th) {
            $this->status = 'failed';
            $this->error_message = $th->getMessage();
            throw $th;
        }
        $this->fetch_status = 'success';
        $this->last_fetched_at = Carbon::now();
        $this->response_data = $my_html;

        $this->save();
        $this->process_pages();
    }



    public function get_next_page_link()
    {
        if ($this->slug == self::MY_VJ) {
            $page_number = (int)$this->page_number + 1;
            if ($page_number > $this->max_page) {
                if ($this->max_page > 50) {
                    $this->max_page = 49;
                }
                $page_number = 0;
            }
            $this->page_number = $page_number;
            $this->last_page_url = $this->url . $page_number;
            return str_replace('{page_number}', $this->page_number, $this->url);
        } else {
            throw new \Exception('Invalid slug');
        }
    }



    public function process_pages()
    {
        $html = str_get_html($this->response_data);
        $jobLinks = [];
        $jobLinksNew = [];
        if ($this->slug == self::MY_VJ) {
            $jsonObject = null;
            try {
                $jsonObject = json_decode($this->response_data);
            } catch (\Throwable $th) {
                throw $th;
            }
            if ($jsonObject == null) {
                throw new \Exception('Failed to decode JSON response');
            }
            if ($jsonObject->movies == null) {
                throw new \Exception('No movies found in JSON response');
            }
            if (!is_array($jsonObject->movies)) {
                throw new \Exception('Movies is not an array in JSON response');
            }
            foreach ($jsonObject->movies as $key => $movieObject) {

                $url1 = 'https://ugawatch.com/watch/' . $movieObject->slug;
                $url2 = 'https://myvj.net/watch/' . $movieObject->slug;

                $page = MovieCrawlerPage::where('url', $url1)->first();
                if ($page != null) {
                    continue;
                }

                $page = MovieCrawlerPage::where('url', $url2)->first();
                if ($page != null) {
                    continue;
                }


                /* 
                  +"row_id": "-997063"
  +"slug": "curvature"
  +"title": "Curvature"
  +"img_port_muno_file_name": "daef.jpg"
  +"bunny_file_name": "m_mn_2358.mp4"
  +"tmdb_poster_path": "/wQ1CwqwGCNZHAkMC0HyEoWYJwzI.jpg"
  +"trailer": "m_mn_2358.mp4"
  +"height": "576"
  +"thumbs_up": "0"
  +"thumbs_down": "0"
  +"categories": "-"
  +"vj": "VJ Kevin"
  +"watch_percentage": null
  +"__playback_position_last_updated": null
  +"playback_position": null
                */
                $page = new MovieCrawlerPage();
                $page->url = $url1;
                $page->movie_crawler_website_id = $this->id;
                $page->title = $movieObject->title;
                $page->status = 'pending';
                $page->slug = $movieObject->slug;
                $page->movie_id = null;
                $page->page_content = null;
                $page->error_message = null;
                $page->last_fetched_at = null;
                //check if url contains 'explore-movies'
                $isMovie = false;
                if (strpos($this->url, 'explore-movies') !== false) {
                    $isMovie = true;
                    $page->type = 'Movie';
                } else {
                    $page->type = 'Series';
                }
                $page->row_id = $movieObject->row_id;
                $page->img_port_muno_file_name = $movieObject->img_port_muno_file_name;
                $page->bunny_file_name = $movieObject->bunny_file_name;
                $page->tmdb_poster_path = $movieObject->tmdb_poster_path;
                $page->vj = $movieObject->vj;
                $page->save();
                $jobLinksNew[] = $url1;
            }
        } else {
            throw new \Exception('Invalid slug when processing pages');
        }

        $this->last_fetched_at = Carbon::now();
        $this->total_movies_found = count($jobLinks);
        $this->new_movies_found = count($jobLinksNew);
        $this->fetch_status = "success";
        $this->error_message = null;
        try {
            $this->save();
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
