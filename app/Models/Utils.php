<?php

namespace App\Models;

use Berkayk\OneSignal\OneSignalFacade;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

include_once('simple_html_dom.php');

class Utils
{



    public static function get_platform()
    {
        $platform_type = 'android';
        if (isset($_GET['platform_type'])) {
            if ($_GET['platform_type'] == 'android') {
                $platform_type = 'android';
            } else if ($_GET['platform_type'] == 'ios') {
                $platform_type = 'ios';
            }
        }
        return $platform_type;
    }
    public static function upload_images_2($files, $is_single_file = false)
    {

        ini_set('memory_limit', '-1');
        if ($files == null || empty($files)) {
            return $is_single_file ? "" : [];
        }
        $uploaded_images = array();
        foreach ($files as $file) {

            if (
                isset($file['name']) &&
                isset($file['type']) &&
                isset($file['tmp_name']) &&
                isset($file['error']) &&
                isset($file['size'])
            ) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = time() . "-" . rand(100000, 1000000) . "." . $ext;
                $destination = Utils::docs_root() . '/storage/images/' . $file_name;

                $res = move_uploaded_file($file['tmp_name'], $destination);
                if (!$res) {
                    continue;
                }
                //$uploaded_images[] = $destination;
                $uploaded_images[] = $file_name;
            }
        }

        $single_file = "";
        if (isset($uploaded_images[0])) {
            $single_file = $uploaded_images[0];
        }


        return $is_single_file ? $single_file : $uploaded_images;
    }


    public static function create_thumbail($params = array())
    {

        ini_set('memory_limit', '-1');

        if (
            !isset($params['source']) ||
            !isset($params['target'])
        ) {
            return [];
        }



        if (!file_exists($params['source'])) {
            $img = url('assets/images/logo.png');
            return $img;
        }


        $image = new Zebra_Image();

        $image->auto_handle_exif_orientation = true;
        $image->source_path = "" . $params['source'];
        $image->target_path = "" . $params['target'];

        $size = filesize($image->source_path) / (1024 * 1024);
        if ($size < 1) {
            copy($params['source'], $params['target']);
            return;
        }

        if (isset($params['quality'])) {
            $image->jpeg_quality = $params['quality'];
        }

        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = true;
        $image->preserve_time = true;
        $image->handle_exif_orientation_tag = true;

        $img_size = getimagesize($image->source_path); // returns an array that is filled with info

        $image->jpeg_quality = Utils::get_jpeg_quality(filesize($image->source_path));
        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = true;
        if (!$image->resize(0, 0, ZEBRA_IMAGE_CROP_CENTER)) {
            return $image->source_path;
        } else {
            return $image->target_path;
        }
    }



    public static function docs_root()
    {
        return public_path();
        $r = $_SERVER['DOCUMENT_ROOT'] . "";

        if (str_contains($r, 'htdocs')) {
            $folder = env('APP_FOLDER');
            return  $folder . "/public";
        }

        if (!str_contains($r, 'home/')) {
            $r = str_replace('/public', "", $r);
            $r = str_replace('\public', "", $r);
        }

        if (!(str_contains($r, 'public'))) {
            $r = $r . "/public";
        }


        /* 
         "/home/ulitscom_html/public/storage/images/956000011639246-(m).JPG
        
        public_html/public/storage/images
        */
        return $r;
    }





    //time_ago static function
    public static function time_ago($datetime, $full = false)
    {
        if ($datetime == null) {
            return null;
        }
        if ($datetime == '0000-00-00 00:00:00' || $datetime == '0000-00-00') {
            return null;
        }
        $ago = null;
        try {
            $ago = Carbon::createFromFormat('Y-m-d H:i:s', $datetime);
        } catch (\Throwable $th) {
            try {
                $ago = Carbon::createFromFormat('Y-m-d', $datetime);
            } catch (\Throwable $th) {
                return null;
            }
        }
        $now = Carbon::now();
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                if ($k == 'y') {
                    $v = $diff->$k . " " . ($diff->$k > 1 ? $v . "s" : $v);
                } else {
                    $v = $diff->$k . " " . ($diff->$k > 1 ? $v . "s" : $v);
                }
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            return array_shift($string);
        }

        return count($string) > 1 ? implode(', ', array_slice($string, 0, -1)) . ' and ' . array_pop($string) : array_pop($string);
    }

    public static function sendNotificationToAll($params)
    {
        $title = env('APP_NAME');
        $body = null;
        $url = 'https://katogo.schooldynamics.ug/logo.png';
        $img = null;
        $type = null;
        $movie_id = null;
        $data = [];
        $buttons = [];
        $schedule = null;
        if (isset($params['title'])) {
            $title = $params['title'];
        }
        if (isset($params['body'])) {
            $body = $params['body'];
        }
        if ($body == null) {
            throw new Exception("Body is required.", 1);
        }
        if (isset($params['url'])) {
            $url = $params['url'];
        }
        if (isset($params['image'])) {
            $img = $params['image'];
        }
        if (isset($params['data'])) {
            $data = $params['data'];
        }
        if (isset($params['buttons'])) {
            $buttons = $buttons['data'];
        }
        if (isset($params['schedule'])) {
            $schedule = $buttons['schedule'];
        }

        $url = null;
        try {
            OneSignalFacade::addParams(
                [
                    'android_channel_id' => '63c01a80-0acd-467b-bdc7-7a1107141a8b',
                    'large_icon' => $img,
                    'big_picture' => $img,
                    'chrome_big_picture' => $img,
                    'chrome_web_image' => $img,
                    'small_icon' => 'logo',
                ]
            )->sendNotificationToAll(
                $body,
                $url = $url,
                $data = $data,
                $buttons = $buttons,
                $schedule =  $schedule,
                $headings = $title,
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }


    //send notification to user
    public static function sendNotificationToUser($user, $params)
    {
        $title = env('APP_NAME');
        $body = null;
        $url = 'https://katogo.schooldynamics.ug/logo.png';
        $img = null;
        $type = null;
        $movie_id = null;
        $data = [];
        if (isset($params['title'])) {
            $title = $params['title'];
        }
        if (isset($params['body'])) {
            $body = $params['body'];
        }
        if ($body == null) {
            throw new Exception("Body is required.", 1);
        }
        if (isset($params['url'])) {
            $url = $params['url'];
        }
        if (isset($params['image'])) {
            $img = $params['image'];
        }
        if (isset($params['data'])) {
            $data = $params['data'];
        }
        $title = null;
        if (isset($params['title'])) {
            $title = $params['title'];
        }

        try {
            OneSignalFacade::addParams(
                [
                    'android_channel_id' => '63c01a80-0acd-467b-bdc7-7a1107141a8b',
                    'large_icon' => $img,
                    'big_picture' => $img,
                    'chrome_big_picture' => $img,
                    'chrome_web_image' => $img,
                    'small_icon' => 'logo',
                ]
            )->sendNotificationToUser(
                'my-id-' . $user->id,
                $body,
                null,
                null,
                null,
                null,
                $headings = $title,
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }



    public static function escapeString($string)
    {
        return DB::getPdo()->quote($string);
    }


    //download movies from google
    public static function download_movies_from_google()
    {
        $name = "m.schooldynamics.ug/storage/videos/1707574562_74652.mp4";
        $names = explode('/', $name);
        if (count($names) > 1) {
            $last_name = $names[count($names) - 1];
        } else {
            $last_name = $name;
        }


        $items = Utils::getBucketItems('mubahood-movies');
        //if(not $items return empty array)
        if ($items == null) {
            return [];
        }
        //dd(count($items));
        $movies = [];
        foreach ($items as $item) {
            $name = $item['name'];
            $local_video_link = $name;
            $names = explode('/', $name);
            if (count($names) > 1) {
                $last_name = $names[count($names) - 1];
            } else {
                $last_name = $name;
            }
            $movie = MovieModel::where('external_url', 'like', '%' . $last_name . '%')
                ->where('downloaded_from_google', 'No')
                ->first();
            if ($movie == null) {
                //echo $last_name . ' not found<br>';
                continue;
            }
            $path = public_path('storage/' . $movie->url);
            if (file_exists($path)) {
                //delete the file
                try {
                    unlink($path);
                } catch (\Throwable $th) {
                    echo $th->getMessage();
                }
            }

            $movie->downloaded_from_google = 'Yes';
            $movie->uploaded_to_from_google = 'Yes';
            $movie->local_video_link = $local_video_link;
            $movie->url = $item['mediaLink'];
            $movie->save();
            echo $last_name . ' downloaded<br>';
        }
        die('done');
        return $movies;
    }


    /**
     * Fetches items from a specified bucket.
     *
     * @param string $bucketPath The relative path of the bucket.
     * @return array|null An array of items in the bucket, or null if an error occurred.
     */
    public static function getBucketItems($bucketPath)
    {
        // Retrieve the bearer token from the environment
        ///$bearerToken = env('GOOGLE_CLOUD_STORAGE_BEARER_TOKEN');
        $bearerToken = "ya29.a0AXooCgskgDuGJ_Lf38WZRdfik_fWXSseSUgMxI-o4N8SNoo6E9W1RY-KB11sGahoJy0Em6KXd7WB8Hp4XMMJH6_R-m-6jsMa0bJO6C0GH2wK039jueizDTtV40sjB3b74HFp8EvB0iAKw53khBDjRJCeiZFp6pbOJwaCgYKAbUSARMSFQHGX2MiBXJUS2YjTYxHYTihG7Fejg0169";

        // Google Cloud Storage API endpoint to list items in the specified bucket
        $maxResults = 10000;
        $url = 'https://storage.googleapis.com/storage/v1/b/' . $bucketPath . '/o?maxResults=' . $maxResults;

        // Make the HTTP GET request with the authorization header
        $response = Http::withToken($bearerToken)->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['items'])) {
                $items = [];
                foreach ($data['items'] as $item) {
                    $items[] = $item;
                }
                return $items;
            } else {
                echo "No items found in the bucket.\n";
                return null;
            }
        } else {
            echo "Failed to retrieve items from the bucket 2.\n";
            dd($response);
            return null;
        }
    }


    public static function refreshAccessToken()
    {
        // Read the credentials from the .env file
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $refreshToken = env('GOOGLE_REFRESH_TOKEN');

        $tokenUrl = "https://oauth2.googleapis.com/token";

        // Prepare the POST request parameters
        $params = [
            "client_id" => $clientId,
            "client_secret" => $clientSecret,
            "refresh_token" => $refreshToken,
            "grant_type" => "refresh_token"
        ];

        try {
            // Make the POST request using Laravel's Http client
            $response = Http::asForm()->post($tokenUrl, $params);

            if ($response->successful()) {
                $tokenInfo = $response->json();
                return [
                    'access_token' => $tokenInfo['access_token'],
                    'expires_in' => $tokenInfo['expires_in']
                ];
            } else {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }
        } catch (Exception $e) {
            throw new Exception('Error refreshing access token: ' . $e->getMessage());
        }
    }

    //mail sender
    public static function mail_sender($data)
    {
        try {
            Mail::send(
                'mails/mail-1',
                [
                    'body' => $data['body'],
                    'title' => $data['subject']
                ],
                function ($m) use ($data) {
                    $m->to($data['email'], $data['name'])
                        ->subject($data['subject']);
                    $m->from(env('MAIL_FROM_ADDRESS'), $data['subject']);
                }
            );
        } catch (\Throwable $th) {
            $msg = 'failed';
            throw $th;
        }
    }


    //coenvet secondsToMinutes 
    public static function secondsToMinutes($seconds)
    {
        if (!is_numeric($seconds) || $seconds < 0) {
            return '0:00';
        }
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    public static $JV = [
        'Junior' => 'Junior',
        'Jingo' => 'Jingo',
        'Muba' => 'Muba',
        'Kevo' => 'Kevo',
        'Baros' => 'Baros',
        'Ulio' => 'Ulio',
        'Emmy' => 'Emmy',
        'Ice p' => 'Ice p',
        'Ivo' => 'Ivo',
        'Shao Khani Lee' => 'Shao Khani Lee',
        'Unknown' => 'Unknown',
    ];
    public static $CATEGORIES = [
        'Action' => 'Action',
        'Adventure' => 'Adventure',
        'Animation' => 'Animation',
        'Biography' => 'Biography',
        'Comedy' => 'Comedy',
        'Crime' => 'Crime',
        'Documentary' => 'Documentary',
        'Drama' => 'Drama',
        'Family' => 'Family',
        'Fantasy' => 'Fantasy',
        'Game Show' => 'Game Show',
        'History' => 'History',
        'Horror' => 'Horror',
        'Music' => 'Music',
        'Musical' => 'Musical',
        'Mystery' => 'Mystery',
        'News' => 'News',
        'Reality-TV' => 'Reality-TV',
        'Romance' => 'Romance',
        'Sci-Fi' => 'Sci-Fi',
        'Sport' => 'Sport',
        'Talk-Show' => 'Talk-Show',
        'Thriller' => 'Thriller',
        'War' => 'War',
        'Western' => 'Western',
    ];

    public static function get_extension_from_mime($mime)
    {
        $mime = strtolower($mime);
        if ($mime == 'application/pdf') {
            return 'pdf';
        }
        if ($mime == 'application/msword') {
            return 'doc';
        }
        if ($mime == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return 'docx';
        }
        if ($mime == 'application/vnd.ms-excel') {
            return 'xls';
        }
        if ($mime == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            return 'xlsx';
        }
        if ($mime == 'application/vnd.ms-powerpoint') {
            return 'ppt';
        }
        if ($mime == 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
            return 'pptx';
        }
        if ($mime == 'application/zip') {
            return 'zip';
        }
        if ($mime == 'application/x-rar-compressed') {
            return 'rar';
        }
        if ($mime == 'application/x-7z-compressed') {
            return '7z';
        }
        if ($mime == 'application/x-tar') {
            return 'tar';
        }
        if ($mime == 'application/x-gzip') {
            return 'gz';
        }
        if ($mime == 'application/x-bzip2') {
            return 'bz2';
        }
        if ($mime == 'application/x-7z-compressed') {
            return '7z';
        }
        if ($mime == 'application/x-zip-compressed') {
            return 'zip';
        }
        if ($mime == 'application/x-rar-compressed') {
            return 'rar';
        }
        if ($mime == 'application/x-7z-compressed') {
            return '7z';
        }
        if ($mime == 'application/x-tar') {
            return 'tar';
        }
        if ($mime == 'application/x-gzip') {
            return 'gz';
        }
        if ($mime == 'application/x-bzip2') {
            return 'bz2';
        }
        if ($mime == 'application/x-7z-compressed') {
            return '7z';
        }
        if ($mime == 'application/x-tar') {
            return 'tar';
        }
        if ($mime == 'application/x-gzip') {
            return 'gz';
        }
        if ($mime == 'application/x-bzip2') {
            return 'bz2';
        }
        if ($mime == 'application/x-7z-compressed') {
            return '7z';
        }
        if ($mime == 'application/vnd.openxmlformats-officedocument.wordprocessingml.template') {
            return 'dotx';
        }
        if ($mime == 'application/octet-stream') {
            return 'png';
        }
        if ($mime == 'image/png') {
            return 'png';
        }
        if ($mime == 'image/jpeg') {
            return 'jpg';
        }
        if ($mime == 'image/gif') {
            return 'gif';
        }
        if ($mime == 'image/bmp') {
            return 'bmp';
        }
        if ($mime == 'image/tiff') {
            return 'tiff';
        }
        if ($mime == 'image/x-icon') {
            return 'ico';
        }
        if ($mime == 'image/svg+xml') {
            return 'svg';
        }
        if ($mime == 'image/vnd.adobe.photoshop') {
            return 'psd';
        }
        if ($mime == 'image/x-xcf') {
            return 'xcf';
        }
        if ($mime == 'image/x-pcx') {
            return 'pcx';
        }
        if ($mime == 'image/x-pict') {
            return 'pict';
        }
        if ($mime == 'application/vnd.mseq') {
            return 'mseq';
        }
        if ($mime == 'application/vnd.3m.post-it-notes') {
            return 'pwn';
        }
        if ($mime == 'application/msword') {
            return 'doc';
        }

        $data = '{
            "x3d": "application/vnd.hzn-3d-crossword",
            "3gp": "video/3gpp",
            "image/png": "png",
            "image/jpeg": "jpg",
            "image/gif": "gif",
            "video/3gpp": "3gp",
            "3g2": "video/3gpp2",
            "mseq": "application/vnd.mseq",
            "pwn": "application/vnd.3m.post-it-notes",
            "plb": "application/vnd.3gpp.pic-bw-large",
            "psb": "application/vnd.3gpp.pic-bw-small",
            "pvb": "application/vnd.3gpp.pic-bw-var",
            "tcap": "application/vnd.3gpp2.tcap",
            "7z": "application/x-7z-compressed",
            "abw": "application/x-abiword",
            "ace": "application/x-ace-compressed",
            "acc": "application/vnd.americandynamics.acc",
            "acu": "application/vnd.acucobol",
            "atc": "application/vnd.acucorp",
            "adp": "audio/adpcm",
            "aab": "application/x-authorware-bin",
            "aam": "application/x-authorware-map",
            "aas": "application/x-authorware-seg",
            "air": "application/vnd.adobe.air-application-installer-package+zip",
            "swf": "application/x-shockwave-flash",
            "fxp": "application/vnd.adobe.fxp",
            "pdf": "application/pdf",
            "ppd": "application/vnd.cups-ppd",
            "dir": "application/x-director",
            "xdp": "application/vnd.adobe.xdp+xml",
            "xfdf": "application/vnd.adobe.xfdf",
            "aac": "audio/x-aac",
            "ahead": "application/vnd.ahead.space",
            "azf": "application/vnd.airzip.filesecure.azf",
            "azs": "application/vnd.airzip.filesecure.azs",
            "azw": "application/vnd.amazon.ebook",
            "ami": "application/vnd.amiga.ami",
            "N/A": "application/andrew-inset",
            "apk": "application/vnd.android.package-archive",
            "cii": "application/vnd.anser-web-certificate-issue-initiation",
            "fti": "application/vnd.anser-web-funds-transfer-initiation",
            "atx": "application/vnd.antix.game-component",
            "dmg": "application/x-apple-diskimage",
            "mpkg": "application/vnd.apple.installer+xml",
            "aw": "application/applixware",
            "mp3": "audio/mpeg",
            "les": "application/vnd.hhe.lesson-player",
            "swi": "application/vnd.aristanetworks.swi",
            "s": "text/x-asm",
            "atomcat": "application/atomcat+xml",
            "atomsvc": "application/atomsvc+xml",
            "atom": "application/atom+xml",
            "ac": "application/pkix-attr-cert",
            "aif": "audio/x-aiff",
            "avi": "video/x-msvideo",
            "aep": "application/vnd.audiograph",
            "dxf": "image/vnd.dxf",
            "dwf": "model/vnd.dwf",
            "par": "text/plain-bas",
            "bcpio": "application/x-bcpio",
            "bin": "application/octet-stream",
            "bmp": "image/bmp",
            "torrent": "application/x-bittorrent",
            "cod": "application/vnd.rim.cod",
            "mpm": "application/vnd.blueice.multipass",
            "bmi": "application/vnd.bmi",
            "sh": "application/x-sh",
            "btif": "image/prs.btif",
            "rep": "application/vnd.businessobjects",
            "bz": "application/x-bzip",
            "bz2": "application/x-bzip2",
            "csh": "application/x-csh",
            "c": "text/x-c",
            "cdxml": "application/vnd.chemdraw+xml",
            "css": "text/css",
            "cdx": "chemical/x-cdx",
            "cml": "chemical/x-cml",
            "csml": "chemical/x-csml",
            "cdbcmsg": "application/vnd.contact.cmsg",
            "cla": "application/vnd.claymore",
            "c4g": "application/vnd.clonk.c4group",
            "sub": "image/vnd.dvb.subtitle",
            "cdmia": "application/cdmi-capability",
            "cdmic": "application/cdmi-container",
            "cdmid": "application/cdmi-domain",
            "cdmio": "application/cdmi-object",
            "cdmiq": "application/cdmi-queue",
            "c11amc": "application/vnd.cluetrust.cartomobile-config",
            "c11amz": "application/vnd.cluetrust.cartomobile-config-pkg",
            "ras": "image/x-cmu-raster",
            "dae": "model/vnd.collada+xml",
            "csv": "text/csv",
            "cpt": "application/mac-compactpro",
            "wmlc": "application/vnd.wap.wmlc",
            "cgm": "image/cgm",
            "ice": "x-conference/x-cooltalk",
            "cmx": "image/x-cmx",
            "xar": "application/vnd.xara",
            "cmc": "application/vnd.cosmocaller",
            "cpio": "application/x-cpio",
            "clkx": "application/vnd.crick.clicker",
            "clkk": "application/vnd.crick.clicker.keyboard",
            "clkp": "application/vnd.crick.clicker.palette",
            "clkt": "application/vnd.crick.clicker.template",
            "clkw": "application/vnd.crick.clicker.wordbank",
            "wbs": "application/vnd.criticaltools.wbs+xml",
            "cryptonote": "application/vnd.rig.cryptonote",
            "cif": "chemical/x-cif",
            "cmdf": "chemical/x-cmdf",
            "cu": "application/cu-seeme",
            "cww": "application/prs.cww",
            "curl": "text/vnd.curl",
            "dcurl": "text/vnd.curl.dcurl",
            "mcurl": "text/vnd.curl.mcurl",
            "scurl": "text/vnd.curl.scurl",
            "car": "application/vnd.curl.car",
            "pcurl": "application/vnd.curl.pcurl",
            "cmp": "application/vnd.yellowriver-custom-menu",
            "dssc": "application/dssc+der",
            "xdssc": "application/dssc+xml",
            "deb": "application/x-debian-package",
            "uva": "audio/vnd.dece.audio",
            "uvi": "image/vnd.dece.graphic",
            "uvh": "video/vnd.dece.hd",
            "uvm": "video/vnd.dece.mobile",
            "uvu": "video/vnd.uvvu.mp4",
            "uvp": "video/vnd.dece.pd",
            "uvs": "video/vnd.dece.sd",
            "uvv": "video/vnd.dece.video",
            "dvi": "application/x-dvi",
            "seed": "application/vnd.fdsn.seed",
            "dtb": "application/x-dtbook+xml",
            "res": "application/x-dtbresource+xml",
            "ait": "application/vnd.dvb.ait",
            "svc": "application/vnd.dvb.service",
            "eol": "audio/vnd.digital-winds",
            "djvu": "image/vnd.djvu",
            "dtd": "application/xml-dtd",
            "mlp": "application/vnd.dolby.mlp",
            "wad": "application/x-doom",
            "dpg": "application/vnd.dpgraph",
            "dra": "audio/vnd.dra",
            "dfac": "application/vnd.dreamfactory",
            "dts": "audio/vnd.dts",
            "dtshd": "audio/vnd.dts.hd",
            "dwg": "image/vnd.dwg",
            "geo": "application/vnd.dynageo",
            "es": "application/ecmascript",
            "mag": "application/vnd.ecowin.chart",
            "mmr": "image/vnd.fujixerox.edmics-mmr",
            "rlc": "image/vnd.fujixerox.edmics-rlc",
            "exi": "application/exi",
            "mgz": "application/vnd.proteus.magazine",
            "epub": "application/epub+zip",
            "eml": "message/rfc822",
            "nml": "application/vnd.enliven",
            "xpr": "application/vnd.is-xpr",
            "xif": "image/vnd.xiff",
            "xfdl": "application/vnd.xfdl",
            "emma": "application/emma+xml",
            "ez2": "application/vnd.ezpix-album",
            "ez3": "application/vnd.ezpix-package",
            "fst": "image/vnd.fst",
            "fvt": "video/vnd.fvt",
            "fbs": "image/vnd.fastbidsheet",
            "fe_launch": "application/vnd.denovo.fcselayout-link",
            "f4v": "video/x-f4v",
            "flv": "video/x-flv",
            "fpx": "image/vnd.fpx",
            "npx": "image/vnd.net-fpx",
            "flx": "text/vnd.fmi.flexstor",
            "fli": "video/x-fli",
            "ftc": "application/vnd.fluxtime.clip",
            "fdf": "application/vnd.fdf",
            "f": "text/x-fortran",
            "mif": "application/vnd.mif",
            "fm": "application/vnd.framemaker",
            "fh": "image/x-freehand",
            "fsc": "application/vnd.fsc.weblaunch",
            "fnc": "application/vnd.frogans.fnc",
            "ltf": "application/vnd.frogans.ltf",
            "ddd": "application/vnd.fujixerox.ddd",
            "xdw": "application/vnd.fujixerox.docuworks",
            "xbd": "application/vnd.fujixerox.docuworks.binder",
            "oas": "application/vnd.fujitsu.oasys",
            "oa2": "application/vnd.fujitsu.oasys2",
            "oa3": "application/vnd.fujitsu.oasys3",
            "fg5": "application/vnd.fujitsu.oasysgp",
            "bh2": "application/vnd.fujitsu.oasysprs",
            "spl": "application/x-futuresplash",
            "fzs": "application/vnd.fuzzysheet",
            "g3": "image/g3fax",
            "gmx": "application/vnd.gmx",
            "gtw": "model/vnd.gtw",
            "txd": "application/vnd.genomatix.tuxedo",
            "ggb": "application/vnd.geogebra.file",
            "ggt": "application/vnd.geogebra.tool",
            "gdl": "model/vnd.gdl",
            "gex": "application/vnd.geometry-explorer",
            "gxt": "application/vnd.geonext",
            "g2w": "application/vnd.geoplan",
            "g3w": "application/vnd.geospace",
            "gsf": "application/x-font-ghostscript",
            "bdf": "application/x-font-bdf",
            "gtar": "application/x-gtar",
            "texinfo": "application/x-texinfo",
            "gnumeric": "application/x-gnumeric",
            "kml": "application/vnd.google-earth.kml+xml",
            "kmz": "application/vnd.google-earth.kmz",
            "gqf": "application/vnd.grafeq",
            "gif": "image/gif",
            "gv": "text/vnd.graphviz",
            "gac": "application/vnd.groove-account",
            "ghf": "application/vnd.groove-help",
            "gim": "application/vnd.groove-identity-message",
            "grv": "application/vnd.groove-injector",
            "gtm": "application/vnd.groove-tool-message",
            "tpl": "application/vnd.groove-tool-template",
            "vcg": "application/vnd.groove-vcard",
            "h261": "video/h261",
            "h263": "video/h263",
            "h264": "video/h264",
            "hpid": "application/vnd.hp-hpid",
            "hps": "application/vnd.hp-hps",
            "hdf": "application/x-hdf",
            "rip": "audio/vnd.rip",
            "hbci": "application/vnd.hbci",
            "jlt": "application/vnd.hp-jlyt",
            "pcl": "application/vnd.hp-pcl",
            "hpgl": "application/vnd.hp-hpgl",
            "hvs": "application/vnd.yamaha.hv-script",
            "hvd": "application/vnd.yamaha.hv-dic",
            "hvp": "application/vnd.yamaha.hv-voice",
            "sfd-hdstx": "application/vnd.hydrostatix.sof-data",
            "stk": "application/hyperstudio",
            "hal": "application/vnd.hal+xml",
            "html": "text/html",
            "irm": "application/vnd.ibm.rights-management",
            "sc": "application/vnd.ibm.secure-container",
            "ics": "text/calendar",
            "icc": "application/vnd.iccprofile",
            "ico": "image/x-icon",
            "igl": "application/vnd.igloader",
            "ief": "image/ief",
            "ivp": "application/vnd.immervision-ivp",
            "ivu": "application/vnd.immervision-ivu",
            "rif": "application/reginfo+xml",
            "3dml": "text/vnd.in3d.3dml",
            "spot": "text/vnd.in3d.spot",
            "igs": "model/iges",
            "i2g": "application/vnd.intergeo",
            "cdy": "application/vnd.cinderella",
            "xpw": "application/vnd.intercon.formnet",
            "fcs": "application/vnd.isac.fcs",
            "ipfix": "application/ipfix",
            "cer": "application/pkix-cert",
            "pki": "application/pkixcmp",
            "crl": "application/pkix-crl",
            "pkipath": "application/pkix-pkipath",
            "igm": "application/vnd.insors.igm",
            "rcprofile": "application/vnd.ipunplugged.rcprofile",
            "irp": "application/vnd.irepository.package+xml",
            "jad": "text/vnd.sun.j2me.app-descriptor",
            "jar": "application/java-archive",
            "class": "application/java-vm",
            "jnlp": "application/x-java-jnlp-file",
            "ser": "application/java-serialized-object",
            "java": "text/x-java-source,java",
            "js": "application/javascript",
            "json": "application/json",
            "joda": "application/vnd.joost.joda-archive",
            "jpm": "video/jpm",
            "jpeg": "image/x-citrix-jpeg",
            "jpg": "image/x-citrix-jpeg",
            "pjpeg": "image/pjpeg",
            "jpgv": "video/jpeg",
            "ktz": "application/vnd.kahootz",
            "mmd": "application/vnd.chipnuts.karaoke-mmd",
            "karbon": "application/vnd.kde.karbon",
            "chrt": "application/vnd.kde.kchart",
            "kfo": "application/vnd.kde.kformula",
            "flw": "application/vnd.kde.kivio",
            "kon": "application/vnd.kde.kontour",
            "kpr": "application/vnd.kde.kpresenter",
            "ksp": "application/vnd.kde.kspread",
            "kwd": "application/vnd.kde.kword",
            "htke": "application/vnd.kenameaapp",
            "kia": "application/vnd.kidspiration",
            "kne": "application/vnd.kinar",
            "sse": "application/vnd.kodak-descriptor",
            "lasxml": "application/vnd.las.las+xml",
            "latex": "application/x-latex",
            "lbd": "application/vnd.llamagraphics.life-balance.desktop",
            "lbe": "application/vnd.llamagraphics.life-balance.exchange+xml",
            "jam": "application/vnd.jam",
            "123": "application/vnd.lotus-1-2-3",
            "apr": "application/vnd.lotus-approach",
            "pre": "application/vnd.lotus-freelance",
            "nsf": "application/vnd.lotus-notes",
            "org": "application/vnd.lotus-organizer",
            "scm": "application/vnd.lotus-screencam",
            "lwp": "application/vnd.lotus-wordpro",
            "lvp": "audio/vnd.lucent.voice",
            "m3u": "audio/x-mpegurl",
            "m4v": "video/x-m4v",
            "hqx": "application/mac-binhex40",
            "portpkg": "application/vnd.macports.portpkg",
            "mgp": "application/vnd.osgeo.mapguide.package",
            "mrc": "application/marc",
            "mrcx": "application/marcxml+xml",
            "mxf": "application/mxf",
            "nbp": "application/vnd.wolfram.player",
            "ma": "application/mathematica",
            "mathml": "application/mathml+xml",
            "mbox": "application/mbox",
            "mc1": "application/vnd.medcalcdata",
            "mscml": "application/mediaservercontrol+xml",
            "cdkey": "application/vnd.mediastation.cdkey",
            "mwf": "application/vnd.mfer",
            "mfm": "application/vnd.mfmp",
            "msh": "model/mesh",
            "mads": "application/mads+xml",
            "mets": "application/mets+xml",
            "mods": "application/mods+xml",
            "meta4": "application/metalink4+xml",
            "mcd": "application/vnd.mcd",
            "flo": "application/vnd.micrografx.flo",
            "igx": "application/vnd.micrografx.igx",
            "es3": "application/vnd.eszigno3+xml",
            "mdb": "application/x-msaccess",
            "asf": "video/x-ms-asf",
            "exe": "application/x-msdownload",
            "cil": "application/vnd.ms-artgalry",
            "cab": "application/vnd.ms-cab-compressed",
            "ims": "application/vnd.ms-ims",
            "application": "application/x-ms-application",
            "clp": "application/x-msclip",
            "mdi": "image/vnd.ms-modi",
            "eot": "application/vnd.ms-fontobject",
            "xls": "application/vnd.ms-excel",
            "xlam": "application/vnd.ms-excel.addin.macroenabled.12",
            "xlsb": "application/vnd.ms-excel.sheet.binary.macroenabled.12",
            "xltm": "application/vnd.ms-excel.template.macroenabled.12",
            "xlsm": "application/vnd.ms-excel.sheet.macroenabled.12",
            "chm": "application/vnd.ms-htmlhelp",
            "crd": "application/x-mscardfile",
            "lrm": "application/vnd.ms-lrm",
            "mvb": "application/x-msmediaview",
            "mny": "application/x-msmoney",
            "pptx": "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "sldx": "application/vnd.openxmlformats-officedocument.presentationml.slide",
            "ppsx": "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
            "potx": "application/vnd.openxmlformats-officedocument.presentationml.template",
            "xlsx": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "xltx": "application/vnd.openxmlformats-officedocument.spreadsheetml.template",
            "docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "dotx": "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
            "obd": "application/x-msbinder",
            "thmx": "application/vnd.ms-officetheme",
            "onetoc": "application/onenote",
            "pya": "audio/vnd.ms-playready.media.pya",
            "pyv": "video/vnd.ms-playready.media.pyv",
            "ppt": "application/vnd.ms-powerpoint",
            "ppam": "application/vnd.ms-powerpoint.addin.macroenabled.12",
            "sldm": "application/vnd.ms-powerpoint.slide.macroenabled.12",
            "pptm": "application/vnd.ms-powerpoint.presentation.macroenabled.12",
            "ppsm": "application/vnd.ms-powerpoint.slideshow.macroenabled.12",
            "potm": "application/vnd.ms-powerpoint.template.macroenabled.12",
            "mpp": "application/vnd.ms-project",
            "pub": "application/x-mspublisher",
            "scd": "application/x-msschedule",
            "xap": "application/x-silverlight-app",
            "stl": "application/vnd.ms-pki.stl",
            "cat": "application/vnd.ms-pki.seccat",
            "vsd": "application/vnd.visio",
            "vsdx": "application/vnd.visio2013",
            "wm": "video/x-ms-wm",
            "wma": "audio/x-ms-wma",
            "wax": "audio/x-ms-wax",
            "wmx": "video/x-ms-wmx",
            "wmd": "application/x-ms-wmd",
            "wpl": "application/vnd.ms-wpl",
            "wmz": "application/x-ms-wmz",
            "wmv": "video/x-ms-wmv",
            "wvx": "video/x-ms-wvx",
            "wmf": "application/x-msmetafile",
            "trm": "application/x-msterminal",
            "doc": "application/msword",
            "docm": "application/vnd.ms-word.document.macroenabled.12",
            "dotm": "application/vnd.ms-word.template.macroenabled.12",
            "wri": "application/x-mswrite",
            "wps": "application/vnd.ms-works",
            "xbap": "application/x-ms-xbap",
            "xps": "application/vnd.ms-xpsdocument",
            "mid": "audio/midi",
            "mpy": "application/vnd.ibm.minipay",
            "afp": "application/vnd.ibm.modcap",
            "rms": "application/vnd.jcp.javame.midlet-rms",
            "tmo": "application/vnd.tmobile-livetv",
            "prc": "application/x-mobipocket-ebook",
            "mbk": "application/vnd.mobius.mbk",
            "dis": "application/vnd.mobius.dis",
            "plc": "application/vnd.mobius.plc",
            "mqy": "application/vnd.mobius.mqy",
            "msl": "application/vnd.mobius.msl",
            "txf": "application/vnd.mobius.txf",
            "daf": "application/vnd.mobius.daf",
            "fly": "text/vnd.fly",
            "mpc": "application/vnd.mophun.certificate",
            "mpn": "application/vnd.mophun.application",
            "mj2": "video/mj2",
            "mpga": "audio/mpeg",
            "mxu": "video/vnd.mpegurl",
            "mpeg": "video/mpeg",
            "m21": "application/mp21",
            "mp4a": "audio/mp4",
            "mp4": "video/mp4",
            "m3u8": "application/vnd.apple.mpegurl",
            "mus": "application/vnd.musician",
            "msty": "application/vnd.muvee.style",
            "mxml": "application/xv+xml",
            "ngdat": "application/vnd.nokia.n-gage.data",
            "n-gage": "application/vnd.nokia.n-gage.symbian.install",
            "ncx": "application/x-dtbncx+xml",
            "nc": "application/x-netcdf",
            "nlu": "application/vnd.neurolanguage.nlu",
            "dna": "application/vnd.dna",
            "nnd": "application/vnd.noblenet-directory",
            "nns": "application/vnd.noblenet-sealer",
            "nnw": "application/vnd.noblenet-web",
            "rpst": "application/vnd.nokia.radio-preset",
            "rpss": "application/vnd.nokia.radio-presets",
            "n3": "text/n3",
            "edm": "application/vnd.novadigm.edm",
            "edx": "application/vnd.novadigm.edx",
            "ext": "application/vnd.novadigm.ext",
            "gph": "application/vnd.flographit",
            "ecelp4800": "audio/vnd.nuera.ecelp4800",
            "ecelp7470": "audio/vnd.nuera.ecelp7470",
            "ecelp9600": "audio/vnd.nuera.ecelp9600",
            "oda": "application/oda",
            "ogx": "application/ogg",
            "oga": "audio/ogg",
            "ogv": "video/ogg",
            "dd2": "application/vnd.oma.dd2+xml",
            "oth": "application/vnd.oasis.opendocument.text-web",
            "opf": "application/oebps-package+xml",
            "qbo": "application/vnd.intu.qbo",
            "oxt": "application/vnd.openofficeorg.extension",
            "osf": "application/vnd.yamaha.openscoreformat",
            "weba": "audio/webm",
            "webm": "video/webm",
            "odc": "application/vnd.oasis.opendocument.chart",
            "otc": "application/vnd.oasis.opendocument.chart-template",
            "odb": "application/vnd.oasis.opendocument.database",
            "odf": "application/vnd.oasis.opendocument.formula",
            "odft": "application/vnd.oasis.opendocument.formula-template",
            "odg": "application/vnd.oasis.opendocument.graphics",
            "otg": "application/vnd.oasis.opendocument.graphics-template",
            "odi": "application/vnd.oasis.opendocument.image",
            "oti": "application/vnd.oasis.opendocument.image-template",
            "odp": "application/vnd.oasis.opendocument.presentation",
            "otp": "application/vnd.oasis.opendocument.presentation-template",
            "ods": "application/vnd.oasis.opendocument.spreadsheet",
            "ots": "application/vnd.oasis.opendocument.spreadsheet-template",
            "odt": "application/vnd.oasis.opendocument.text",
            "odm": "application/vnd.oasis.opendocument.text-master",
            "ott": "application/vnd.oasis.opendocument.text-template",
            "ktx": "image/ktx",
            "sxc": "application/vnd.sun.xml.calc",
            "stc": "application/vnd.sun.xml.calc.template",
            "sxd": "application/vnd.sun.xml.draw",
            "std": "application/vnd.sun.xml.draw.template",
            "sxi": "application/vnd.sun.xml.impress",
            "sti": "application/vnd.sun.xml.impress.template",
            "sxm": "application/vnd.sun.xml.math",
            "sxw": "application/vnd.sun.xml.writer",
            "sxg": "application/vnd.sun.xml.writer.global",
            "stw": "application/vnd.sun.xml.writer.template",
            "otf": "application/x-font-otf",
            "osfpvg": "application/vnd.yamaha.openscoreformat.osfpvg+xml",
            "dp": "application/vnd.osgi.dp",
            "pdb": "application/vnd.palm",
            "p": "text/x-pascal",
            "paw": "application/vnd.pawaafile",
            "pclxl": "application/vnd.hp-pclxl",
            "efif": "application/vnd.picsel",
            "pcx": "image/x-pcx",
            "psd": "image/vnd.adobe.photoshop",
            "prf": "application/pics-rules",
            "pic": "image/x-pict",
            "chat": "application/x-chat",
            "p10": "application/pkcs10",
            "p12": "application/x-pkcs12",
            "p7m": "application/pkcs7-mime",
            "p7s": "application/pkcs7-signature",
            "p7r": "application/x-pkcs7-certreqresp",
            "p7b": "application/x-pkcs7-certificates",
            "p8": "application/pkcs8",
            "plf": "application/vnd.pocketlearn",
            "pnm": "image/x-portable-anymap",
            "pbm": "image/x-portable-bitmap",
            "pcf": "application/x-font-pcf",
            "pfr": "application/font-tdpfr",
            "pgn": "application/x-chess-pgn",
            "pgm": "image/x-portable-graymap",
            "png": "image/x-png",
            "ppm": "image/x-portable-pixmap",
            "pskcxml": "application/pskc+xml",
            "pml": "application/vnd.ctc-posml",
            "ai": "application/postscript",
            "pfa": "application/x-font-type1",
            "pbd": "application/vnd.powerbuilder6",
            "pgp": "application/pgp-signature",
            "box": "application/vnd.previewsystems.box",
            "ptid": "application/vnd.pvi.ptid1",
            "pls": "application/pls+xml",
            "str": "application/vnd.pg.format",
            "ei6": "application/vnd.pg.osasli",
            "dsc": "text/prs.lines.tag",
            "psf": "application/x-font-linux-psf",
            "qps": "application/vnd.publishare-delta-tree",
            "wg": "application/vnd.pmi.widget",
            "qxd": "application/vnd.quark.quarkxpress",
            "esf": "application/vnd.epson.esf",
            "msf": "application/vnd.epson.msf",
            "ssf": "application/vnd.epson.ssf",
            "qam": "application/vnd.epson.quickanime",
            "qfx": "application/vnd.intu.qfx",
            "qt": "video/quicktime",
            "rar": "application/x-rar-compressed",
            "ram": "audio/x-pn-realaudio",
            "rmp": "audio/x-pn-realaudio-plugin",
            "rsd": "application/rsd+xml",
            "rm": "application/vnd.rn-realmedia",
            "bed": "application/vnd.realvnc.bed",
            "mxl": "application/vnd.recordare.musicxml",
            "musicxml": "application/vnd.recordare.musicxml+xml",
            "rnc": "application/relax-ng-compact-syntax",
            "rdz": "application/vnd.data-vision.rdz",
            "rdf": "application/rdf+xml",
            "rp9": "application/vnd.cloanto.rp9",
            "jisp": "application/vnd.jisp",
            "rtf": "application/rtf",
            "rtx": "text/richtext",
            "link66": "application/vnd.route66.link66+xml",
            "rss": "application/rss+xml",
            "shf": "application/shf+xml",
            "st": "application/vnd.sailingtracker.track",
            "svg": "image/svg+xml",
            "sus": "application/vnd.sus-calendar",
            "sru": "application/sru+xml",
            "setpay": "application/set-payment-initiation",
            "setreg": "application/set-registration-initiation",
            "sema": "application/vnd.sema",
            "semd": "application/vnd.semd",
            "semf": "application/vnd.semf",
            "see": "application/vnd.seemail",
            "snf": "application/x-font-snf",
            "spq": "application/scvp-vp-request",
            "spp": "application/scvp-vp-response",
            "scq": "application/scvp-cv-request",
            "scs": "application/scvp-cv-response",
            "sdp": "application/sdp",
            "etx": "text/x-setext",
            "movie": "video/x-sgi-movie",
            "ifm": "application/vnd.shana.informed.formdata",
            "itp": "application/vnd.shana.informed.formtemplate",
            "iif": "application/vnd.shana.informed.interchange",
            "ipk": "application/vnd.shana.informed.package",
            "tfi": "application/thraud+xml",
            "shar": "application/x-shar",
            "rgb": "image/x-rgb",
            "slt": "application/vnd.epson.salt",
            "aso": "application/vnd.accpac.simply.aso",
            "imp": "application/vnd.accpac.simply.imp",
            "twd": "application/vnd.simtech-mindmapper",
            "csp": "application/vnd.commonspace",
            "saf": "application/vnd.yamaha.smaf-audio",
            "mmf": "application/vnd.smaf",
            "spf": "application/vnd.yamaha.smaf-phrase",
            "teacher": "application/vnd.smart.teacher",
            "svd": "application/vnd.svd",
            "rq": "application/sparql-query",
            "srx": "application/sparql-results+xml",
            "gram": "application/srgs",
            "grxml": "application/srgs+xml",
            "ssml": "application/ssml+xml",
            "skp": "application/vnd.koan",
            "sgml": "text/sgml",
            "sdc": "application/vnd.stardivision.calc",
            "sda": "application/vnd.stardivision.draw",
            "sdd": "application/vnd.stardivision.impress",
            "smf": "application/vnd.stardivision.math",
            "sdw": "application/vnd.stardivision.writer",
            "sgl": "application/vnd.stardivision.writer-global",
            "sm": "application/vnd.stepmania.stepchart",
            "sit": "application/x-stuffit",
            "sitx": "application/x-stuffitx",
            "sdkm": "application/vnd.solent.sdkm+xml",
            "xo": "application/vnd.olpc-sugar",
            "au": "audio/basic",
            "wqd": "application/vnd.wqd",
            "sis": "application/vnd.symbian.install",
            "smi": "application/smil+xml",
            "xsm": "application/vnd.syncml+xml",
            "bdm": "application/vnd.syncml.dm+wbxml",
            "xdm": "application/vnd.syncml.dm+xml",
            "sv4cpio": "application/x-sv4cpio",
            "sv4crc": "application/x-sv4crc",
            "sbml": "application/sbml+xml",
            "tsv": "text/tab-separated-values",
            "tiff": "image/tiff",
            "tao": "application/vnd.tao.intent-module-archive",
            "tar": "application/x-tar",
            "tcl": "application/x-tcl",
            "tex": "application/x-tex",
            "tfm": "application/x-tex-tfm",
            "tei": "application/tei+xml",
            "txt": "text/plain",
            "dxp": "application/vnd.spotfire.dxp",
            "sfs": "application/vnd.spotfire.sfs",
            "tsd": "application/timestamped-data",
            "tpt": "application/vnd.trid.tpt",
            "mxs": "application/vnd.triscape.mxs",
            "t": "text/troff",
            "tra": "application/vnd.trueapp",
            "ttf": "application/x-font-ttf",
            "ttl": "text/turtle",
            "umj": "application/vnd.umajin",
            "uoml": "application/vnd.uoml+xml",
            "unityweb": "application/vnd.unity",
            "ufd": "application/vnd.ufdl",
            "uri": "text/uri-list",
            "utz": "application/vnd.uiq.theme",
            "ustar": "application/x-ustar",
            "uu": "text/x-uuencode",
            "vcs": "text/x-vcalendar",
            "vcf": "text/x-vcard",
            "vcd": "application/x-cdlink",
            "vsf": "application/vnd.vsf",
            "wrl": "model/vrml",
            "vcx": "application/vnd.vcx",
            "mts": "model/vnd.mts",
            "vtu": "model/vnd.vtu",
            "vis": "application/vnd.visionary",
            "viv": "video/vnd.vivo",
            "ccxml": "application/ccxml+xml,",
            "vxml": "application/voicexml+xml",
            "src": "application/x-wais-source",
            "wbxml": "application/vnd.wap.wbxml",
            "wbmp": "image/vnd.wap.wbmp",
            "wav": "audio/x-wav",
            "davmount": "application/davmount+xml",
            "woff": "application/x-font-woff",
            "wspolicy": "application/wspolicy+xml",
            "webp": "image/webp",
            "wtb": "application/vnd.webturbo",
            "wgt": "application/widget",
            "hlp": "application/winhlp",
            "wml": "text/vnd.wap.wml",
            "wmls": "text/vnd.wap.wmlscript",
            "wmlsc": "application/vnd.wap.wmlscriptc",
            "wpd": "application/vnd.wordperfect",
            "stf": "application/vnd.wt.stf",
            "wsdl": "application/wsdl+xml",
            "xbm": "image/x-xbitmap",
            "xpm": "image/x-xpixmap",
            "xwd": "image/x-xwindowdump",
            "der": "application/x-x509-ca-cert",
            "fig": "application/x-xfig",
            "xhtml": "application/xhtml+xml",
            "xml": "application/xml",
            "xdf": "application/xcap-diff+xml",
            "xenc": "application/xenc+xml",
            "xer": "application/patch-ops-error+xml",
            "rl": "application/resource-lists+xml",
            "rs": "application/rls-services+xml",
            "rld": "application/resource-lists-diff+xml",
            "xslt": "application/xslt+xml",
            "xop": "application/xop+xml",
            "xpi": "application/x-xpinstall",
            "xspf": "application/xspf+xml",
            "xul": "application/vnd.mozilla.xul+xml",
            "xyz": "chemical/x-xyz",
            "yaml": "text/yaml",
            "yang": "application/yang",
            "yin": "application/yin+xml",
            "zir": "application/vnd.zul",
            "zip": "application/zip",
            "zmm": "application/vnd.handheld-entertainment+xml",
            "zaz": "application/vnd.zzazz.deck+xml"}';

        //to php array
        $mime_types = json_decode($data, true);
        // dd($mime_types);
        $mime_types = array_flip($mime_types);
        // dd($mime_types);
        $mime_types = array_map(function ($item) {
            return str_replace('application/', '', $item);
        }, $mime_types);
    }
    public static function system_boot()
    {
        //set unlimited time limit
        // set_time_limit(0);
        //set unlimited memory limit
        // ini_set('memory_limit', '-1');
        //set unlimited memory limit
        // ini_set('max_execution_time', 0);
        //set unlimited memory limit
        // ini_set('max_input_time', 0);
        //set unlimited memory limit
        // ini_set('post_max_size', '100M');
        //set unlimited memory limit
        // ini_set('upload_max_filesize', '100M');
        // self::get_remote_movies_links();
        // self::download_pending_movies();
        //self::process_thumbs();

        //schools
        // self::get_school_links(1); //Nursery schools
        // self::get_school_links(2); //Primary schools
        // self::get_school_links(3); //Secondary schools
        // self::get_school_links(4); //Tertiary schools
        // self::get_school_links(5); //University schools
        //self::get_school_profiles(); //profiles

        //self::get_past_paper_cats();
        //self::get_past_paper_pages();
        //Utils::download_sharability_posts();


        //self::get_remote_movies_links_2();
        //self::download_pending_movies();

        die('-done-');
        return 'Done';
    }

    public static function save_my_counter($type, $count_value, $other = null)
    {
        $my_counter = new MyCounter();
        $my_counter->type = $type;
        $my_counter->count_value = $count_value;

        //if()

        //if $other  is an array
        if (is_array($other)) {

            //if id is set
            if (isset($other['id'])) {
                $my_counter = MyCounter::find($other['id']);
                if ($my_counter == null) {
                    $my_counter = new MyCounter();
                }
            }

            //if isset($other['status'])) {
            if (isset($other['status'])) {
                $my_counter->status = $other['status'];
            }
            if (isset($other['status_message'])) {
                $my_counter->status_message = $other['status_message'];
            }
            if (isset($other['data'])) {
                $my_counter->data = $other['data'];
            }

            if ($my_counter->statu != 'SUCCESS') {
            }
        }

        /* 
          $table->string('status')->nullable()->default('SUCCESS');
            $table->text('status_message')->nullable();
            $table->longText('data')->nullable();
        */
        $my_counter->save();
    }

    public static function get_url($url)
    {
        $html = null;
        try {
            // Create a cookie jar with the required cookies (use domain without scheme)
            $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray([
                'laravel_session' => 'eyJpdiI6ImZsdTRXdi9NZ0FHV09TUzVJMmpEbVE9PSIsInZhbHVlIjoiRDg2UVFSUTI1bklXQnNFK3VaZU1uM2NDSkVzZFFTQnpYendicjdobjhQaStWTHBvMGxXTytEMGoxS1psS3BDekRyeWtwYjFtS280L0lWV1N2bjcxTXpnd1dsOHRGTXVTMkV1MGpCR3FCK0VZRjdmRGlodExrRXlvbVRsci9Cd1giLCJtYWMiOiJkNWI0YjVjMzUxNTU0MGE5ZjI3ZjYxNTNlYWRiMjhmMTI0ODJhZTMyNDcyMGIwMGI3ODU1OTViNjNhNjAyMGEzIiwidGFnIjoiIn0%3D',
                'XSRF-TOKEN'     => 'eyJpdiI6IkJMelllbDkrQWJrYVBtamN5ZUJ6SGc9PSIsInZhbHVlIjoiSFVlc3pHZTBjWk5Ib0l6b0FFYnpiRWFuWDc1Mm1WdkFJYmNUdFp1M0pVSWRNRVJZR3dMVE4zTjM2TEZLSHVXN05zQkVHNHl4cy9yeGZMNWxkS21Pb05SK0kzajJUTUdueThzM2ZXSnVDVnllRUFxb1lXV3A4bU1uRHNFZEVZN20iLCJtYWMiOiI0ODMyNWY1YmE3OTA4YTEzYWQ0MmFlYmE2ZTFmYzliNmMyODVhMzNlMzVlMDEzNmQzODkxYjAyOGYzODIyYzM2IiwidGFnIjoiIn0%3D'
            ], 'ugaflix.com');

            // Create the Guzzle client with options similar to the original cURL setup.
            $client = new \GuzzleHttp\Client([
                'allow_redirects' => true,
                'verify'          => false,
                'timeout'         => 30,
                'connect_timeout' => 30,
                'headers'         => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Cache-Control'   => 'no-cache',
                    'Pragma'          => 'no-cache',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
            ]);

            // Make the GET request with the cookie jar
            $response = $client->get($url, [
                'cookies' => $cookieJar,
            ]);

            // Get the full response body as a string
            $html = (string) $response->getBody();
        } catch (\Throwable $th) {
            throw $th;
        }

        return $html;
    }



    public static function get_remote_movies_links_4()
    {

        $scarpers = ScraperModel::where([
            'type' => 'ugaflix_movie',
            'status' => 'SUCCESS',
        ])->orderBy('id', 'asc')
            ->limit(100000)
            ->get();



        //get movies pages


        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //max execution time
        ini_set('max_execution_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');



        foreach ($scarpers as $scaper) {


            $movie = MovieModel::where([
                'title' => $scaper->title,
            ])->first();

            if ($movie == null) {
                $movie = MovieModel::where([
                    'external_url' => $scaper->url,
                ])->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where([
                    'url' => $scaper->url,
                ])->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where([
                    'external_url' => $scaper->url,
                ])->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where([
                    'imdb_url' => $scaper->url,
                ])->first();
            }

            if ($movie == null) {
                echo 'Movie not found: ' . $scaper->title . '<br>';
                continue;
            }

            if ($scaper->datae == null || $scaper->datae == '') {
                $movie->thumbnail_url = $movie->image_url;
                echo 'Movie image not found: ' . $scaper->title . '<br>';
                die('Movie image not found: ' . $scaper->title);
            }

            $movie->image_url = $scaper->datae;
            $movie->thumbnail_url = $scaper->datae;
            $movie->save();
            $scaper->status = 'PROCESSED';
            $scaper->save();
            //display image in 100x100  
            echo $scaper->id . ' <img src="' . $scaper->datae . '" width="80"  />' . 'DONE<br>  ';

            continue;
            dd($scaper);
            dd($movie);
            /* 
  "image_url" => "https://ugaflix.com/upload/images/xBrx6O1RIhjtWmVthZIANuQz7Z2.jpg"
    "thumbnail_url" =
*/
            if ($movie != null) {

                if ($movie->content_is_video == 'Yes') {
                    //echo already processed
                    echo '<a href="' . $scaper->url . '" target="_blank">' . $scaper->title . '</a> already processed<br>';
                    $scaper->status = 'SUCCESS';
                    $scaper->error_message = 'Already processed';
                    $scaper->save();
                    continue;
                }
            }


            $url = str_replace('details', 'watch', $scaper->url);
            $html = self::get_url($url);

            $html = str_get_html($html);
            $downloadLink = null;
            $DownloadVideoElement = $html->find('a[title="download"]', 0);
            $DownloadVideoElement = $html->find('.class="subscribe-btn-item"', 0);
            if ($DownloadVideoElement) {
                $downloadLink = $DownloadVideoElement->href;
            } else {
            }
            if ($downloadLink == null) {
                $video = $html->find('video', 0);
                if ($video != null) {
                    $downloadLink = $video->src;
                }
            }
            if ($downloadLink == null) {
                $scaper->status = 'NOT_FOUND_VIDEO';
                $scaper->error_message = 'Download link not found';
                $scaper->save();
                echo $scaper->id . ' - ' . $scaper->title . ' - ' . 'Download link not found' . ' ==NOT_FOUND== : ' . $scaper->url . '<br>';
                continue;
            }
            if ($movie == null) {
                $movie = MovieModel::where([
                    'external_url' => $downloadLink,
                ])->first();
            }

            if ($movie == null) {
                $movie = MovieModel::where([
                    'url' => $downloadLink,
                ])->first();
            }

            if ($movie == null) {
                $movie = new MovieModel();
            }

            $actor_video_link = $html->find('.actor-video-link', 0);
            $isFirst = true;
            $ginre = null;
            $vj = null;
            if ($actor_video_link != null) {
                $links = $actor_video_link->find('li');
                if ($links != null) {
                    foreach ($links as $link) {
                        if ($isFirst) {
                            $isFirst = false;
                            $ginre = $link->plaintext;
                        }

                        $plaintext = strtolower($link->plaintext);
                        //check plaintext if not empty has word vj
                        if (strpos($plaintext, 'vj') !== false) {
                            $vj = trim($link->plaintext);
                        }
                    }
                }
            }

            if ($vj == null) {
                $_title = strtolower($movie->title);
                //check if title has vj
                if (strpos($_title, 'vj') !== false) {
                    $slpts = explode('vj', $_title);
                    if (count($slpts) > 1) {
                        $vj = trim($slpts[1]);
                    }
                }
            }

            $firsth3 = $html->find('.page-content-area', 0);
            $description = null;
            if ($firsth3 != null) {
                $description = trim($firsth3->plaintext);
                //replace Watch Trailer  
                $description = str_replace('Watch Trailer', '', $description);
                //splti by Description                                                                                                                                                  
                $splits = explode('Description', $description);
                if (count($splits) > 1) {
                    $description = trim($splits[1]);
                }
            }


            $movie->title = $scaper->title;
            $movie->url = $downloadLink;
            $movie->external_url = $downloadLink;
            $movie->type =  "Movie";

            if ($description != null) {
                $description = str_replace('Description', '', $description);
                $movie->description = $description;
            }

            // get this meta <link rel="image_src" href="https://ugaflix.com/upload/images/xBrx6O1RIhjtWmVthZIANuQz7Z2.jpg">
            $linkImage = $html->find('link[rel="image_src"]', 0); //href
            if ($linkImage != null) {
                $linkImage = $linkImage->href;
            } else {
                $linkImage = null;
            }

            if ($linkImage != null) {
                $movie->image_url = $linkImage;
            }
            $movie->imdb_url = $scaper->url;
            $movie->status = 'Inactive';
            $movie->type =  'Movie';
            $movie->video_is_downloaded_to_server =  'No';
            $movie->content_type_processed =  'No';
            $movie->is_premium =  'No';
            $movie->new_server_path =  'ugaflix';
            $movie->vj = $vj;
            $movie->category = $ginre;


            $isEdit = false;
            if ($movie->id != null) {
                $isEdit = true;
            }

            try {
                $movie->save();
            } catch (\Throwable $th) {
                //throw $th;
                echo '<br>';
                $scaper->status = 'FAILED';
                $scaper->error_message = $th->getMessage();
                $scaper->save();
                echo $scaper->id . ' - ' . $scaper->title . ' - ' . $th->getMessage() . ' ==FAILED==<br>';
                continue;
            }

            //IF IS EDITED
            if ($isEdit) {
                echo '<br>';
                echo '<a href="' . $movie->url . '" target="_blank">' . $scaper->id . ' - ' . $scaper->title . ' - ' . $movie->id . ' - ' .   ' ==EDITED==</a><br>';
            } else {
                echo '<br>';
                echo '<a href="' . $movie->url . '" target="_blank">' . $scaper->id . ' - ' . $scaper->title . ' - ' . $movie->id . ' - ' .   ' ==CREATED==</a><br>';
            }

            if ($movie->content_is_video != 'Yes') {
                $movie->verify_movie();
            }
            $scaper->status  = 'SUCCESS';
            $scaper->error_message = null;
            $scaper->save();
        }

        die('-done-');
        dd($scarpers);

        $max = 160;
        for ($i = 1; $i <= $max; $i++) {
            $url = 'https://ugaflix.com/movies?page=' . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_4',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }

            try {
                $my_html = self::get_url($url);
            } catch (\Throwable $th) {
                //throw $th;
                echo $th->getMessage();
            }

            $html = str_get_html($my_html);
            $videos = $html->find('.single-video');

            foreach ($videos as $video) {
                $link = $video->find('a', 0);
                if ($link == null) {
                    continue;
                }
                $title = $link->title;
                if ($title == null) {
                    echo '<br>';
                    echo 'title not found';
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => 'title not found',
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $page_url = $link->href;
                if ($page_url == null) {
                    echo '<br>';
                    echo 'page url not found';
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => 'page url not found',
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $img = $video->find('img', 1)->src;
                if ($img == null) {
                    $img  = $video->find('img', 0)->src;
                }

                $lastScrap = ScraperModel::where([
                    'url' => $page_url,
                ])->orderBy('id', 'desc')->first();
                if ($lastScrap == null) {
                    $lastScrap = new ScraperModel();
                }
                $lastScrap->type = 'ugaflix_movie';
                $lastScrap->url = $page_url;
                $lastScrap->title = $title;
                $lastScrap->status = 'PENDING';

                try {
                    $lastScrap->save();
                } catch (\Throwable $th) {
                    //throw $th;
                    echo '<br>';
                    echo 'error saving scraper model';
                    echo '<br>';
                    echo $th->getMessage();
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => $th->getMessage(),
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $params = [
                    'status' => 'SUCCESS',
                    'status_message' => $page_url,
                    'data' => $url,
                ];

                self::save_my_counter('get_remote_movies_links_4', $i, $params);

                echo '<br>';
                echo $i . '. - ' . $url . ' - ' . $title . '<br>';

                continue;
            }
        }
        die('-done-');

        $last_page = 1;
        $last_my_counter = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'SUCCESS',
        ])->orderBy('id', 'desc')->first();

        /*  $note_success = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'FAILED',
        ])->orderBy('id', 'desc')->delete(); */

        set_time_limit(0);

        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');

        //set upload max filesize
        ini_set('upload_max_filesize', '10000M');

        //set max execution time
        ini_set('max_execution_time', 0);

        if ($last_my_counter != null) {
            $last_page = $last_my_counter->count_value;
        } else {
            $last_page = 1;
        }

        //https://movies.ug/play.php?mId=10819 


        $base_url = 'https://movies.ug/play.php?mId=';

        $html = null;
        $i = $last_page;
        $max = $last_page + 10000;
        $start_time = time();
        for (; $i < $max; $i++) {
            $url = $base_url . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_3',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }


            $my_html = null;
            try {
                $my_html = self::get_url($url);
            } catch (\Throwable $th) {

                $message = $th->getMessage();
                $params = [
                    'status' => 'FAILED',
                    'status_message' => $message,
                    'data' => null,
                ];
                //display details
                echo $i . ' - ' . $url . ' - ' . $message . '<br>';
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                continue;
            }
            if ($my_html == null) {
                $params = [
                    'status' => 'FAILED',
                    'status_message' => 'html not found',
                    'data' =>  $url,
                ];
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - html not found<br>';
                continue;
            }

            //now create dom object
            $html = str_get_html($my_html);
            //get first h2
            $h2s = $html->find('h3', 0);
            $error_message = '';
            $params = [
                'status' => 'SUCCESS',
                'status_message' => $url,
                'data' =>  $url,
            ];
            if ($h2s == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $url;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }
            $title = trim($h2s->plaintext);

            if ($title == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['data'] = $url;
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
            }

            // if title is empty
            if (empty($title)) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $video_url = null;

            //get button with text download
            $buttons = $html->find('button');
            foreach ($buttons as $button) {
                if (str_contains($button->plaintext, 'Download Movie')) {
                    try {
                        $video_url = $button->parent()->href;
                    } catch (\Throwable $th) {
                    }
                    break;
                }
            }
            if ($video_url == null) {
                //get first video tag
                $video = $html->find('video', 0);
                if ($video != null) {
                    $video_url = $video->find('source', 0)->src;
                } else {
                }
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);

                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $movie = MovieModel::where('external_url', $video_url)->first();
            if ($movie == null) {
                $movie = MovieModel::where('url', $video_url)->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where('imdb_url', $url)->first();
            }

            //get titles
            $title = str_replace('Title: ', '', $title);
            $title = str_replace('Title:', '', $title);
            $title = trim($title);

            if ($movie == null) {
                $movie = MovieModel::where('title', $title)->first();
            }

            if ($movie == null) {
                $movie = new MovieModel();
            }
            $movie->title = $title;
            $movie->external_url = $video_url;
            $movie->url = $video_url;
            $movie->imdb_url = $url;
            $movie->imdb_id = $i;
            $movie->imdb_rating = 0;
            $movie->imdb_votes = 0;

            $h2s = $html->find('h2');
            $actor = null;
            foreach ($h2s as $h2) {
                if (str_contains($h2->plaintext, 'Actor:')) {
                    $actor = trim(str_replace('Actor:', '', $h2->plaintext));
                    $movie->actor = $actor;
                }
            }

            $h3s = $html->find('h4');
            foreach ($h3s as $h) {
                if (str_contains($h->plaintext, 'Genre:')) {
                    $movie->genre = trim(str_replace('Genre:', '', $h->plaintext));
                    continue;
                }
                if (str_contains($h->plaintext, 'VJ:')) {
                    $movie->vj = trim(str_replace('VJ:', '', $h->plaintext));
                    continue;
                }
                //Views
                if (str_contains($h->plaintext, 'Views:')) {
                    $movie->imdb_rating = trim(str_replace('Views:', '', $h->plaintext));
                    $movie->views_count = $movie->imdb_rating;
                    continue;
                }
            }

            $firstP = $html->find('p', 0);
            if ($firstP != null) {
                $movie->description = trim($firstP->plaintext);
            }
            $thumbnail = $html->find('img', 0);
            if ($thumbnail != null) {
                $movie->thumbnail_url = $thumbnail->src;
                if ($movie->thumbnail_url != null && strlen($movie->thumbnail_url) > 4) {
                    $movie->thumbnail_url = 'https://movies.ug' . $thumbnail->src;
                }
            }
            $movie->image_url = $movie->thumbnail_url;

            //year
            $min_year = 1900;
            $max_year = 2025;
            $year = null;
            for ($x = $min_year; $x <= $max_year; $x++) {
                if (str_contains($movie->title, $x)) {
                    $year = (int)$x;
                    break;
                }
            }
            if ($year != null) {
                $movie->year = $year;
            } else {
                $movie->year = null;
            }
            $movie->type = 'movie';
            $movie->status = 'Active';
            $movie->is_processed = 'No';

            $exist = 'NOT_EXIST';
            if ($movie->id != null) {
                $exist = 'EXIST';
            }

            try {
                $movie->save();
            } catch (\Throwable $th) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'movie not saved';
                $params['data'] = $movie;
                $error_message = 'movie not saved';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
            }

            echo $i . '. ' . $movie->title . ' - '  . $exist . ' - ' . $movie->id . '<br>';
            $params['status'] = 'SUCCESS';
            $params['status_message'] = 'movie saved';
            $params['data'] = $movie;
            $params['data'] = $url;
            $params['error_message'] = null;
            self::save_my_counter('get_remote_movies_links_3', $i, $params);

            continue;
        }


        $end_time = time();

        $diff = $end_time - $start_time;

        $hours = floor($diff / 3600);
        $minutes = floor(($diff / 60) % 60);
        $seconds = $diff % 60;

        echo "<hr>TIME TAKEN: " . $hours . " hours " . $minutes . " minutes " . $seconds . " seconds";

        die("done");
        try {
            $html = file_get_html($url);
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'file_get_html';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }
        if ($html == null) {
            return false;
        }

        $base_url = $url;
        $movies_count = 0;
        // find all link
        try {
            foreach ($html->find('a') as $e) {
                //check if last does not contain .mp4 or .mkv or .avi or .flv or .wmv or .mov or .webm and continue
                if (!str_contains($e->href, '.mp4') && !str_contains($e->href, '.mkv') && !str_contains($e->href, '.avi') && !str_contains($e->href, '.flv') && !str_contains($e->href, '.wmv') && !str_contains($e->href, '.mov') && !str_contains($e->href, '.webm')) {
                    continue;
                }
                $movies_count++;
                $url = $base_url . $e->href;
                //check if there is no MovieModel with this url
                $movie = MovieModel::where('external_url', $url)->first();
                if ($movie != null) {
                    continue;
                }
                $movie = new MovieModel();
                $movie->url = null;
                $movie->external_url = $url;
                $movie->title = self::get_movie_title_from_url($url);
                //check if title contains season or series or episode and make type to series else make type to movie
                $temp_title = strtolower($movie->title);
                if (str_contains($temp_title, 'season') || str_contains($temp_title, 'series') || str_contains($temp_title, 'episode')) {
                    $movie->type = 'series';
                } else {
                    $movie->type = 'movie';
                }
                $movie->status = 'pending';
                $movie->downloads_count = 0;
                $movie->views_count = 0;
                $movie->likes_count = 0;
                $movie->dislikes_count = 0;
                $movie->comments_count = 0;
                $movie->video_is_downloaded_to_server = 'no';
                $movie->save();
            }
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'find all link';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }

        /* //size of content from url
        $new_scrap->datae = strlen($html);
        //to mb
        $new_scrap->datae = $new_scrap->datae / 1000000;
        $new_scrap->datae = round($new_scrap->datae, 2);
        $new_scrap->title = $movies_count;
        $new_scrap->status = 'success';
        $new_scrap->save(); */
        return true;
    }

    public static function get_remote_movies_links_3()
    {

        $last_page = 1;
        $last_my_counter = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'SUCCESS',
        ])->orderBy('id', 'desc')->first();

        /*  $note_success = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'FAILED',
        ])->orderBy('id', 'desc')->delete(); */

        set_time_limit(0);

        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');

        //set upload max filesize
        ini_set('upload_max_filesize', '10000M');

        //set max execution time
        ini_set('max_execution_time', 0);

        if ($last_my_counter != null) {
            $last_page = $last_my_counter->count_value;
        } else {
            $last_page = 1;
        }

        //https://movies.ug/play.php?mId=10819 


        $base_url = 'https://movies.ug/play.php?mId=';

        $html = null;
        $i = $last_page;
        $max = $last_page + 10000;
        $start_time = time();
        for (; $i < $max; $i++) {
            $url = $base_url . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_3',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }


            $my_html = null;
            try {
                $my_html = self::get_url($url);
            } catch (\Throwable $th) {

                $message = $th->getMessage();
                $params = [
                    'status' => 'FAILED',
                    'status_message' => $message,
                    'data' => null,
                ];
                //display details
                echo $i . ' - ' . $url . ' - ' . $message . '<br>';
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                continue;
            }
            if ($my_html == null) {
                $params = [
                    'status' => 'FAILED',
                    'status_message' => 'html not found',
                    'data' =>  $url,
                ];
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - html not found<br>';
                continue;
            }

            //now create dom object
            $html = str_get_html($my_html);
            //get first h2
            $h2s = $html->find('h3', 0);
            $error_message = '';
            $params = [
                'status' => 'SUCCESS',
                'status_message' => $url,
                'data' =>  $url,
            ];
            if ($h2s == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $url;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }
            $title = trim($h2s->plaintext);

            if ($title == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['data'] = $url;
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
            }

            // if title is empty
            if (empty($title)) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $video_url = null;

            //get button with text download
            $buttons = $html->find('button');
            foreach ($buttons as $button) {
                if (str_contains($button->plaintext, 'Download Movie')) {
                    try {
                        $video_url = $button->parent()->href;
                    } catch (\Throwable $th) {
                    }
                    break;
                }
            }
            if ($video_url == null) {
                //get first video tag
                $video = $html->find('video', 0);
                if ($video != null) {
                    $video_url = $video->find('source', 0)->src;
                } else {
                }
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);

                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $movie = MovieModel::where('external_url', $video_url)->first();
            if ($movie == null) {
                $movie = MovieModel::where('url', $video_url)->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where('imdb_url', $url)->first();
            }

            //get titles
            $title = str_replace('Title: ', '', $title);
            $title = str_replace('Title:', '', $title);
            $title = trim($title);

            if ($movie == null) {
                $movie = MovieModel::where('title', $title)->first();
            }

            if ($movie == null) {
                $movie = new MovieModel();
            }
            $movie->title = $title;
            $movie->external_url = $video_url;
            $movie->url = $video_url;
            $movie->imdb_url = $url;
            $movie->imdb_id = $i;
            $movie->imdb_rating = 0;
            $movie->imdb_votes = 0;

            $h2s = $html->find('h2');
            $actor = null;
            foreach ($h2s as $h2) {
                if (str_contains($h2->plaintext, 'Actor:')) {
                    $actor = trim(str_replace('Actor:', '', $h2->plaintext));
                    $movie->actor = $actor;
                }
            }

            $h3s = $html->find('h4');
            foreach ($h3s as $h) {
                if (str_contains($h->plaintext, 'Genre:')) {
                    $movie->genre = trim(str_replace('Genre:', '', $h->plaintext));
                    continue;
                }
                if (str_contains($h->plaintext, 'VJ:')) {
                    $movie->vj = trim(str_replace('VJ:', '', $h->plaintext));
                    continue;
                }
                //Views
                if (str_contains($h->plaintext, 'Views:')) {
                    $movie->imdb_rating = trim(str_replace('Views:', '', $h->plaintext));
                    $movie->views_count = $movie->imdb_rating;
                    continue;
                }
            }

            $firstP = $html->find('p', 0);
            if ($firstP != null) {
                $movie->description = trim($firstP->plaintext);
            }
            $thumbnail = $html->find('img', 0);
            if ($thumbnail != null) {
                $movie->thumbnail_url = $thumbnail->src;
                if ($movie->thumbnail_url != null && strlen($movie->thumbnail_url) > 4) {
                    $movie->thumbnail_url = 'https://movies.ug' . $thumbnail->src;
                }
            }
            $movie->image_url = $movie->thumbnail_url;

            //year
            $min_year = 1900;
            $max_year = 2025;
            $year = null;
            for ($x = $min_year; $x <= $max_year; $x++) {
                if (str_contains($movie->title, $x)) {
                    $year = (int)$x;
                    break;
                }
            }
            if ($year != null) {
                $movie->year = $year;
            } else {
                $movie->year = null;
            }
            $movie->type = 'movie';
            $movie->status = 'Active';
            $movie->is_processed = 'No';

            $exist = 'NOT_EXIST';
            if ($movie->id != null) {
                $exist = 'EXIST';
            }

            try {
                $movie->save();
            } catch (\Throwable $th) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'movie not saved';
                $params['data'] = $movie;
                $error_message = 'movie not saved';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
            }

            echo $i . '. ' . $movie->title . ' - '  . $exist . ' - ' . $movie->id . '<br>';
            $params['status'] = 'SUCCESS';
            $params['status_message'] = 'movie saved';
            $params['data'] = $movie;
            $params['data'] = $url;
            $params['error_message'] = null;
            self::save_my_counter('get_remote_movies_links_3', $i, $params);

            continue;
        }


        $end_time = time();

        $diff = $end_time - $start_time;

        $hours = floor($diff / 3600);
        $minutes = floor(($diff / 60) % 60);
        $seconds = $diff % 60;

        echo "<hr>TIME TAKEN: " . $hours . " hours " . $minutes . " minutes " . $seconds . " seconds";

        die("done");
        try {
            $html = file_get_html($url);
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'file_get_html';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }
        if ($html == null) {
            return false;
        }

        $base_url = $url;
        $movies_count = 0;
        // find all link
        try {
            foreach ($html->find('a') as $e) {
                //check if last does not contain .mp4 or .mkv or .avi or .flv or .wmv or .mov or .webm and continue
                if (!str_contains($e->href, '.mp4') && !str_contains($e->href, '.mkv') && !str_contains($e->href, '.avi') && !str_contains($e->href, '.flv') && !str_contains($e->href, '.wmv') && !str_contains($e->href, '.mov') && !str_contains($e->href, '.webm')) {
                    continue;
                }
                $movies_count++;
                $url = $base_url . $e->href;
                //check if there is no MovieModel with this url
                $movie = MovieModel::where('external_url', $url)->first();
                if ($movie != null) {
                    continue;
                }
                $movie = new MovieModel();
                $movie->url = null;
                $movie->external_url = $url;
                $movie->title = self::get_movie_title_from_url($url);
                //check if title contains season or series or episode and make type to series else make type to movie
                $temp_title = strtolower($movie->title);
                if (str_contains($temp_title, 'season') || str_contains($temp_title, 'series') || str_contains($temp_title, 'episode')) {
                    $movie->type = 'series';
                } else {
                    $movie->type = 'movie';
                }
                $movie->status = 'pending';
                $movie->downloads_count = 0;
                $movie->views_count = 0;
                $movie->likes_count = 0;
                $movie->dislikes_count = 0;
                $movie->comments_count = 0;
                $movie->video_is_downloaded_to_server = 'no';
                $movie->save();
            }
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'find all link';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }

        /* //size of content from url
        $new_scrap->datae = strlen($html);
        //to mb
        $new_scrap->datae = $new_scrap->datae / 1000000;
        $new_scrap->datae = round($new_scrap->datae, 2);
        $new_scrap->title = $movies_count;
        $new_scrap->status = 'success';
        $new_scrap->save(); */
        return true;
    }


    public static function get_remote_movies_links_2()
    {




        $url = 'https://translatedfilms.com/videos/';

        $html = null;
        try {
            $html = file_get_html($url);
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'file_get_html';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }
        if ($html == null) {
            return false;
        }

        $base_url = $url;
        $movies_count = 0;
        // find all link
        try {
            foreach ($html->find('a') as $e) {
                //check if last does not contain .mp4 or .mkv or .avi or .flv or .wmv or .mov or .webm and continue
                if (!str_contains($e->href, '.mp4') && !str_contains($e->href, '.mkv') && !str_contains($e->href, '.avi') && !str_contains($e->href, '.flv') && !str_contains($e->href, '.wmv') && !str_contains($e->href, '.mov') && !str_contains($e->href, '.webm')) {
                    continue;
                }
                $movies_count++;
                $url = $base_url . $e->href;
                //check if there is no MovieModel with this url
                $movie = MovieModel::where('external_url', $url)->first();
                if ($movie != null) {
                    continue;
                }
                $movie = new MovieModel();
                $movie->url = null;
                $movie->external_url = $url;
                $movie->title = self::get_movie_title_from_url($url);
                //check if title contains season or series or episode and make type to series else make type to movie
                $temp_title = strtolower($movie->title);
                if (str_contains($temp_title, 'season') || str_contains($temp_title, 'series') || str_contains($temp_title, 'episode')) {
                    $movie->type = 'series';
                } else {
                    $movie->type = 'movie';
                }
                $movie->status = 'pending';
                $movie->downloads_count = 0;
                $movie->views_count = 0;
                $movie->likes_count = 0;
                $movie->dislikes_count = 0;
                $movie->comments_count = 0;
                $movie->video_is_downloaded_to_server = 'no';
                $movie->save();
            }
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'find all link';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }

        /* //size of content from url
        $new_scrap->datae = strlen($html);
        //to mb
        $new_scrap->datae = $new_scrap->datae / 1000000;
        $new_scrap->datae = round($new_scrap->datae, 2);
        $new_scrap->title = $movies_count;
        $new_scrap->status = 'success';
        $new_scrap->save(); */
        return true;
    }

    public static function download_sharability_posts()
    {
        //get last 100 links of type SHAREBILITY_POST
        $links = Link::where('type', 'SHAREBILITY_RESOURCE')
            ->where('processed', 'NO')
            ->limit(5000)->get();
        foreach ($links as $key => $link) {
            $html = null;
            try {
                $html = file_get_html($link->url);
            } catch (\Throwable $th) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = $th->getMessage();
                continue;
            }
            if ($html == null) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = 'Html not found';
                $link->save();
                continue;
            }
            $title = $html->find('title', 0);
            if ($title == null) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = 'Title not found';
                $link->save();
                continue;
            }
            $title = $title->plaintext;
            $title = str_replace('| Sharebility Uganda', '', $title);
            $title = str_replace('Sharebility Uganda', '', $title);
            $title = str_replace('Resources', '', $title);
            $title = str_replace('| Sharebility', '', $title);
            $title = str_replace('Sharebility', '', $title);
            $title = trim($title);

            $post = new LearningMaterialPost();
            $post->title = $title;
            $post->learning_material_category_id = $link->school_type;


            //slug
            $slug = explode('/', $link->url);
            if (count($slug) < 2) {
                continue;
            }
            $slug = $slug[count($slug) - 2];
            if ($slug == null) {
                throw new \Exception('Slug not found');
            }

            //external_url if already exists
            if (LearningMaterialPost::where('external_url', $link->url)->exists()) {
                continue;
            }

            //check if external_id exists
            if (LearningMaterialPost::where('external_id', $slug)->exists()) {
                continue;
            }

            $post->external_id = $slug;
            $post->slug = $slug;
            $post->external_url = $link->url;


            //get description
            $description = $html->find('meta[name=description]', 0);
            if ($description == null) {
                $description = $html->find('meta[name=twitter:description]', 0);
            }
            //if $description is null try twitter:description
            if ($description == null) {
                $description = $html->find('meta[name=twitter:description]', 0);
            }
            if ($description != null) {
                $post->short_description = $description->content;
                $post->description = $description->content;
            }
            //replace Sharebility with Schooldynamics
            $post->description = str_replace('Sharebility', 'Schooldynamics', $post->description);
            $post->short_description = str_replace('Sharebility', 'Schooldynamics', $post->short_description);

            //get image
            $image = $html->find('meta[property=og:image]', 0);
            if ($image == null) {
                $image = $html->find('meta[name=twitter:image]', 0);
            }
            //if $image is null try twitter:image
            if ($image == null) {
                $image = $html->find('meta[name=twitter:image]', 0);
            }
            if ($image != null) {
                $post->image = $image->content;
            }


            // a in .wpdm-button-area
            $post->download_url = null;
            $a = $html->find('.wpdm-button-area a', 0);
            if ($a != null) {
                $post->download_url = $a->href;
            } else {
                $a = $html->find('.wpdm-download-link a', 0);
                if ($a != null) {
                    $post->download_url = $a->href;
                }
            }

            if ($post->download_url == null) {
                echo '<hr>External id: ' . $post->external_id . ' Download url not found';
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = 'Download url not found';
                $link->save();
                continue;
            }

            //download file 
            $public_path = public_path() . '/storage/files';
            //check if public_path does not exist
            if (!file_exists($public_path)) {
                mkdir($public_path);
            }
            //get last url segment
            $url_segments = explode('/', $post->download_url);
            $file_name = time() . "_" . rand(1000, 100000);
            if (count($url_segments) > 0) {
                $file_name = $url_segments[count($url_segments) - 1];
            }

            //check drive.google.com in download_url
            if (str_contains($post->download_url, 'drive.google.com')) {
                $post->external_download_url = $post->download_url;
                $post->download_url = null;
                $post->save();
                $link->processed = 'Yes';
                $link->success = 'Yes';
                $link->save();
                echo '<hr> ==> Downloaded DRIVE: ' . $post->id . '. ' . $link->title;
                continue;
            }

            //download file
            try {

                //GET redirect  of $post->download_url
                $ch = curl_init($post->download_url);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_exec($ch);
                $redirect_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);
                if ($redirect_url == null) {
                    throw new \Exception('Redirect url not found');
                }

                //check if redirect_url CONTAINS drive.google.com 
                if (str_contains($redirect_url, 'drive.google.com')) {
                    $post->external_download_url = $redirect_url;
                    $post->download_url = null;
                    $post->save();
                    $link->processed = 'Yes';
                    $link->success = 'Yes';
                    $link->save();
                    echo '<hr> ==> Downloaded DRIVE: ' . $post->id . '. ' . $link->title;
                    continue;
                }
                $post->external_download_url = $redirect_url;

                //check post with external_download_url exists
                if (LearningMaterialPost::where('external_download_url', $redirect_url)->exists()) {
                    $link->processed = 'Yes';
                    $link->success = 'No';
                    $link->error = 'Download url already exists for another post';
                    $link->save();
                    continue;
                }

                //get file extension
                $file_extension = pathinfo($redirect_url, PATHINFO_EXTENSION);

                if ($file_extension == null || $file_extension == '' || strlen($file_extension) < 3) {
                    //get file extension from $post->download_url
                    $file_extension = pathinfo($post->download_url, PATHINFO_EXTENSION);
                }

                if ($redirect_url == null || $redirect_url == '' || strlen($redirect_url) < 4) {
                    $redirect_url = $post->download_url;
                }
                //get extension from $redirect_url
                if ($file_extension == null || $file_extension == '' || strlen($file_extension) < 3) {
                    $file_extension = pathinfo($redirect_url, PATHINFO_EXTENSION);
                }


                if ($file_extension == null || $file_extension == '' || strlen($file_extension) < 3) {
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    if ($contentType == null) {
                        die('Content type not found');
                    }
                    //if content contains pdf
                    if (str_contains($contentType, 'pdf')) {
                        $file_extension = 'pdf';
                    } else if (str_contains($contentType, 'text')) {
                        //failed because content type is plain text
                        $link->processed = 'Yes';
                        $link->success = 'No';
                        $link->error = 'Content type is plain text';
                        $link->save();
                        echo '<hr> ==> Error ' . $post->id . '. ' . $link->title;
                        continue;
                    } else {
                        if ($contentType == '*/*') {
                            //failed because content type is plain text
                            $link->processed = 'Yes';
                            $link->success = 'No';
                            $link->error = 'Content type is */*';
                            $link->save();
                            echo '<hr> ==> Error ' . $post->id . '. ' . $link->title;
                            continue;
                        }
                        $file_extension = self::get_extension_from_mime($contentType);
                        if ($file_extension == null) {
                            die('File extension not found: ' . $contentType . ", <hr>URL: " . $post->download_url);
                        }
                    }
                }


                if ($file_extension == null || $file_extension == '' || strlen($file_extension) < 3) {
                    dd($ch);
                    //if redirect_url does not contain word sharebility
                    if (str_contains($redirect_url, 'sharebility')) {
                        echo ('<hr>File extension not found. ' . $post->download_url . ', ' . $redirect_url);
                        $post->download_url = $redirect_url;
                        $post->save();

                        $link->processed = 'Yes';
                        $link->success = 'No';
                        $link->error = 'File extension not found. ' . $post->download_url;
                        $link->save();
                        continue;
                    } else {
                        //fail
                        $link->processed = 'Yes';
                        $link->success = 'No';
                        $link->error = 'File extension not found. ' . $post->download_url;
                        $link->save();
                        continue;
                    }
                }

                $file_name = 'schooldynamics-file-' . time() . "_" . rand(100000, 100000000) . '.' . $file_extension;
                $ch = curl_init($post->download_url);
                $fp = fopen($public_path . '/' . $file_name, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
                $post->download_url = 'files/' . $file_name;
                $post->save();
                $link->processed = 'Yes';
                $link->success = 'Yes';
                $link->save();
                echo '<hr> ==> Downloaded ' . $post->id . '. ' . $link->title;
            } catch (\Throwable $th) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = $th->getMessage();
                echo '<hr> ==> Error ' . $post->id . '. ' . $link->title;
                $link->save();
                continue;
            }
        }
    }
    public static function get_past_paper_pages()
    {
        foreach (LearningMaterialCategory::where([])->get() as $key => $cat) {
            //check if last_visit is not null
            if ($cat->last_visit != null) {
                $last_visit = Carbon::parse($cat->last_visit);
                //if last_visit is less than 1 day ago return
                if ($last_visit->addDays(1)->greaterThan(Carbon::now())) {
                    continue;
                }
            }
            $external_url = $cat->external_url;

            //check if $external_url has / at the end and add if not
            if (substr($external_url, -1) != '/') {
                $external_url .= '/';
            }

            $page = Page::where('url', $cat->external_url)->first();
            if ($page != null) {
                //last_visit is not null
                if ($page->last_visit != null) {
                    $last_visit = Carbon::parse($page->last_visit);
                    //if last_visit is less than 1 day ago return
                    if ($last_visit->addDays(1)->greaterThan(Carbon::now())) {
                        continue;
                    }
                }
            }



            $page = Page::where('url', $cat->external_url)->first();
            if ($page == null) {
                $page = new Page();
                $page->url = $cat->external_url;
                $page->title = 'SHAREBILITY_CATEGORY';
                $page->category_id = $cat->id;
                $page->type = 'SHAREBILITY_CATEGORY';
                $page->last_visit = null;
                $page->save();
            }
            $due_page = Page::find($page->id);

            //check if there is no Page with this url
            $html = null;
            try {
                $html = file_get_html($cat->external_url);
            } catch (\Throwable $th) {
                continue;
            }
            if ($html == null) {
                continue;
            }

            //get class error-404-text
            $pages = $html->find('a');
            if ($pages != null) {
                foreach ($pages as $key => $a) {
                    if (!str_contains($a->href, '-category')) {
                        continue;
                    }
                    //last segment as slug
                    $slug = explode('/', $a->href);
                    if (count($slug) < 2) {
                        continue;
                    }
                    $slug = $slug[count($slug) - 2];
                    if ($slug == null) {
                        throw new \Exception('Slug not found');
                    }
                    //check if there is no Page with this url
                    $page = Page::where('url', $a->href)->first();
                    if ($page != null) {
                        continue;
                    }
                    //if not contain /page/ skip
                    if (!str_contains(strtolower($a->href), '/page/')) {
                        continue;
                    }

                    $page = new Page();
                    $page->url = $a->href;
                    $page->title = 'SHAREBILITY_CATEGORY';
                    $page->category_id = $cat->id;
                    $page->type = 'SHAREBILITY_CATEGORY';
                    $page->last_visit = null;
                    $page->save();
                }
            }
            $cat_pages = Page::where('category_id', $cat->id)->get();
            foreach ($cat_pages as $key => $val) {
                $html = null;
                try {
                    $html = file_get_html($val->url);
                } catch (\Throwable $th) {
                    continue;
                }
                if ($html == null) {
                    continue;
                }
                $links = $html->find('a');

                foreach ($links as $key => $a) {
                    if (!str_contains($a->href, '/download/')) {
                        continue;
                    }
                    //last segment as slug
                    $slug = explode('/', $a->href);
                    if (count($slug) < 2) {
                        continue;
                    }
                    $slug = $slug[count($slug) - 2];
                    if ($slug == null) {
                        throw new \Exception('Slug not found');
                    }
                    //tolower
                    $slug = strtolower($slug);
                    //check if there is no Link with this url
                    $link = Link::where('external_id', $slug)->first();
                    if ($link != null) {
                        continue;
                    }
                    //check if there is no Link with this url
                    $link = Link::where('url', $a->href)->first();
                    if ($link != null) {
                        continue;
                    }

                    $link = new Link();
                    $link->title = trim($a->plaintext);
                    $link->url = $a->href;
                    $link->external_id = $slug;
                    $link->type = 'SHAREBILITY_RESOURCE';
                    $link->processed = 'NO';
                    $link->success = 'NO';
                    $link->error = null;
                    $link->school_type = $cat->id;
                    $link->thumbnail = $val->id;
                    $link->save();
                }
            }

            $due_page->last_visit = Carbon::now();
            $due_page->save();
            echo ('<hr>done with ' . $due_page->url);
        }
    }
    public static function get_past_paper_cats()
    {
        $cats_page = Page::where('title', 'SHAREBILITY_CATEGORIES')->first();
        if ($cats_page == null) {
            $cats_page = new Page();
            $cats_page->title = 'SHAREBILITY_CATEGORIES';
            $cats_page->url = 'https://sharebility.net/';
            $cats_page->last_visit = Carbon::now();
            $cats_page->save();
        }

        $last_visit = Carbon::parse($cats_page->last_visit);
        $links = Link::where('type', 'SHAREBILITY_RESOURCE')->get();

        if ($links->count() > 0) {
            if ($last_visit->addDays(1)->greaterThan(Carbon::now())) {
                return;
            }
        }
        $cats_page->url = 'https://sharebility.net/';
        $html = null;
        try {
            $html = file_get_html($cats_page->url);
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
        if ($html == null) {
            dd('html not found');
        }

        //find elementor-widget-container class
        $cats = $html->find('a');
        if ($cats == null) {
            dd('elementor-widget-container not found');
        }
        foreach ($cats as $key => $a) {
            //if not contains -category skip
            if (!str_contains($a->href, '-category')) {
                continue;
            }

            //last segment as slug
            $slug = explode('/', $a->href);
            if (count($slug) < 2) {
                continue;
            }
            $slug = $slug[count($slug) - 2];
            if ($slug == null) {
                throw new \Exception('Slug not found');
            }
            //tolower
            $slug = strtolower($slug);
            //check if there is no Link with this url
            $cat = LearningMaterialCategory::where('external_id', $slug)->first();
            if ($cat != null) {
                continue;
            }

            $cat = LearningMaterialCategory::where('external_url', $a->href)->first();
            if ($cat != null) {
                continue;
            }
            //category html
            $cat_html = null;
            try {
                $cat_html = file_get_html($a->href);
            } catch (\Throwable $th) {
                continue;
            }
            if ($cat_html == null) {
                continue;
            }

            //get title
            $title = $cat_html->find('title', 0);
            if ($title == null) {
                $title = $cat_html->find('h1', 0);
            }
            $name = $title->plaintext;
            //remove Resources | Sharebility Uganda
            $name = str_replace('Resources | Sharebility Uganda', '', $name);
            $name = str_replace('Sharebility Uganda', '', $name);
            $name = str_replace('Resources', '', $name);
            $name = str_replace('| Sharebility', '', $name);
            $name = str_replace('Sharebility', '', $name);
            $name = trim($name);
            $newCat = new LearningMaterialCategory();
            $newCat->name = $name;
            //get description
            $description = $cat_html->find('meta[name=description]', 0);
            if ($description == null) {
                $description = $cat_html->find('meta[name=twitter:description]', 0);
            }
            //if $description is null try twitter:description
            if ($description == null) {
                $description = $cat_html->find('meta[name=twitter:description]', 0);
            }
            if ($description != null) {
                $newCat->short_description = $description->content;
                $newCat->description = $description->content;
            }
            //replace Sharebility with Schooldynamics
            $newCat->description = str_replace('Sharebility', 'Schooldynamics', $newCat->description);
            $newCat->short_description = str_replace('Sharebility', 'Schooldynamics', $newCat->short_description);

            //get image
            $image = $cat_html->find('meta[property=og:image]', 0);
            if ($image == null) {
                $image = $cat_html->find('meta[name=twitter:image]', 0);
            }
            //if $image is null try twitter:image
            if ($image == null) {
                $image = $cat_html->find('meta[name=twitter:image]', 0);
            }
            if ($image != null) {
                $newCat->image = $image->content;
            }


            //random solid color
            $newCat->color = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            $newCat->slug = $slug;
            //order count + 1
            $newCat->order = LearningMaterialCategory::count() + 1;
            $newCat->status = 1;
            $newCat->external_url = $a->href;
            $newCat->external_id = $slug;
            $newCat->save();
            echo 'Downloaded ' . $newCat->id . '. ' . $newCat->name . ' ' . $newCat->external_id . ' ' . $newCat->external_url . ' ' . $newCat->status . ' ' . $newCat->order . ' ' . $newCat->color . ' ' . $newCat->image . ' ' . $newCat->description . ' ' . $newCat->short_description . '<br><hr>';
        }
        return;
    }

    public static function process_thumbs()
    {


        /*  MovieModel::where([
        ])->update([
            'is_processed' => 'No',
            'error_message' => null,
        ]); */

        $movies = MovieModel::where([
            'is_processed' => 'No',
        ])->limit(400)->get();

        //$link = 'https://api.themoviedb.org/3/search/collection?&api_key=f4c978fc273712fc9354e9536f312223&query=';
        $key = 'AIzaSyDy4AEIGtDJy2al8F5JKjhjom_b6qV3wR0';
        $link = 'https://www.googleapis.com/customsearch/v1?';
        $link .= 'key=' . $key;
        $link .= '&cx=b62b522c9a3324dde:omuauf_lfve';

        $link = 'https://www.googleapis.com/customsearch/v1?&key=AIzaSyDy4AEIGtDJy2al8F5JKjhjom_b6qV3wR0&cx=017576662512468239146:omuauf_lfve&1=';

        foreach ($movies as $key => $movie) {

            $title = $movie->title;
            $title = strtolower($title);
            //replace evething after (
            $t = explode('(', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }
            //replace evething after [
            $title = explode('[', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }
            //replace evething after vj
            $title = explode('vj', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }

            $title = explode('tv', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }

            $title = explode('episode', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }

            $title = explode('season', $title);
            if (count($t) > 1) {
                $title = $t[0];
            }


            $url = $link . '&q=test'; // . $title ; 
            $data = file_get_contents($url);

            try {
                $data = file_get_contents($url);
            } catch (\Throwable $th) {
                dd($th->getMessage());
                continue;
            }
            dd($data);
            die('done');

            $data = json_decode($data, true);
            dd($data);
            if ($data == null) {
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Data not found';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            if (!array_key_exists('results', $data)) {
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Results not found';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            $results = $data['results'];
            if ($results == null) {
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Results not found';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            if (count($results) < 1) {
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Results is empty';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            $result = $results[0];
            if ($result == null) {
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Result not found';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            if (!isset($result['poster_path'])) {
                throw new \Exception('Backdrop path not found');
                $movie->is_processed = 'Failed';
                $movie->error_message = 'Backdrop path not found';
                $movie->save();
                echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . '<br>';
                continue;
            }
            $poster_path = $result['poster_path'];
            $backdrop_path = 'https://image.tmdb.org/t/p/w440_and_h660_face/' . $poster_path;

            $movie->thumbnail_url = 'https://image.tmdb.org/t/p/original' . $backdrop_path;
            echo $movie->id . ' ' . $movie->title . ' ' . $movie->is_processed . ' ' . $movie->error_message . ' - SUCCESS<br><hr>';

            echo '<img src="' . $movie->thumbnail_url . '" style="width: 100px; height: 100px;">';

            $movie->is_processed = 'Yes';
            $movie->save();
        }


        die('done');

        return false;
        if (!self::is_localhost_server()) {
            return false;
        }
        //links where processed is no limit 10
        //set unlimited time limit
        set_time_limit(0);
        //set unlimited memory limit
        ini_set('memory_limit', '-1');
        /*  Link::where([])->update([
            'processed' => 'No',
            'success' => 'No',
            'error' => null,
        ]);  */
        $links = Link::where('processed', 'No')->limit(3000)->get();
        $movies = MovieModel::where([])->get();

        foreach ($links as $key => $link) {
            $new_movies = self::sortBySimilarity($movies, $link->title);
            $movie = null;
            $count = 0;
            $down_link = null;
            foreach ($new_movies as $key => $val) {
                $similarity = self::has_similar_words($val->title, $link->title);

                if ($similarity < 1) {
                    continue;
                }

                $count++;
                if ($count > 5) {
                    break;
                }

                $movie = $val;
                break;
            }

            if ($movie == null) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = 'No similar movie found';
                continue;
            }

            $thumbnail_url = 'https://movies.ug' . $link->thumbnail;
            $public_path = public_path() . '/storage/images';

            //check if public_path does not exist
            if (!file_exists($public_path)) {
                //mkdir($public_path);
            }

            //extension of thumbnail
            $extension = pathinfo($thumbnail_url, PATHINFO_EXTENSION);
            if ($extension == null || $extension == '') {
                $extension = 'jpg';
            }

            //download file
            try {
                $ch = curl_init($thumbnail_url);
                $fp = fopen($public_path . '/' . $movie->id . '.' . $extension, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_exec($ch);
                $link->processed = 'Yes';
                $link->success = 'Yes';
                $link->save();
                $movie->thumbnail_url = 'images/' . $link->id . '.jpg';
                $movie->save();
                echo 'Downloaded ' . $link->id . '. ' . $link->title . ' ' . $link->thumbnail . ' ' . $link->external_id . ' ' . $link->url . ' ' . $link->processed . ' ' . $link->success . ' ' . $link->error . '<br>';
            } catch (\Throwable $th) {
                $link->processed = 'Yes';
                $link->success = 'No';
                $link->error = $th->getMessage();
                $link->save();
                echo 'Error ' . $link->id . ' ' . $link->title . ' ' . $link->thumbnail . ' ' . $link->external_id . ' ' . $link->url . ' ' . $link->processed . ' ' . $link->success . ' ' . $link->error . '<br>';
            }
        }
    }

    //check if two strings contains similar words
    public static function has_similar_words($str1, $str2)
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);

        //replace / with ''

        $str1 = explode(' ', $str1);
        $str2 = explode(' ', $str2);
        $similar_words = 0;
        $skip = [
            'the',
            'a',
            'an',
            'and',
            'or',
            'of',
            'in',
            'on',
            'at',
            'to',
            'for',
            'with',
            'by',
            'from',
            'up',
            'Series',
            'Season',
            'Episode',
            'Movie',
            'Film',
            'TV',
            'Show',
            'Full',
            'HD',
            '1080p',
            '720p',
            '480p',
            '360p',
            '240p',
            '144p',
            'Download',
            'Watch',
            'Online',
            'Free',
            'Streaming',
            'Video',
            'Clip',
            'Vj',
        ];
        //coverting skip to lowercase
        $skip = array_map('strtolower', $skip);

        foreach ($str1 as $key => $val) {
            //$skip to lowercase
            $val = strtolower($val);
            //str1 to lowercase
            $val = strtolower($val);
            //skip if in skip
            if (in_array($val, $skip)) {
                continue;
            }

            if (in_array($val, $skip)) {
                continue;
            }
            if (in_array($val, $str2)) {
                $similar_words++;
            }
        }
        return $similar_words;
    }


    public static function getSimilarityScore($str1, $str2)
    {
        $len1 = mb_strlen($str1, 'UTF-8');
        $len2 = mb_strlen($str2, 'UTF-8');

        $matrix = [];

        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i] = [$i];
        }

        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = (mb_substr($str1, $i - 1, 1, 'UTF-8') != mb_substr($str2, $j - 1, 1, 'UTF-8')) ? 1 : 0;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        return $matrix[$len1][$len2];
    }


    public static function sortBySimilarity($movies, $searchString)
    {
        $searchString = strtolower($searchString);

        $sortedMovies = $movies->sortBy(function ($movie) use ($searchString) {
            return self::getSimilarityScore(strtolower($movie->title), $searchString);
        });

        return $sortedMovies;
    }




    //movie search algorithm

    public static function get_school_links($school_type)
    {

        $valid = [1, 2, 3, 4, 5, 6];
        if (!in_array($school_type, $valid)) {
            die('Invalid school type');
        }
        $school_type_text = 'Nursery';

        if ($school_type == 2) {
            $school_type_text = 'Primary';
        } else if ($school_type == 3) {
            $school_type_text = 'Secondary';
        } else if ($school_type == 4) {
            $school_type_text = 'Tertiary';
        } else if ($school_type == 5) {
            $school_type_text = 'University';
        } else if ($school_type == 6) {
            $school_type_text = 'Institute';
        }

        $start_num = 1;
        $max_num = 131;


        $base_url = 'https://unser.co.ug/schools/' . $school_type . '/?dist=';
        for ($i = $start_num; $i < $max_num; $i++) {
            $url = $base_url . $i;
            //check if there is no Page with this url
            $page = Page::where('url', $url)->first();
            if ($page != null) {
                continue;
            }
            $html = null;
            try {
                $html = file_get_html($url);
            } catch (\Throwable $th) {
                continue;
            }
            if ($html == null) {
                continue;
            }


            $table = $html->find('table', 0);
            if ($table == null) {
                dd('table not found');
                continue;
            }
            if (!isset($table->children[1])) {
                dd('table children not found');
                continue;
            }
            if (!isset($table->children[1]->children)) {
                dd('table children children not found');
                continue;
            }



            foreach ($table->children[1]->children as $e) {
                $obj = $e->children[0]->find('a', 0);
                if ($obj == null) {
                    dd('a not found');
                    continue;
                }
                $link = $obj->href;
                if ($link == null) {
                    dd('href not found');
                    continue;
                }
                $title = $obj->plaintext;
                if ($title == null) {
                    dd('plaintext not found');
                    continue;
                }

                //check if there is no Link with this url
                $l = Link::where('url', $link)->first();
                if ($l != null) {
                    continue;
                }

                $l = new Link();
                $l->thumbnail = $link;
                $l->title = $title;
                $l->url = $link;
                $l->external_id = $url;
                $l->school_type = $school_type_text;
                $l->type = 'school';
                $l->success = 'No';
                $l->error = null;
                $l->save();
                echo $l->id . ' ' . $l->title . ' ' . $l->thumbnail . ' ' . $l->external_id . ' ' . $l->url . ' ' . $l->school_type . ' ' . $l->type . ' ' . $l->success . ' ' . $l->error . '<br>';
            }

            $page = new Page();
            $page->url = $url;
            $page->title = $url;
            $page->save();
        }

        return true;
        die('done');



        //get last movie where video_is_downloaded_to_server is no
        $last_movie = MovieModel::where([
            'image_url' => null,
        ])->orderBy('id', 'desc')->first();


        $search_url = 'https://movies.ug/?search=' . ($last_movie->title);

        die($search_url);

        dd($last_movie->title);

        if ($last_movie == null) {
            return false;
        }

        //check if video_downloaded_to_server_start_time time is not null and strlen is greater than 4
        if (
            $last_movie->video_downloaded_to_server_start_time != null &&
            strlen($last_movie->video_downloaded_to_server_start_time) > 4
        ) {
            $now = Carbon::now();
            $video_downloaded_to_server_start_time = Carbon::parse($last_movie->video_downloaded_to_server_start_time);
            //if started less than 5 minutes ago return
            if ($video_downloaded_to_server_start_time->addMinutes(5)->greaterThan($now)) {
                //return false;
            }
        }

        $download_url = 'https://images.pexels.com/photos/934011/pexels-photo-934011.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2';
        if (!self::is_localhost_server()) {
            $download_url = $last_movie->external_url;
        }

        //public_path
        $public_path = public_path() . '/storage/videos';

        //check if public_path does not exist
        if (!file_exists($public_path)) {
            mkdir($public_path);
        }

        //get last url segment
        $url_segments = explode('/', $download_url);
        $file_name = time() . "_" . rand(1000, 100000);
        //cjheck if contains ? and remove ? and everything after
        //get file extension
        if (str_contains($download_url, '.')) {
            $file_extension = explode('.', $download_url)[1];
        } else {
            $file_extension = '.mp4';
        }
        //check if file extension is not mp4 or mkv or avi or flv or wmv or mov or webm
        if (
            $file_extension != 'mp4' &&
            $file_extension != 'mkv' &&
            $file_extension != 'avi' &&
            $file_extension != 'flv' &&
            $file_extension != 'wmv' &&
            $file_extension != 'mov' &&
            $file_extension != 'webm'
        ) {
            $file_name .= '.mp4';
        } else if ($file_extension == 'webm') {
            $file_name .= '.mp4';
        }


        $local_file_path = $public_path . '/' . $file_name;

        //set unlimited time limit
        set_time_limit(0);
        //set unlimited memory limit
        ini_set('memory_limit', '-1');

        $last_movie->video_downloaded_to_server_start_time = Carbon::now();
        $last_movie->video_is_downloaded_to_server_status = 'downloading';
        $last_movie->save();
        try {
            //download file
            $ch = curl_init($download_url);
            $fp = fopen($local_file_path, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            $last_movie->video_is_downloaded_to_server_status = 'success';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_downloaded_to_server_end_time = Carbon::now();
            $last_movie->url = 'videos/' . $file_name;
            $last_movie->save();
        } catch (\Throwable $th) {
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server_status = 'error';
            $last_movie->video_is_downloaded_to_server_error_message = $th->getMessage();
            $last_movie->save();
            return false;
        }
    }

    //movie search algorithm
    /* 
    "id" => 1701
    "created_at" => "2024-03-06 00:36:50"
    "updated_at" => "2024-03-06 01:13:57"
    "title" => "Attiak Public Nursery School"
    "url" => "/school/2042/"
    "external_id" => "https://unser.co.ug/schools/1/?dist=1"
    "thumbnail" => "/school/2042/"
    "processed" => "No"
    "success" => "No"
    "error" => null
    "type" => "school"
    "school_type" => "Nursery"
  ]
*/
    public static function get_school_profiles()
    {

        $links = Link::where('type', 'school')->where('processed', 'No')->limit(100000)->get();


        $accepted_keys = [
            'name',
            'district',
            'county',
            'sub-county',
            'parish',
            'address',
            'p.o.box',
            'email',
            'website',
            'phone',
            'fax',
            'service code',
            'reg no',
            'center no',
            'operation satus',
            'founder',
            'funder',
            'boys/girls',
            'day/boarding',
            'registry status',
            'nearest school',
            'main deo office',
            'urban/rural',
            'founding year',
            'level',
            'highest class',
            'access',
        ];

        foreach ($links as $key => $link) {
            $url = 'https://unser.co.ug' . $link->url;

            //check if the url already exists
            $existingSchool = School::where('url', $url)->first();
            if ($existingSchool) {
                continue;
            }

            $html = null;
            try {
                $html = file_get_html($url);
            } catch (\Throwable $th) {
                continue;
            }
            if ($html == null) {
                continue;
            }
            $school = new School();
            $table = $html->find('td');
            foreach ($table as $key => $val) {
                //key is even, continue
                if ($key % 2 == 0) {
                    continue;
                }

                $title = strtolower(trim($table[$key - 1]->plaintext));
                //replace : with ''
                $title = str_replace(':', '', $title);
                $data = trim($table[$key]->plaintext);


                if ($title == 'name') {
                    $school->name = $data;
                }
                if ($title == 'district') {
                    $school->district = $data;
                }
                if ($title == 'county') {
                    if ($data != 'County') {
                        $school->county = $data;
                    }
                }
                if ($title == 'sub-county') {
                    $school->sub_county = $data;
                }
                if ($title == 'parish') {
                    $school->parish = $data;
                }
                if ($title == 'address') {
                    $school->address = $data;
                }
                if ($title == 'p.o.box') {
                    $school->p_o_box = $data;
                }
                if ($title == 'email') {
                    $school->email = $data;
                }
                if ($title == 'website') {
                    $school->website = $data;
                }
                if ($title == 'phone') {
                    $school->phone = $data;
                }
                if ($title == 'fax') {
                    $school->fax = $data;
                }
                if ($title == 'service code') {
                    $school->service_code = $data;
                }
                if ($title == 'reg no') {
                    $school->reg_no = $data;
                }
                if ($title == 'center no') {
                    $school->center_no = $data;
                }
                if ($title == 'operation satus') {
                    $school->operation_status = $data;
                }
                if ($title == 'founder') {
                    $school->founder = $data;
                }
                if ($title == 'funder') {
                    $school->funder = $data;
                }
                if ($title == 'boys/girls') {
                    $school->boys_girls = $data;
                }
                if ($title == 'day/boarding') {
                    $school->day_boarding = $data;
                }
                if ($title == 'registry status') {
                    $school->registry_status = $data;
                }
                if ($title == 'nearest school') {
                    $school->nearest_school = $data;
                }
                if ($title == 'main deo office') {
                    $school->nearest_school_distance = $data;
                }
                if ($title == 'founding year') {
                    $school->founding_year = (int)str_replace(' ', '', $data);
                }
                if ($title == 'level') {
                    $school->level = $data;
                }
                if ($title == 'highest class') {
                    $school->highest_class = $data;
                }
                if ($title == 'access') {
                    $school->access = $data;
                }
                $school->details = null;
                $school->reply_message = null;
                $school->contated = 'No';
                $school->replied = 'No';
                $school->success = 'No';
                $school->url = $url;
            }
            $school->save();
            echo $school->id . ". " . $school->name . "  ====>  " . $school->district . " ";
        }

        return;



        return true;
        die('done');



        //get last movie where video_is_downloaded_to_server is no
        $last_movie = MovieModel::where([
            'image_url' => null,
        ])->orderBy('id', 'desc')->first();


        $search_url = 'https://movies.ug/?search=' . ($last_movie->title);

        die($search_url);

        dd($last_movie->title);

        if ($last_movie == null) {
            return false;
        }

        //check if video_downloaded_to_server_start_time time is not null and strlen is greater than 4
        if (
            $last_movie->video_downloaded_to_server_start_time != null &&
            strlen($last_movie->video_downloaded_to_server_start_time) > 4
        ) {
            $now = Carbon::now();
            $video_downloaded_to_server_start_time = Carbon::parse($last_movie->video_downloaded_to_server_start_time);
            //if started less than 5 minutes ago return
            if ($video_downloaded_to_server_start_time->addMinutes(5)->greaterThan($now)) {
                //return false;
            }
        }

        $download_url = 'https://images.pexels.com/photos/934011/pexels-photo-934011.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2';
        if (!self::is_localhost_server()) {
            $download_url = $last_movie->external_url;
        }

        //public_path
        $public_path = public_path() . '/storage/videos';

        //check if public_path does not exist
        if (!file_exists($public_path)) {
            mkdir($public_path);
        }

        //get last url segment
        $url_segments = explode('/', $download_url);
        $file_name = time() . "_" . rand(1000, 100000);
        //cjheck if contains ? and remove ? and everything after
        //get file extension
        if (str_contains($download_url, '.')) {
            $file_extension = explode('.', $download_url)[1];
        } else {
            $file_extension = '.mp4';
        }
        //check if file extension is not mp4 or mkv or avi or flv or wmv or mov or webm
        if (
            $file_extension != 'mp4' &&
            $file_extension != 'mkv' &&
            $file_extension != 'avi' &&
            $file_extension != 'flv' &&
            $file_extension != 'wmv' &&
            $file_extension != 'mov' &&
            $file_extension != 'webm'
        ) {
            $file_name .= '.mp4';
        } else if ($file_extension == 'webm') {
            $file_name .= '.mp4';
        }


        $local_file_path = $public_path . '/' . $file_name;

        //set unlimited time limit
        set_time_limit(0);
        //set unlimited memory limit
        ini_set('memory_limit', '-1');

        $last_movie->video_downloaded_to_server_start_time = Carbon::now();
        $last_movie->video_is_downloaded_to_server_status = 'downloading';
        $last_movie->save();
        try {
            //download file
            $ch = curl_init($download_url);
            $fp = fopen($local_file_path, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            $last_movie->video_is_downloaded_to_server_status = 'success';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_downloaded_to_server_end_time = Carbon::now();
            $last_movie->url = 'videos/' . $file_name;
            $last_movie->save();
        } catch (\Throwable $th) {
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server_status = 'error';
            $last_movie->video_is_downloaded_to_server_error_message = $th->getMessage();
            $last_movie->save();
            return false;
        }
    }


    public static function download_pending_thumbs()
    {


        $start_num = 0;
        $max_num = 10;

        //get last page
        $last_page = Page::orderBy('id', 'desc')->first();
        if ($last_page != null) {
            $start_num = $last_page->id;
        }
        $end_num = $start_num + $max_num;

        $base_url = 'https://movies.ug/index.php?page=';
        for ($i = $start_num; $i < $end_num; $i++) {
            $url = $base_url . $i;

            //check if there is no Page with this url
            $page = Page::where('url', $url)->first();
            if ($page != null) {
                continue;
            }
            $html = null;
            try {
                $html = file_get_html($url);
            } catch (\Throwable $th) {
                continue;
            }
            if ($html == null) {
                continue;
            }


            foreach ($html->find('a') as $e) {
                if ($e->href == null) {
                    continue;
                }
                if (!str_contains($e->href, 'play.php?')) {
                    continue;
                }
                if ($e->children == null) {
                    continue;
                }
                foreach ($e->children as $key1 => $child) {
                    if ($child->tag != 'img') {
                        continue;
                    }
                    if ($child->src == null) {
                        continue;
                    }
                    //check if there is no Link with this src
                    $link = Link::where('thumbnail', $child->src)->first();
                    if ($link != null) {
                        continue;
                    }
                    $l = new Link();
                    $l->thumbnail = $child->src;
                    $l->title = $child->title;
                    $l->url = $e->href;
                    $l->external_id = $e->href;
                    $l->save();
                }
            }

            $page = new Page();
            $page->url = $url;
            $page->title = $url;
            $page->save();
        }

        return true;
        die('done');



        //get last movie where video_is_downloaded_to_server is no
        $last_movie = MovieModel::where([
            'image_url' => null,
        ])->orderBy('id', 'desc')->first();


        $search_url = 'https://movies.ug/?search=' . ($last_movie->title);

        die($search_url);

        dd($last_movie->title);

        if ($last_movie == null) {
            return false;
        }

        //check if video_downloaded_to_server_start_time time is not null and strlen is greater than 4
        if (
            $last_movie->video_downloaded_to_server_start_time != null &&
            strlen($last_movie->video_downloaded_to_server_start_time) > 4
        ) {
            $now = Carbon::now();
            $video_downloaded_to_server_start_time = Carbon::parse($last_movie->video_downloaded_to_server_start_time);
            //if started less than 5 minutes ago return
            if ($video_downloaded_to_server_start_time->addMinutes(5)->greaterThan($now)) {
                //return false;
            }
        }

        $download_url = 'https://images.pexels.com/photos/934011/pexels-photo-934011.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2';
        if (!self::is_localhost_server()) {
            $download_url = $last_movie->external_url;
        }

        //public_path
        $public_path = public_path() . '/storage/videos';

        //check if public_path does not exist
        if (!file_exists($public_path)) {
            mkdir($public_path);
        }

        //get last url segment
        $url_segments = explode('/', $download_url);
        $file_name = time() . "_" . rand(1000, 100000);
        //cjheck if contains ? and remove ? and everything after
        //get file extension
        if (str_contains($download_url, '.')) {
            $file_extension = explode('.', $download_url)[1];
        } else {
            $file_extension = '.mp4';
        }
        //check if file extension is not mp4 or mkv or avi or flv or wmv or mov or webm
        if (
            $file_extension != 'mp4' &&
            $file_extension != 'mkv' &&
            $file_extension != 'avi' &&
            $file_extension != 'flv' &&
            $file_extension != 'wmv' &&
            $file_extension != 'mov' &&
            $file_extension != 'webm'
        ) {
            $file_name .= '.mp4';
        } else if ($file_extension == 'webm') {
            $file_name .= '.mp4';
        }


        $local_file_path = $public_path . '/' . $file_name;

        //set unlimited time limit
        set_time_limit(0);
        //set unlimited memory limit
        ini_set('memory_limit', '-1');

        $last_movie->video_downloaded_to_server_start_time = Carbon::now();
        $last_movie->video_is_downloaded_to_server_status = 'downloading';
        $last_movie->save();
        try {
            //download file
            $ch = curl_init($download_url);
            $fp = fopen($local_file_path, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            $last_movie->video_is_downloaded_to_server_status = 'success';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_downloaded_to_server_end_time = Carbon::now();
            $last_movie->url = 'videos/' . $file_name;
            $last_movie->save();
        } catch (\Throwable $th) {
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server_status = 'error';
            $last_movie->video_is_downloaded_to_server_error_message = $th->getMessage();
            $last_movie->save();
            return false;
        }
    }


    public static function download_pending_movies()
    {
        return;
        //get video that is now video_is_downloaded_to_server_status is yes
        $video_is_downloaded_to_server_status_yes = MovieModel::where([
            'video_is_downloaded_to_server_status' => 'downloading',
        ])->orderBy('id', 'desc')->first();

        if ($video_is_downloaded_to_server_status_yes != null) {
            //check if its started 2 hours ago and reset it to no
            $now = Carbon::now();
            $video_downloaded_to_server_start_time = Carbon::parse($video_is_downloaded_to_server_status_yes->video_downloaded_to_server_start_time);
            //if started less than 2 hours ago return
            if ($video_downloaded_to_server_start_time->addHours(2)->greaterThan($now)) {
                return false;
            }
            $video_is_downloaded_to_server_status_yes->video_downloaded_to_server_end_time = Carbon::now();
            $video_is_downloaded_to_server_status_yes->video_is_downloaded_to_server_status = 'error';
            $video_is_downloaded_to_server_status_yes->video_is_downloaded_to_server = 'yes';
            $video_is_downloaded_to_server_status_yes->video_is_downloaded_to_server_error_message = 'download took more than 2 hours';
            $video_is_downloaded_to_server_status_yes->save();
        }

        //get last movie where video_is_downloaded_to_server is no
        $last_movie = MovieModel::where([
            'video_is_downloaded_to_server' => 'no',
        ])->orderBy('id', 'desc')->first();

        if ($last_movie == null) {
            return false;
        }

        //check if video_downloaded_to_server_start_time time is not null and strlen is greater than 4
        if (
            $last_movie->video_downloaded_to_server_start_time != null &&
            strlen($last_movie->video_downloaded_to_server_start_time) > 4
        ) {
            $now = Carbon::now();
            $video_downloaded_to_server_start_time = Carbon::parse($last_movie->video_downloaded_to_server_start_time);
            //if started less than 5 minutes ago return
            if ($video_downloaded_to_server_start_time->addMinutes(5)->greaterThan($now)) {
                //return false;
            }
        }

        $download_url = 'https://images.pexels.com/photos/934011/pexels-photo-934011.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2';
        if (!self::is_localhost_server()) {
            $download_url = $last_movie->external_url;
        }

        //public_path
        $public_path = public_path() . '/storage/videos';

        //check if public_path does not exist
        if (!file_exists($public_path)) {
            mkdir($public_path);
        }

        //get last url segment
        $url_segments = explode('/', $download_url);
        $file_name = time() . "_" . rand(1000, 100000);
        //cjheck if contains ? and remove ? and everything after
        //get file extension
        if (str_contains($download_url, '.')) {
            $file_extension = explode('.', $download_url)[1];
        } else {
            $file_extension = '.mp4';
        }
        //check if file extension is not mp4 or mkv or avi or flv or wmv or mov or webm
        if (
            $file_extension != 'mp4' &&
            $file_extension != 'mkv' &&
            $file_extension != 'avi' &&
            $file_extension != 'flv' &&
            $file_extension != 'wmv' &&
            $file_extension != 'mov' &&
            $file_extension != 'webm'
        ) {
            $file_name .= '.mp4';
        } else if ($file_extension == 'webm') {
            $file_name .= '.mp4';
        }


        $local_file_path = $public_path . '/' . $file_name;

        //set unlimited time limit
        set_time_limit(0);
        //set unlimited memory limit
        ini_set('memory_limit', '-1');

        $last_movie->video_downloaded_to_server_start_time = Carbon::now();
        $last_movie->video_is_downloaded_to_server_status = 'downloading';
        $last_movie->save();
        try {
            //download file
            $ch = curl_init($download_url);
            $fp = fopen($local_file_path, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            $last_movie->video_is_downloaded_to_server_status = 'success';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_downloaded_to_server_end_time = Carbon::now();
            $last_movie->url = 'videos/' . $file_name;
            $last_movie->save();
        } catch (\Throwable $th) {
            $last_movie->video_is_downloaded_to_server = 'yes';
            $last_movie->video_is_downloaded_to_server_status = 'error';
            $last_movie->video_is_downloaded_to_server_error_message = $th->getMessage();
            $last_movie->save();
            return false;
        }
    }




    //check if is localhost
    public static function is_localhost_server()
    {

        $server = $_SERVER['SERVER_NAME'];
        if ($server == 'localhost' || $server == '127.0.0.1') {
            return true;
        }
        return false;
    }


    public static function get_remote_movies_links()
    {


        //get last scraped movie where created_at is greater than now minus 5 minutes
        $last_scraped_movie = ScraperModel::where([
            'type' => 'movie',
            'status' => 'success',
        ])->orderBy('id', 'desc')->first();


        $can_scrape = false;
        if ($last_scraped_movie == null) {
            $can_scrape = true;
        }

        $minutes_from_last_scraped_movie = 0;
        if ($last_scraped_movie != null) {
            //now minus 5 minutes
            $now_minus_5_minutes = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            //check if last scraped movie was done 5 minutes ago
            $last_scraped_movie_time = Carbon::parse($last_scraped_movie->created_at);
            if ($last_scraped_movie_time->lessThan($now_minus_5_minutes)) {
                $can_scrape = true;
            }
            $minutes_from_last_scraped_movie = $last_scraped_movie_time->diffInMinutes(Carbon::now());
        }

        if (!$can_scrape) {
            return false;
        }

        //check if there is any pending scraping latest
        $pending_scrap = ScraperModel::where([
            'type' => 'movie',
            'status' => 'pending',
        ])->orderBy('id', 'desc')->first();


        $new_scrap = new ScraperModel();

        if ($pending_scrap != null) {
            $new_scrap = $pending_scrap;
        } else {
            $new_scrap->type = 'movie';
            $new_scrap->status = 'pending';
            $new_scrap->save();
        }
        $new_scrap->url = 'https://movies.ug/videos/';

        $html = null;
        try {
            $html = file_get_html($new_scrap->url);
        } catch (\Throwable $th) {
            $new_scrap->status = 'error';
            $new_scrap->error = 'file_get_html';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save();
        }
        if ($html == null) {
            return false;
        }

        $base_url = 'https://movies.ug/videos/';
        $movies_count = 0;
        // find all link
        try {
            foreach ($html->find('a') as $e) {
                //check if last does not contain .mp4 or .mkv or .avi or .flv or .wmv or .mov or .webm and continue
                if (!str_contains($e->href, '.mp4') && !str_contains($e->href, '.mkv') && !str_contains($e->href, '.avi') && !str_contains($e->href, '.flv') && !str_contains($e->href, '.wmv') && !str_contains($e->href, '.mov') && !str_contains($e->href, '.webm')) {
                    continue;
                }
                $movies_count++;
                $url = $base_url . $e->href;
                //check if there is no MovieModel with this url
                $movie = MovieModel::where('external_url', $url)->first();
                if ($movie != null) {
                    continue;
                }
                $movie = new MovieModel();
                $movie->url = null;
                $movie->external_url = $url;
                $movie->title = self::get_movie_title_from_url($url);
                //check if title contains season or series or episode and make type to series else make type to movie
                $temp_title = strtolower($movie->title);
                if (str_contains($temp_title, 'season') || str_contains($temp_title, 'series') || str_contains($temp_title, 'episode')) {
                    $movie->type = 'series';
                } else {
                    $movie->type = 'movie';
                }
                $movie->status = 'pending';
                $movie->downloads_count = 0;
                $movie->views_count = 0;
                $movie->likes_count = 0;
                $movie->dislikes_count = 0;
                $movie->comments_count = 0;
                $movie->video_is_downloaded_to_server = 'no';
                $movie->save();
            }
        } catch (\Throwable $th) {
            $new_scrap->status = 'error';
            $new_scrap->error = 'find all link';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save();
        }

        //size of content from url
        $new_scrap->datae = strlen($html);
        //to mb
        $new_scrap->datae = $new_scrap->datae / 1000000;
        $new_scrap->datae = round($new_scrap->datae, 2);
        $new_scrap->title = $movies_count;
        $new_scrap->status = 'success';
        $new_scrap->save();
        return true;
    }

    public static function get_movie_title_from_url($url)
    {
        $url = str_replace('https://movies.ug/videos/', '', $url);
        //remove url encoded characters
        $url = urldecode($url);
        //remove html entities
        $url = html_entity_decode($url);
        $url = str_replace('.mp4', '', $url);
        $url = str_replace('.mkv', '', $url);
        $url = str_replace('.avi', '', $url);
        $url = str_replace('.flv', '', $url);
        $url = str_replace('.wmv', '', $url);
        $url = str_replace('.mov', '', $url);
        $url = str_replace('.webm', '', $url);
        $url = str_replace('-', ' ', $url);
        $url = str_replace('_', ' ', $url);
        $url = str_replace('.', ' ', $url);
        $url = str_replace('  ', ' ', $url);
        $url = str_replace('  ', ' ', $url);
        $url = str_replace('  ', ' ', $url);
        return $url;
    }
    public static function file_upload($file)
    {
        if ($file == null) {
            return '';
        }
        //get file extension
        $file_extension = $file->getClientOriginalExtension();
        $file_name = time() . "_" . rand(1000, 100000) . "." . $file_extension;
        $public_path = public_path() . "/storage/images";
        $file->move($public_path, $file_name);
        $url = 'images/' . $file_name;
        return $url;
    }

    public static function get_user(Request $r)
    {
        $u = auth('api')->user();
        if ($u != null) {
            $u = User::find($u->id);
            return $u;
        }
        /* if ($u == null) {
            $u = User::find(1);
            return $u;
        } */

        $logged_in_user_id = $r->header('logged_in_user_id');
        $u = User::find($logged_in_user_id);
        if ($u == null) {
            $logged_in_user_id = $r->get('logged_in_user_id');
            $u = User::find($logged_in_user_id);
        }
        return $u;
    }

    public static function success($data, $message)
    {
        //set header response to json
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'code' => 1,
            'message' => $message,
            'data' => $data,
        ]);
        die();
    }

    public static function error($message)
    {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'code' => 0,
            'message' => $message,
            'data' => null,
        ]);
        die();
    }

    static function getActiveFinancialPeriod($company_id)
    {
        return FinancialPeriod::where('company_id', $company_id)
            ->where('status', 'Active')->first();
    }

    static public function generateSKU($sub_category_id)
    {
        //year-subcategory-id-serial
        $year = date('Y');
        $sub_category = StockSubCategory::find($sub_category_id);
        $serial = StockItem::where('stock_sub_category_id', $sub_category_id)->count() + 1;
        $sku = $year . "-" . $sub_category->id . "-" . $serial;
        return $sku;
    }





    public static function get_remote_movies_links_4_get_images()
    {


        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //max execution time
        ini_set('max_execution_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');


        $max = 160;
        for ($i = 1; $i <= $max; $i++) {
            $url = 'https://ugaflix.com/movies?page=' . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_4_get_images',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }

            try {
                $my_html = self::get_url($url);
            } catch (\Throwable $th) {
                //throw $th;
                echo $th->getMessage();
            }

            $html = str_get_html($my_html);
            $videos = $html->find('.single-video');

            foreach ($videos as $video) {
                $link = $video->find('a', 0);
                if ($link == null) {
                    continue;
                }
                $title = $link->title;
                if ($title == null) {
                    echo '<br>';
                    echo 'title not found';
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => 'title not found',
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $page_url = $link->href;
                if ($page_url == null) {
                    echo '<br>';
                    echo 'page url not found';
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => 'page url not found',
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $img = $video->find('img', 1)->src;
                if ($img == null) {
                    $img  = $video->find('img', 0)->src;
                }

                $lastScrap = ScraperModel::where([
                    'url' => $page_url,
                    'type' => 'ugaflix_movie',
                ])->orderBy('id', 'desc')->first();
                if ($lastScrap == null) {
                    echo ('<br>last scrap not found: ' . $page_url);
                    continue;
                }
                if ($lastScrap->datae != null) {
                    if (strlen($lastScrap->datae) > 4) {
                        echo ('<br>last scrap already has data: ' . $page_url);
                        continue;
                    }
                }
                $lastScrap->type = 'ugaflix_movie';
                $lastScrap->url = $page_url;
                $lastScrap->title = $title;
                $lastScrap->datae =  $img;
                $lastScrap->save();
                echo "PROCESSING: " . $i . ' - ' . $url . ' - ' . $title . '<br>';
                echo '<img src="' . $img . '" style="width: 100px; height: 100px;" /><br>';

                continue;

                try {
                    $lastScrap->save();
                } catch (\Throwable $th) {
                    //throw $th;
                    echo '<br>';
                    echo 'error saving scraper model';
                    echo '<br>';
                    echo $th->getMessage();
                    $params = [
                        'status' => 'FAILED',
                        'status_message' => $th->getMessage(),
                        'data' => $url,
                    ];
                    self::save_my_counter('get_remote_movies_links_4', $i, $params);
                    continue;
                }
                $params = [
                    'status' => 'SUCCESS',
                    'status_message' => $page_url,
                    'data' => $url,
                ];

                self::save_my_counter('get_remote_movies_links_4', $i, $params);

                echo '<br>';
                echo $i . '. - ' . $url . ' - ' . $title . '<br>';

                continue;
            }
        }
        die('-done-');

        $last_page = 1;
        $last_my_counter = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'SUCCESS',
        ])->orderBy('id', 'desc')->first();

        /*  $note_success = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'FAILED',
        ])->orderBy('id', 'desc')->delete(); */

        set_time_limit(0);

        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');

        //set upload max filesize
        ini_set('upload_max_filesize', '10000M');

        //set max execution time
        ini_set('max_execution_time', 0);

        if ($last_my_counter != null) {
            $last_page = $last_my_counter->count_value;
        } else {
            $last_page = 1;
        }

        //https://movies.ug/play.php?mId=10819 


        $base_url = 'https://movies.ug/play.php?mId=';

        $html = null;
        $i = $last_page;
        $max = $last_page + 10000;
        $start_time = time();
        for (; $i < $max; $i++) {
            $url = $base_url . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_3',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }


            $my_html = null;
            try {
                $my_html = self::get_url($url);
            } catch (\Throwable $th) {

                $message = $th->getMessage();
                $params = [
                    'status' => 'FAILED',
                    'status_message' => $message,
                    'data' => null,
                ];
                //display details
                echo $i . ' - ' . $url . ' - ' . $message . '<br>';
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                continue;
            }
            if ($my_html == null) {
                $params = [
                    'status' => 'FAILED',
                    'status_message' => 'html not found',
                    'data' =>  $url,
                ];
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - html not found<br>';
                continue;
            }

            //now create dom object
            $html = str_get_html($my_html);
            //get first h2
            $h2s = $html->find('h3', 0);
            $error_message = '';
            $params = [
                'status' => 'SUCCESS',
                'status_message' => $url,
                'data' =>  $url,
            ];
            if ($h2s == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $url;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }
            $title = trim($h2s->plaintext);

            if ($title == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['data'] = $url;
                $params['error_message'] = $error_message;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
            }

            // if title is empty
            if (empty($title)) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'h2 not found';
                $params['data'] = $html;
                $error_message = 'h2 not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $video_url = null;

            //get button with text download
            $buttons = $html->find('button');
            foreach ($buttons as $button) {
                if (str_contains($button->plaintext, 'Download Movie')) {
                    try {
                        $video_url = $button->parent()->href;
                    } catch (\Throwable $th) {
                    }
                    break;
                }
            }
            if ($video_url == null) {
                //get first video tag
                $video = $html->find('video', 0);
                if ($video != null) {
                    $video_url = $video->find('source', 0)->src;
                } else {
                }
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            if ($video_url == null) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'video url not found';
                $params['data'] = $html;
                $error_message = 'video url not found';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);

                //display details
                echo $i . ' - ' . $url . ' - ' . $error_message . '<br>';
                continue;
            }

            $movie = MovieModel::where('external_url', $video_url)->first();
            if ($movie == null) {
                $movie = MovieModel::where('url', $video_url)->first();
            }
            if ($movie == null) {
                $movie = MovieModel::where('imdb_url', $url)->first();
            }

            //get titles
            $title = str_replace('Title: ', '', $title);
            $title = str_replace('Title:', '', $title);
            $title = trim($title);

            if ($movie == null) {
                $movie = MovieModel::where('title', $title)->first();
            }

            if ($movie == null) {
                $movie = new MovieModel();
            }
            $movie->title = $title;
            $movie->external_url = $video_url;
            $movie->url = $video_url;
            $movie->imdb_url = $url;
            $movie->imdb_id = $i;
            $movie->imdb_rating = 0;
            $movie->imdb_votes = 0;

            $h2s = $html->find('h2');
            $actor = null;
            foreach ($h2s as $h2) {
                if (str_contains($h2->plaintext, 'Actor:')) {
                    $actor = trim(str_replace('Actor:', '', $h2->plaintext));
                    $movie->actor = $actor;
                }
            }

            $h3s = $html->find('h4');
            foreach ($h3s as $h) {
                if (str_contains($h->plaintext, 'Genre:')) {
                    $movie->genre = trim(str_replace('Genre:', '', $h->plaintext));
                    continue;
                }
                if (str_contains($h->plaintext, 'VJ:')) {
                    $movie->vj = trim(str_replace('VJ:', '', $h->plaintext));
                    continue;
                }
                //Views
                if (str_contains($h->plaintext, 'Views:')) {
                    $movie->imdb_rating = trim(str_replace('Views:', '', $h->plaintext));
                    $movie->views_count = $movie->imdb_rating;
                    continue;
                }
            }

            $firstP = $html->find('p', 0);
            if ($firstP != null) {
                $movie->description = trim($firstP->plaintext);
            }
            $thumbnail = $html->find('img', 0);
            if ($thumbnail != null) {
                $movie->thumbnail_url = $thumbnail->src;
                if ($movie->thumbnail_url != null && strlen($movie->thumbnail_url) > 4) {
                    $movie->thumbnail_url = 'https://movies.ug' . $thumbnail->src;
                }
            }
            $movie->image_url = $movie->thumbnail_url;

            //year
            $min_year = 1900;
            $max_year = 2025;
            $year = null;
            for ($x = $min_year; $x <= $max_year; $x++) {
                if (str_contains($movie->title, $x)) {
                    $year = (int)$x;
                    break;
                }
            }
            if ($year != null) {
                $movie->year = $year;
            } else {
                $movie->year = null;
            }
            $movie->type = 'movie';
            $movie->status = 'Active';
            $movie->is_processed = 'No';

            $exist = 'NOT_EXIST';
            if ($movie->id != null) {
                $exist = 'EXIST';
            }

            try {
                $movie->save();
            } catch (\Throwable $th) {
                $params['status'] = 'FAILED';
                $params['status_message'] = 'movie not saved';
                $params['data'] = $movie;
                $error_message = 'movie not saved';
                $params['error_message'] = $error_message;
                $params['data'] = $url;
                self::save_my_counter('get_remote_movies_links_3', $i, $params);
            }

            echo $i . '. ' . $movie->title . ' - '  . $exist . ' - ' . $movie->id . '<br>';
            $params['status'] = 'SUCCESS';
            $params['status_message'] = 'movie saved';
            $params['data'] = $movie;
            $params['data'] = $url;
            $params['error_message'] = null;
            self::save_my_counter('get_remote_movies_links_3', $i, $params);

            continue;
        }


        $end_time = time();

        $diff = $end_time - $start_time;

        $hours = floor($diff / 3600);
        $minutes = floor(($diff / 60) % 60);
        $seconds = $diff % 60;

        echo "<hr>TIME TAKEN: " . $hours . " hours " . $minutes . " minutes " . $seconds . " seconds";

        die("done");
        try {
            $html = file_get_html($url);
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'file_get_html';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }
        if ($html == null) {
            return false;
        }

        $base_url = $url;
        $movies_count = 0;
        // find all link
        try {
            foreach ($html->find('a') as $e) {
                //check if last does not contain .mp4 or .mkv or .avi or .flv or .wmv or .mov or .webm and continue
                if (!str_contains($e->href, '.mp4') && !str_contains($e->href, '.mkv') && !str_contains($e->href, '.avi') && !str_contains($e->href, '.flv') && !str_contains($e->href, '.wmv') && !str_contains($e->href, '.mov') && !str_contains($e->href, '.webm')) {
                    continue;
                }
                $movies_count++;
                $url = $base_url . $e->href;
                //check if there is no MovieModel with this url
                $movie = MovieModel::where('external_url', $url)->first();
                if ($movie != null) {
                    continue;
                }
                $movie = new MovieModel();
                $movie->url = null;
                $movie->external_url = $url;
                $movie->title = self::get_movie_title_from_url($url);
                //check if title contains season or series or episode and make type to series else make type to movie
                $temp_title = strtolower($movie->title);
                if (str_contains($temp_title, 'season') || str_contains($temp_title, 'series') || str_contains($temp_title, 'episode')) {
                    $movie->type = 'series';
                } else {
                    $movie->type = 'movie';
                }
                $movie->status = 'pending';
                $movie->downloads_count = 0;
                $movie->views_count = 0;
                $movie->likes_count = 0;
                $movie->dislikes_count = 0;
                $movie->comments_count = 0;
                $movie->video_is_downloaded_to_server = 'no';
                $movie->save();
            }
        } catch (\Throwable $th) {
            /* $new_scrap->status = 'error';
            $new_scrap->error = 'find all link';
            $new_scrap->error_message = $th->getMessage();
            $new_scrap->save(); */
        }

        /* //size of content from url
        $new_scrap->datae = strlen($html);
        //to mb
        $new_scrap->datae = $new_scrap->datae / 1000000;
        $new_scrap->datae = round($new_scrap->datae, 2);
        $new_scrap->title = $movies_count;
        $new_scrap->status = 'success';
        $new_scrap->save(); */
        return true;
    }




    public static function get_remote_movies_links_namzentertainment()
    {

        set_time_limit(0);

        //set memory limit
        ini_set('memory_limit', '-1');

        //set max input time
        ini_set('max_input_time', 0);

        //set post max size
        ini_set('post_max_size', '10000M');

        //set upload max filesize
        ini_set('upload_max_filesize', '10000M');

        //set max execution time
        ini_set('max_execution_time', 0);

        $max = 8019;
        $min = 47;
        for ($i = $min; $i <= $max; $i++) {
            $url = 'https://namzentertainment.com/Serie.php?id=' . $i;
            // $url = "https://movies.ug/play.php?mId=10819";

            $done = MyCounter::where([
                'type' => 'get_remote_movies_links_namzentertainment',
                'count_value' => $i,
                'status' => 'SUCCESS',
            ])->orderBy('id', 'desc')->first();

            if ($done != null) {
                //display details
                echo $i . ' - ' . $url . ' - already done with #' . $done->id . '<br>';
                continue;
            }
            $myCounter = new MyCounter();
            $myCounter->type = 'get_remote_movies_links_namzentertainment';
            $myCounter->count_value = $i;
            $myCounter->status = 'PENDING';
            $myCounter->data = $url;

            try {
                $my_html = self::get_url_2($url);
            } catch (\Throwable $th) {
                //throw $th;
                echo $th->getMessage();
            }
            $html = str_get_html($my_html);
            //.details__title
            $titleObj = $html->find('.details__title', 0);
            if ($titleObj == null) {
                $myCounter->status = 'FAILED';
                $myCounter->status_message = 'No title found';
                $myCounter->save();
                echo $i . '. ' . $url . ' - No title found<br>';
                continue;
            }
            $title  = trim($titleObj->plaintext);
            $videoObj = $html->find('source', 0);

            if ($videoObj == null) {
                $myCounter->status = 'FAILED';
                $myCounter->status_message = 'No video found';
                echo $i . '. ' . $url . ' - No video found<br>';
                die();
                $myCounter->save();
                continue;
            }
            $video_url = $videoObj->getAttribute('src');
            if ($video_url == null) {
                $myCounter->status = 'FAILED';
                $myCounter->status_message = 'No video url found';
                echo $i . '. ' . $url . ' - No video url found<br>';

                $myCounter->save();
                continue;
            }

            $episodes = [];
            $mCustomScrollbar = $html->find('.accordion__list', 0);
            if ($mCustomScrollbar != null) {
                $links = $mCustomScrollbar->find('tr');
                if ($links != null) {
                    $count = 0;
                    foreach ($links as $key => $value) {
                        $tds = $value->find('td');
                        if ($tds == null) {
                            continue;
                        }
                        $td = $value->find('td', 0);
                        if ($td == null) {
                            continue;
                        }
                        $data_target = $value->getAttribute('data-target');
                        $ep_name = trim($td->plaintext);
                        $ep_url = $data_target;
                        $ep['title'] = $ep_name;
                        $ep['url'] = $ep_url;
                        $splits = explode(' ', $ep_name);
                        $count++;
                        $num = $count;
                        foreach ($splits as $key => $value) {
                            $_num = trim($value);
                            if (is_numeric($_num)) {
                                $num = $value;
                            }
                        }
                        $ep['number'] = $num;
                        $episodes[] = $ep;
                    }
                }
            }







            $movie = MovieModel::where([
                'url' => $video_url,
            ])->first();

            $movie = MovieModel::where([
                'title' => $title,
            ])->first();

            if ($movie == null) {
                $movie = MovieModel::where([
                    'external_url' => $video_url,
                ])->first();
            }
            $isEdit = true;

            if ($movie == null) {
                $movie = new MovieModel();
                $isEdit = false;
            } else {
                echo "<hr>";
                echo $i . '. ' .  $title . ' - ' . count($episodes) . ' episodes<br>';
                echo $movie->id . ' - ' . $movie->title . ' - ' . $movie->external_url . '<br>';
                continue;
            }

            $movie->title = $title;
            $movie->external_url = $video_url;
            $movie->url = $video_url;

            //first img in .card__cover
            $imgObj = $html->find('.card__cover img', 0);
            if ($imgObj != null) {
                $img_url = $imgObj->getAttribute('src');
                if ($img_url != null) {
                    //if not contain http
                    $img_url = trim($img_url);
                    if (strpos($img_url, 'http') === false) {
                        $img_url = 'https://namzentertainment.com/' . $img_url;
                    }
                    $movie->thumbnail_url = $img_url;
                }
            }

            $card__content = $html->find('.card__content', 0);
            if ($card__content != null) {
                $list = $card__content->find('li');
                foreach ($list as $key => $value) {
                    $text = trim($value->plaintext);
                    $text_temp = strtolower($text);
                    if ($text == null || $text == '') {
                        continue;
                    }
                    //chec if contains vj
                    if (strpos($text_temp, 'vj') !== false) {
                        $movie->vj = $text;
                    }
                    if (strpos($text_temp, 'genre') !== false) {
                        //replace Genre:
                        $movie->genre = str_replace('Genre:', '', $text);
                        $movie->genre = trim($movie->genre);
                        $movie->genre = str_replace('Genres:', '', $movie->genre);
                        $movie->genre = str_replace('Genre', '', $movie->genre);
                        $movie->genre = str_replace('Genres', '', $movie->genre);
                        $movie->genre = str_replace(':', '', $movie->genre);
                        $splits = explode(' ', $movie->genre);
                        $last = $movie->genre;
                        if (isset($splits[0])) {
                            $last = $splits[0];
                        } else {
                            $last = $movie->genre;
                        }
                        $movie->genre = $last;
                    }
                }
            }

            $movie->type = 'Movie';

            //content for .card__description card__description--details
            $card__description = $html->find('.card__description--details', 0);
            if ($card__description != null) {
                $movie->description = trim($card__description->plaintext);
            }


            //check if episodes is not empty
            if ($episodes != null && count($episodes) > 0) {

                $serie = SeriesMovie::where([
                    'title' => $title,
                ])->first();

                if ($serie == null) {
                    $serie = SeriesMovie::where([
                        'external_url' =>  $url,
                    ])->first();
                }
                if ($serie == null) {
                    $serie = new SeriesMovie();
                }
                $serie->title = $title;
                $serie->external_url = $url;
                $serie->Category = $movie->genre;
                $serie->description = $movie->description;
                $serie->thumbnail = $movie->thumbnail_url;
                $serie->total_episodes = count($episodes);
                $serie->total_seasons = 1;
                $serie->total_views = 0;
                $serie->total_rating = 0;
                $serie->is_active = 'No';
                $serie->is_premium = 'No';

                $isEdit = true;
                if ($serie->id == null) {
                    $isEdit = false;
                }
                $serie->save();
                $serie = SeriesMovie::find($serie->id);

                echo "<hr>";
                echo $i . '. ' .  $title . ' - ' . count($episodes) . ' episodes<br>';

                foreach ($episodes as $key => $value) {
                    $ep_url = $value['url'];
                    $ep_title = $value['title'];

                    $ep = MovieModel::where([
                        'external_url' => $ep_url
                    ])->first();

                    if ($ep == null) {
                        $ep = MovieModel::where([
                            'url' => $ep_url
                        ])->first();
                    }
                    $isEdit = false;
                    if ($ep != null) {
                        $isEdit = true;
                    } else {
                        $isEdit = false;
                    }

                    if ($ep == null) {
                        $ep = new MovieModel();
                    }

                    $ep->title = $serie->title . ' - ' . $ep_title;
                    $ep->external_url = $ep_url;
                    $ep->url = $ep_url;
                    $ep->category_id = $serie->id;
                    $ep->category_id = $serie->id;
                    $ep->category = $serie->title;
                    $ep->description = $movie->description;
                    $ep->thumbnail_url = $serie->thumbnail;
                    $ep->content_type = 'video/mp4';
                    $ep->content_is_video = 'Yes';
                    $ep->content_type_processed = 'No';
                    $ep->content_type_processed_time = null;
                    $ep->type = 'Series';
                    $ep->is_premium = 'No';
                    if (isset($value['number'])) {
                        $ep->episode_number = $value['number'];
                        $ep->country = $value['number'];
                    }


                    if ($isEdit) {
                        echo ' - edit - ';
                    } else {
                        echo ' - new - ';
                    }
                    //save
                    try {
                        $ep->save();
                        echo $ep->id . ' - saved - ===> ' . $ep->title . "<br>";
                    } catch (\Throwable $th) {
                        echo ' - error - ';
                        echo '<br>';
                        echo '<pre>';
                        print_r($th);
                        echo '</pre>';
                    }
                }


                echo "<hr>";
                continue;
            }

            $isEdit = true;
            if ($movie->id == null) {
                $isEdit = false;
                echo ' - new movie - ';
            } else {
                echo ' - edit movie - ';
                $isEdit = true;
            }
            $movie->imdb_id = $i;
            $movie->imdb_url = $url;

            try {
                $movie->save();
                echo $i . '. ' .  $title . ' - saved - ===> ' . $movie->id . "<br>";
            } catch (\Throwable $th) {
                echo ' - error - ';
                echo '<br>';
                echo '<pre>';
                print_r($th);
                echo '</pre>';
            }

            echo "<hr> MOVIE!";
        }


        die('-done-');

        $last_page = 1;
        $last_my_counter = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'SUCCESS',
        ])->orderBy('id', 'desc')->first();

        /*  $note_success = MyCounter::where([
            'type' => 'get_remote_movies_links_3',
            'status' => 'FAILED',
        ])->orderBy('id', 'desc')->delete(); */
    }


    public static function get_url_2($url)
    {
        $html = null;
        try {
            // Create a cookie jar with the required cookies (use domain without scheme)
            $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray([
                'P' => '0783204665',
                'PHPSESSID' => '06aridtdfikhd3gq91fnoni16t',
                'u' => 'mubahood360@gmail.com',
            ], 'namzentertainment.com');

            // Create the Guzzle client with options similar to the original cURL setup.
            $client = new \GuzzleHttp\Client([
                'allow_redirects' => true,
                'verify'          => false,
                'timeout'         => 30,
                'connect_timeout' => 30,
                'headers'         => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Cache-Control'   => 'no-cache',
                    'Pragma'          => 'no-cache',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
            ]);

            // Make the GET request with the cookie jar
            $response = $client->get($url, [
                'cookies' => $cookieJar,
            ]);

            // Get the full response body as a string
            $html = (string) $response->getBody();
        } catch (\Throwable $th) {
            throw $th;
        }

        return $html;
    }
}
