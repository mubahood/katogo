<?php

use App\Models\MovieModel;
use App\Models\Utils;

Encore\Admin\Form::forget(['map', 'editor']);

// On admin user edit pages: pre-fill both password fields with a safe default
// so the form submits cleanly without browser auto-fill mismatches.
Encore\Admin\Admin::script('
(function () {
    var path = window.location.pathname;
    if (!/\/auth\/users\/\d+\/edit/.test(path)) return;
    var DEFAULT_PWD = "Admin@2026";
    function fillPasswords() {
        var pwd  = document.querySelector("input[name=password]");
        var conf = document.querySelector("input[name=password_confirmation]");
        if (!pwd || !conf) return;
        if (!pwd.value)  { pwd.value  = DEFAULT_PWD; }
        if (!conf.value) { conf.value = DEFAULT_PWD; }
        // Keep confirmation in sync when admin types a new password
        pwd.addEventListener("input", function () { conf.value = pwd.value; });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", fillPasswords);
    } else {
        fillPasswords();
    }
})();
');

// UGFlix Debug Video Player — mimics mobile app playback logic for admin testing
// Cache-bust: append version timestamp to force browser to reload updated JS
Encore\Admin\Admin::js('/vendor/ugflix-debug-player/ugflix-debug-player.js?v=' . filemtime(public_path('vendor/ugflix-debug-player/ugflix-debug-player.js')));
// UGFlix Series Debug Player — series/TV-show player with episode sidebar
Encore\Admin\Admin::js('/vendor/ugflix-debug-player/ugflix-series-player.js?v=' . filemtime(public_path('vendor/ugflix-debug-player/ugflix-series-player.js')));
// Inject the app base URL and stream token for debug player proxy/stream endpoints
Encore\Admin\Admin::script('window.__KATOGO_BASE_URL = ' . json_encode(rtrim(config('app.url'), '/')) . ';');
Encore\Admin\Admin::script('window.__KATOGO_STREAM_TOKEN = ' . json_encode(substr(sha1('debug-stream-' . config('app.key')), 0, 32)) . ';');

/* // open the file "demosaved.csv" for writing
$class_name = 'S.6';
$file = fopen($class_name . '.csv', 'w');
$names_1 = 'BUBIIKE CAROLINE,KAGINO ERIC,KANA ROMAN,KASAJJA GEOFREY,KAWALA PHIONA,KIBUTO IVAN,KIIGE HILLARY,KIRABIRA  FATUMA,KISIBO  MUSITAFA,KITUTU ISAAC,KYOMYA ABUDALLAH,LUBOGO WILSON,MALOLE BRIAN,MUGOYA GEOFREY,MUKOSE INNOCENT TITUS,MUKOSE DAN,MUNDU ROMAN,MUWAYI BENARD,NANGOBI PRETTY,TIBAWULA SANDRA,WAISWA EMMA,WALUBE SADAT,WANKYA BRIAN';
$names = explode(',', $names_1);

fputcsv($file, [
    'STUDENT ID',
    'First Name',
    'Last Name'
]);
$data = [];
foreach ($names as $key => $name) {
    $first_and_last_name = explode(' ', $name);
    $first_name = $first_and_last_name[0];
    if (isset($first_and_last_name[0]) && isset($first_and_last_name[1])) {
        $first_name = $first_and_last_name[0];
        $last_name = $first_and_last_name[1];
    } else {
        die('Last name not found');
    }
    if (isset($first_and_last_name[2])) {
        $last_name .= ' ' . $first_and_last_name[2];
    }
    $id = $class_name . '-' . $first_name . '-' . $last_name;
    $id = strtoupper(str_replace(' ', '-', $id));
    $id = str_replace(' ', '-', $id);
    $data[] = array($id, $first_name, $last_name);
}
 
// save each row of the data
foreach ($data as $row) {
    fputcsv($file, $row);
}

// Close the file
fclose($file);

die("done"); */

//Utils::system_boot();
/* foreach (MovieModel::all() as $key => $value) {
    $value->title = str_replace('/', '', $value->title);
    $value->save(); 
} */

/* try {
    $u = \App\Models\User::where('email', 'mubahood360@gmail.com')->first();
    $code = rand(100000, 999999);
    $u->secret_code = $code;
    $u->save();

    $mail_body = <<<EOD
    <p>Dear {$u->name},</p>
    <p>Your password reset code is <b>$code</b></p>
    <p>Thank you.</p>
    EOD;

    $data['email'] = $u->email;
    $date = date('Y-m-d');
    $data['subject'] = "Password Reset Code - " . env('APP_NAME');
    $data['body'] = $mail_body;
    $data['data'] = $data['body'];
    $data['name'] = $u->name;
    try {
        Utils::mail_sender($data);
        dd("success 1");
    } catch (\Throwable $th) {
        dd("failed because 1 " . $th->getMessage());
        return Utils::error($th->getMessage());
    }
} catch (\Exception $e) {
    dd("failed because 2 " . $e->getMessage());
    return Utils::error($e->getMessage());
}
die("done"); */
//$items = Utils::getBucketItems('mubahood-movies');
//dd($items);

