<?php

namespace App\Admin\Actions\Post;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class MovieStatusChange extends BatchAction
{
    public $name = 'Change Movie Status';
    public $icon = 'fa fa-check';


    public function handle(Collection $collection, Request $r)
    {
        $i = 0;
        foreach ($collection as $model) {
            if ($r->has('status')) {
                $model->status = $r->get('status');
            }

            if ($r->has('video_url_tested_by_curl')) {
                $model->video_url_tested_by_curl = $r->get('video_url_tested_by_curl');
            }
            if ($r->has('video_url_tested_by_curl_works')) {
                $model->video_url_tested_by_curl_works = $r->get('video_url_tested_by_curl_works');
            }
            if ($r->has('video_url_tested_by_human')) {
                $model->video_url_tested_by_human = $r->get('video_url_tested_by_human');
            }
            if ($r->has('video_url_tested_by_human_works')) {
                $model->video_url_tested_by_human_works = $r->get('video_url_tested_by_human_works');
            }
            if ($r->has('firebase_transfer_attempted')) {
                $model->firebase_transfer_attempted = $r->get('firebase_transfer_attempted');
            }
            if ($r->has('firebase_transfer_transfer_in_progress')) {
                $model->firebase_transfer_transfer_in_progress = $r->get('firebase_transfer_transfer_in_progress');
            }
            if ($r->has('firebase_transfer_successful')) {
                $model->firebase_transfer_successful = $r->get('firebase_transfer_successful');
            }
            if ($r->has('firebase_transfer_failure_reason')) {
                $model->firebase_transfer_failure_reason = $r->get('firebase_transfer_failure_reason');
            }
            if ($r->has('firebase_transfer_path')) {
                $model->firebase_transfer_path = $r->get('firebase_transfer_path');
            }
            if ($r->has('firebase_video_url')) {
                $model->firebase_video_url = $r->get('firebase_video_url');
            }
            if ($r->has('firebase_video_url_expires_at')) {
                $model->firebase_video_url_expires_at = $r->get('firebase_video_url_expires_at');
            }
            if ($r->has('firebase_video_tested_by_curl')) {
                $model->firebase_video_tested_by_curl = $r->get('firebase_video_tested_by_curl');
            }
            if ($r->has('firebase_video_tested_by_curl_works')) {
                $model->firebase_video_tested_by_curl_works = $r->get('firebase_video_tested_by_curl_works');
            }
            if ($r->has('firebase_video_tested_by_human')) {
                $model->firebase_video_tested_by_human = $r->get('firebase_video_tested_by_human');
            }
            if ($r->has('firebase_video_tested_by_human_works')) {
                $model->firebase_video_tested_by_human_works = $r->get('firebase_video_tested_by_human_works');
            }

            $model->save();
            $i++;
        }
        return $this->response()->success("Updated $i status to " . $r->get('status') . " successfully.")->refresh();
    }

    public function form()
    {
        $this->select('status', __('Status'))
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive']);

        $this->select('video_url_tested_by_curl', __('Video URL Tested by Curl'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('video_url_tested_by_curl_works', __('Video URL Curl Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('video_url_tested_by_human', __('Video URL Tested by Human'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('video_url_tested_by_human_works', __('Video URL Human Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_transfer_attempted', __('Firebase Transfer Attempted'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_transfer_transfer_in_progress', __('Firebase Transfer In Progress'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_transfer_successful', __('Firebase Transfer Successful'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->text('firebase_transfer_failure_reason', __('Firebase Transfer Failure Reason'));

        $this->text('firebase_transfer_path', __('Firebase Transfer Path'));

        $this->text('firebase_video_url', __('Firebase Video URL'));

        $this->datetime('firebase_video_url_expires_at', __('Firebase Video URL Expires At'));

        $this->select('firebase_video_tested_by_curl', __('Firebase Video Tested by Curl'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_video_tested_by_curl_works', __('Firebase Video Curl Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_video_tested_by_human', __('Firebase Video Tested by Human'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);

        $this->select('firebase_video_tested_by_human_works', __('Firebase Video Human Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No']);
    }
}
