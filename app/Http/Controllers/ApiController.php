<?php

namespace App\Http\Controllers;

use App\Models\ChatHead;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\CustomerTicket;
use App\Models\CustomerTicketRecord;
use App\Models\Image;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockSubCategory;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\TrendingNotification;
use App\Models\User;
use App\Models\Utils;
use App\Services\AccountMergeService;
use App\Services\SubscriptionPesapalService;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Schema;
use Stevebauman\Location\Facades\Location;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\ApiResponser;

class ApiController extends BaseController
{

    use ApiResponser;

    public function products_delete(Request $r)
    {
        $pro = Product::find($r->id);
        if ($pro == null) {
            return $this->error('Product not found.');
        }
        try {
            $pro->delete();
            return $this->success(null, $message = "Sussesfully deleted!", 200);
        } catch (\Throwable $th) {
            return $this->error('Failed to delete product.');
        }
    }

    public function products_1(Request $request)
    {
        //latest 1000 products without pagination
        $products = Product::where([])->limit(1000)->get();
        return $this->success($products, 'Success');
    }



    public function product_create(Request $r)
    {

        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Not authonticated.");
        }
        $u = User::find($u->id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        //local_id is required
        if (
            !isset($r->local_id) ||
            $r->local_id == null ||
            strlen($r->local_id) < 6
        ) {
            return $this->error('Local ID is missing.');
        }


        $isEdit = false;
        if (
            isset($r->is_edit) && $r->is_edit == 'Yes' && $r->id != null
            && $r->id > 0
        ) {
            $pro = Product::find($r->id);
            if ($pro == null) {
                $pro = new Product();
                $isEdit = false;
            } else {
                $isEdit = true;
            }
        } else {
            $pro = new Product();
        }

        if (!$isEdit) {
            $pro->feature_photo = 'no_image.png';
            $pro->user = $u->id;
            $pro->supplier = $u->id;
            $pro->in_stock = 1;
            $pro->rates = 1;
        }


        if ($r->p_type == 'Yes') {
            if ($r->keywords ==  null) {
                return $this->error('Prices are missing.');
            }
            $my_prices = null;
            try {
                $my_prices = json_decode($r->keywords);
            } catch (\Throwable $th) {
                $my_prices = null;
            }
            //if not array
            if ($my_prices == null || !is_array($my_prices)) {
                return $this->error('Prices not found.');
            }
            //$my_prices if empty
            if (count($my_prices) < 1) {
                return $this->error('Prices not found.');
            }
            $prices = [];
            $min_price = 0;
            $max_price = 0;


            foreach ($my_prices as $key => $value) {
                if ($value->price == null || strlen($value->price) < 1) {
                    return $this->error('Price is missing.');
                }
                if ($value->min_qty == null || strlen($value->min_qty) < 1) {
                    return $this->error('Minimum quantity is missing.');
                }
                if ($value->max_qty == null || strlen($value->max_qty) < 1) {
                    return $this->error('Maximum quantity is missing.');
                }
                $my_min = (int)($value->min_qty);
                $my_max = (int)($value->max_qty);
                $price = (int)($value->price);
                if ($min_price < $my_min) {
                    $min_price = $my_min;
                }
                if ($max_price < $my_max) {
                    $max_price = $my_max;
                }
                $prices[] = $value;
            }

            $pro->price_1 = $min_price;
            $pro->price_2 = $max_price;
            $pro->keywords = $r->keywords;
        } else if ($r->p_type == 'No') {
            if ($r->price_1 == null || strlen($r->price_1) < 1) {
                return $this->error('Price is missing.');
            }
            if ($r->price_2 == null || strlen($r->price_2) < 1) {
                return $this->error('Price is missing.');
            }
            $pro->price_1 = $r->price_1;
            $pro->price_2 = $r->price_2;
        } else {
            return $this->error('Product type is missing.');
        }


        $pro->name = $r->name;
        $pro->description = $r->description;
        $pro->local_id = $r->local_id;
        $pro->summary = $r->data;
        $pro->metric = 1;
        $pro->status = 0;
        $pro->currency = 1;
        $pro->url = $u->url;


        $pro->has_sizes = $r->has_sizes;
        $pro->has_colors = $r->has_colors;
        $pro->colors = $r->colors;
        $pro->sizes = $r->sizes;
        $pro->p_type = $r->p_type;

        $cat = ProductCategory::find($r->category);
        if ($cat == null) {
            return $this->error('Category not found.');
        }
        $pro->category = $cat->id;

        $pro->date_added = Carbon::now();
        $pro->date_updated = Carbon::now();
        $imgs = Image::where([
            'parent_local_id' => $pro->local_id
        ])->get();
        if ($imgs->count() > 0) {
            $pro->feature_photo = $imgs[0]->src;
        }
        if ($pro->save()) {
            foreach ($imgs as $key => $img) {
                $img->product_id = $pro->id;
                $img->save();
            }
            $newPro = Product::find($pro->id);
            if ($isEdit) {
                return $this->success($newPro, $message = "Updated successfully!", 200);
            }
            return $this->success($newPro, $message = "Submitted successfully!", 200);
        } else {
            return $this->error('Failed to upload product.');
        }
    }




    public function disable_account(Request $request)
    {
        $u = Utils::get_user($request);
        if ($u == null) {
            Utils::error("Not authonticated.");
        }
        $administrator_id = $u->id;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $u->status = 'Disabled';
        $u->save();
        $u = Administrator::find($administrator_id);


        return $this->success($u, 'Account deleted successfully.');
    }




    public function upload_media(Request $request)
    {
        $u = Utils::get_user($request);
        if ($u == null) {
            Utils::error("Not authonticated.");
        }
        $administrator_id = $u->id;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (
            !isset($request->parent_local_id) ||
            $request->parent_local_id == null
        ) {
            return $this->error('Local parent ID is missing.');
        }

        //  strlen($request->parent_local_id) < 6
        if (
            strlen($request->parent_local_id) < 6
        ) {
            return $this->error('Local parent ID is too short.');
        }


        if (
            empty($_FILES)
        ) {
            return $this->error('No files found.');
        }



        $images = Utils::upload_images_2($_FILES, false);
        $_images = [];


        if (empty($images)) {
            return $this->error('Failed to upload files.');
        }

        $msg = "";
        foreach ($images as $src) {

            $img = new Image();
            $img->administrator_id =  $administrator_id;
            $img->src =  $src;
            $img->thumbnail =  null;
            $img->parent_endpoint =  $request->parent_endpoint;
            $img->parent_local_id =  $request->parent_local_id;
            $img->type =  $request->type;
            $img->parent_id =  (int)($request->parent_id);
            $pro = Product::where(['local_id' => $img->parent_local_id])->first();
            $img->product_id =  null;
            if ($pro != null) {
                $img->product_id =  $pro->id;
            }
            $img->size = 0;
            $img->note = '';
            if (
                isset($request->note)
            ) {
                $img->note =  $request->note;
            }
            $img->save();
            $_images[] = $img;
        }

        return $this->success(
            null,
            count($_images) . " Files uploaded successfully."
        );
    }



    public function chat_delete(Request $r)
    {

        $chat_head = ChatHead::find($r->chat_head_id);
        if ($chat_head == null) {
            return $this->error('Chat head not found.');
        }

        try {
            $chat_head->delete();
            return $this->success(null, 'Chat head deleted successfully.');
        } catch (\Throwable $th) {
            return $this->error('Failed to delete chat head.');
        }
    }

    public function chat_start(Request $r)
    {

        $sender = User::find($r->sender_id);
        if ($sender == null) {
            return $this->error('Sender not found.');
        }
        $receiver = User::find($r->receiver_id);
        if ($receiver == null) {
            return $this->error('Receiver not found.');
        }

        $product_owner = $sender;
        $customer = $receiver;

        $pro = null;
        if ($r->product_id != null) {
            $pro = Product::find($r->product_id);
        }



        if ($pro != null) {
            $chat_head = ChatHead::where([
                'product_owner_id' => $product_owner->id,
                'customer_id' => $customer->id,
                'product_id' => $pro->id
            ])->first();
            if ($chat_head == null) {
                $chat_head = ChatHead::where([
                    'customer_id' => $product_owner->id,
                    'product_owner_id' => $customer->id,
                    'product_id' => $pro->id
                ])->first();
            }
        } else {
            $chat_head = ChatHead::where([
                'product_owner_id' => $product_owner->id,
                'customer_id' => $customer->id
            ])->first();
            if ($chat_head == null) {
                $chat_head = ChatHead::where([
                    'customer_id' => $product_owner->id,
                    'product_owner_id' => $customer->id
                ])->first();
            }
        }





        if ($chat_head == null) {
            $chat_head = new ChatHead();
            $chat_head->product_id = null;
            $chat_head->customer_photo = $customer->avatar;
            $chat_head->product_owner_id = $product_owner->id;
            $chat_head->customer_id = $customer->id;
            $chat_head->product_owner_name = $product_owner->name;
            $chat_head->product_owner_photo = $product_owner->photo;
            $chat_head->customer_name = $customer->name;
            $chat_head->last_message_body = '';
            $chat_head->last_message_time = Carbon::now();
            $chat_head->last_message_status = 'sent';
            $chat_head->type = 'dating';

            if ($pro != null) {
                $chat_head->product_id = $pro->id;
                $chat_head->customer_photo = $pro->feature_photo;
                $chat_head->product_owner_photo = $pro->feature_photo;
                $chat_head->product_owner_name = $pro->name;
                $chat_head->type = 'product';
            }

            /* 
            $table->string('type')->default('dating')->nullable();
            $table->integer('sender_unread_count')->default(0)->nullable();
            $table->integer('receiver_unread_count')->default(0)->nullable();
            */

            $chat_head->save();
            $chat_head = ChatHead::find($chat_head->id);
        }

        return $this->success($chat_head, 'Success');
    }



    public function me(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Not authonticated.");
        }
        return $this->success($u, "Success");
    }

    /**
     * Update authenticated user profile fields.
     *
     * Important: this does not change account_state. It only patches
     * provided fields (used by mobile to backfill phone number).
     */
    public function me_update(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);
        if (!$u) {
            return $this->error('User not found.', 404);
        }

        $phoneInput = trim((string) ($r->input('phone_number')
            ?: $r->input('phoneNumber')
            ?: $r->input('mobile_money_phone')
            ?: $r->input('mobileMoneyPhone')));
        $phone = $this->normalizeUgandaPhone($phoneInput);

        Log::info('ME_UPDATE_REQUEST', [
            'user_id' => $u->id,
            'has_phone_payload' => $phoneInput !== '',
            'incoming_phone_raw' => $phoneInput,
            'incoming_phone_normalized' => $phone,
            'previous_phone' => $u->phone_number,
        ]);

        if ($phoneInput !== '' && $phone === null) {
            return $this->error('Enter a valid Uganda phone number.', 422);
        }

        if ($phone !== null) {
            $u->phone_number = $phone;

            // For auto-generated guest usernames, promote username to phone.
            if ($this->isAutoGeneratedUsername($u->username)) {
                $u->username = $phone;
            }
        }

        if ($r->filled('name')) {
            $name = trim((string) $r->input('name'));
            if ($name !== '') {
                $u->name = $name;
            }
        }

        if ($r->filled('email')) {
            $email = trim((string) $r->input('email'));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $existingEmail = User::where('email', $email)
                    ->where('id', '!=', $u->id)
                    ->first();

                if ($existingEmail) {
                    return $this->error('Email is already taken by another account.');
                }

                $u->email = $email;
                if (empty($u->username)) {
                    $u->username = $email;
                }
            }
        }

        try {
            $u->save();
            Log::info('ME_UPDATE_SAVED', [
                'user_id' => $u->id,
                'phone_number' => $u->phone_number,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to update profile: ' . $e->getMessage());
        }

        return $this->success(User::find($u->id), 'Profile updated successfully.');
    }

    private function normalizeUgandaPhone(?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '') {
            return null;
        }

        // Keep digits only and normalize common Ugandan formats.
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
            $digits = '256' . substr($digits, 1);
        }

        if (strpos($digits, '256') !== 0 && strlen($digits) === 9 && strpos($digits, '7') === 0) {
            $digits = '256' . $digits;
        }

        if (preg_match('/^2567\d{8}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    private function isAutoGeneratedUsername(?string $username): bool
    {
        $value = trim((string) $username);
        if ($value === '') {
            return true;
        }

        if (preg_match('/^guest_[a-z0-9]+@auto\./i', $value) === 1) {
            return true;
        }

        return stripos($value, '@auto.') !== false;
    }

    public function debug_chat(Request $r, $id)
    {
        $chat_head = ChatHead::find($id);
        if (!$chat_head) {
            return $this->error('Chat head not found');
        }

        $customer = User::find($chat_head->customer_id);
        $product_owner = User::find($chat_head->product_owner_id);

        return $this->success([
            'chat_head' => $chat_head,
            'customer' => $customer,
            'product_owner' => $product_owner,
            'customer_exists' => $customer !== null,
            'product_owner_exists' => $product_owner !== null,
        ], 'Debug info');
    }

    public function chat_heads(Request $r)
    {
        $u = Utils::get_user($r);

        if ($u != null) {
            $u->last_online_at = now();
            $u->save();
        }
        if ($u == null) {
            return $this->error('User not found.');
        }

        // Get all chat heads where user is either customer or product owner
        // Use proper query with where conditions
        $chat_heads = ChatHead::where(function ($query) use ($u) {
            $query->where('product_owner_id', $u->id)
                ->orWhere('customer_id', $u->id);
        })
            ->orderBy('updated_at', 'desc')
            ->get();

        $heads = [];
        $me = $u;

        // Batch-load all participant user IDs to avoid N+1
        $participantIds = $chat_heads->map(function ($h) use ($me) {
            return $h->customer_id == $me->id ? $h->product_owner_id : $h->customer_id;
        })->unique()->toArray();
        $participantUsers = User::whereIn('id', $participantIds)->get()->keyBy('id');

        // Batch-load last messages & unread counts (2 queries instead of N*3)
        $chatHeadIds = $chat_heads->pluck('id')->toArray();
        $lastMessages = ChatMessage::whereIn('chat_head_id', $chatHeadIds)
            ->whereIn('id', function ($q) use ($chatHeadIds) {
                $q->select(DB::raw('MAX(id)'))->from('chat_messages')
                    ->whereIn('chat_head_id', $chatHeadIds)->groupBy('chat_head_id');
            })->get()->keyBy('chat_head_id');
        $unreadCounts = ChatMessage::whereIn('chat_head_id', $chatHeadIds)
            ->where('receiver_id', $me->id)
            ->where('status', '!=', 'read')
            ->select('chat_head_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('chat_head_id')
            ->pluck('cnt', 'chat_head_id');

        foreach ($chat_heads as $head) {
            try {
                $their_id = null;
                $is_customer = ($me->id == $head->customer_id);
                $their_id = $is_customer ? $head->product_owner_id : $head->customer_id;

                // Get from batch-loaded map (no N+1)
                $them = $participantUsers->get($their_id);
                if ($them == null) {
                    continue;
                }

                // Get from batch-loaded data (no N+1)
                $lastMesg = $lastMessages->get($head->id);
                $my_unread_count = $unreadCounts->get($head->id, 0);

                // Set unread count in the appropriate field based on role
                if ($is_customer) {
                    $head->customer_unread_messages_count = $my_unread_count;
                    $head->product_owner_unread_messages_count = 0;
                    $head->unread_count = $my_unread_count; // Add convenient field
                } else {
                    $head->product_owner_unread_messages_count = $my_unread_count;
                    $head->customer_unread_messages_count = 0;
                    $head->unread_count = $my_unread_count; // Add convenient field
                }

                // Set the other person's information consistently
                if ($is_customer) {
                    // I am the customer, they are the product owner
                    $head->product_owner_name = $them->name ?? 'Unknown';
                    $head->product_owner_photo = $them->avatar ?? 'no_image.png';
                    $head->product_owner_last_seen = $them->last_online_at ?? 'offline';

                    // Ensure my info is set
                    $head->customer_name = $me->name;
                    $head->customer_photo = $me->avatar ?? 'no_image.png';
                    $head->customer_last_seen = 'online';

                    // Convenience fields for frontend
                    $head->other_user_id = $them->id;
                    $head->other_user_name = $them->name ?? 'Unknown';
                    $head->other_user_photo = $them->avatar ?? 'no_image.png';
                    $head->other_user_last_seen = $them->last_online_at ?? 'offline';
                } else {
                    // I am the product owner, they are the customer
                    $head->customer_name = $them->name ?? 'Unknown';
                    $head->customer_photo = $them->avatar ?? 'no_image.png';
                    $head->customer_last_seen = $them->last_online_at ?? 'offline';

                    // Ensure my info is set
                    $head->product_owner_name = $me->name;
                    $head->product_owner_photo = $me->avatar ?? 'no_image.png';
                    $head->product_owner_last_seen = 'online';

                    // Convenience fields for frontend
                    $head->other_user_id = $them->id;
                    $head->other_user_name = $them->name ?? 'Unknown';
                    $head->other_user_photo = $them->avatar ?? 'no_image.png';
                    $head->other_user_last_seen = $them->last_online_at ?? 'offline';
                }

                // Set last message info
                if ($lastMesg != null) {
                    $head->last_message_body = $lastMesg->body;
                    $head->last_message_time = $lastMesg->created_at->toDateTimeString();
                    $head->last_message_status = $lastMesg->status;
                    $head->last_message_sender_id = $lastMesg->sender_id;
                    $head->is_last_message_mine = ($lastMesg->sender_id == $me->id);
                } else {
                    // Default values for new chats without messages
                    $head->last_message_body = 'Chat started';
                    $head->last_message_time = $head->created_at->toDateTimeString();
                    $head->last_message_status = 'new';
                    $head->last_message_sender_id = null;
                    $head->is_last_message_mine = false;
                }

                $heads[] = $head;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->success($heads, 'Success');
    }


    public function chat_messages(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u != null) {
            $u->last_online_at = now();
            $u->save();
        }
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (isset($r->chat_head_id) && $r->chat_head_id != null) {
            $messages = ChatMessage::where([
                'chat_head_id' => $r->chat_head_id
            ])->get();
            return $this->success($messages, 'Success');
        }
        $messages = ChatMessage::where([
            'sender_id' => $u->id
        ])->orWhere([
            'receiver_id' => $u->id
        ])->get();
        return $this->success($messages, 'Success');
    }


    public function chat_mark_as_read(Request $r)
    {
        $receiver = Administrator::find($r->receiver_id);
        if ($receiver == null) {
            return $this->error('Receiver not found.');
        }
        $chat_head = ChatHead::find($r->chat_head_id);
        if ($chat_head == null) {
            return $this->error('Chat head not found.');
        }
        ChatMessage::where([
            'chat_head_id' => $chat_head->id,
            'receiver_id' => $receiver->id,
        ])->where('status', '!=', 'read')->update(['status' => 'read']);
        return $this->success(null, 'Marked as read for chat head: ' . $chat_head->id . ' and receiver: ' . $receiver->id);
    }

    public function chat_send(Request $r)
    {

        $u = Utils::get_user($r);
        if ($u != null) {
            $u->last_online_at = now();
            $u->save();
        }
        $sender = $u;
        if ($sender == null) {
            return $this->error('Sender not found.');
        }

        $user_id = $r->user;
        if ($sender == null) {
            $sender = Administrator::find($user_id);
        }

        if ($sender == null) {
            return $this->error('User not found.');
        }
        $receiver = User::find($r->receiver_id);
        if ($receiver == null) {
            return $this->error('Receiver not found.');
        }


        $chat_head = ChatHead::find($r->chat_head_id);

        if ($chat_head == null) {
            return $this->error('Chat head not found.');
        }

        // Determine message type (text or audio)
        $message_type = $r->type ?? 'text';
        $message_body = $r->body ?? '';
        $audio_url = null;
        $audio_duration = null;
        
        // Handle audio message
        if ($message_type === 'audio') {
            if ($r->hasFile('audio')) {
                // Upload audio file
                $audio_file = $r->file('audio');
                $audio_path = $audio_file->store('chat_audio', 'public');
                $audio_url = url('storage/' . $audio_path);
            } elseif ($r->audio_url) {
                $audio_url = $r->audio_url;
            }
            
            if (empty($audio_url)) {
                return $this->error('Audio file or URL is required for audio messages.');
            }
            
            $audio_duration = $r->audio_duration ?? 0;
            $message_body = '🎵 Voice message';
        }

        $chat_message = new ChatMessage();
        $chat_message->chat_head_id = $chat_head->id;
        $chat_message->sender_id = $sender->id;
        $chat_message->receiver_id = $receiver->id;
        $chat_message->sender_name = $sender->name;
        $chat_message->sender_photo = $sender->photo;
        $chat_message->receiver_name = $receiver->name;
        $chat_message->receiver_photo = $receiver->photo;
        $chat_message->body = $message_body;
        $chat_message->type = $message_type;
        $chat_message->audio_url = $audio_url;
        $chat_message->audio_duration = $audio_duration;
        $chat_message->status = 'sent';
        $chat_message->save();
        
        $last_body = $message_type === 'audio' ? '🎵 Voice message' : $message_body;
        $chat_head->last_message_body = $last_body;
        $chat_head->last_message_time = Carbon::now();
        $chat_head->last_message_status = 'sent';
        $chat_head->save();
        return $this->success($chat_message, 'Success');
    }


    public function file_uploading(Request $r)
    {
        $path = Utils::file_upload($r->file('photo'));
        if ($path == '') {
            Utils::error("File not uploaded.");
        }
        Utils::success([
            'file_name' => $path,
        ], "File uploaded successfully.");
    }

    public function manifest(Request $r)
    {
        // Raise memory limit — V1 manifest loads many movie sections concurrently
        @ini_set('memory_limit', '2G');

        $u = Utils::get_user($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        // Throttled payment check: only once per 5 minutes per user (avoids blocking manifest with external Pesapal API calls)
        try {
            $paymentCacheKey = "v1_pay_check_{$u->id}";
            if (!Cache::has($paymentCacheKey)) {
                Cache::put($paymentCacheKey, true, 300); // 5 min throttle
                try {
                    $pendingPayments = SubscriptionTransaction::whereNotIn('status', ['Completed'])
                        ->where('created_at', '>=', Carbon::now()->subHours(72))
                        ->where('user_id', $u->id)
                        ->orderBy('id', 'desc')
                        ->limit(3)
                        ->get();
                    foreach ($pendingPayments as $pay) {
                        if ($pay->status == 'Completed') {
                            continue;
                        }
                        $number_of_times_checked = (int) $pay->number_of_times_checked;
                        if ($number_of_times_checked > 20) {
                            $pay->status = 'Failed';
                            $pay->refund_reason = 'Payment not completed after multiple checks.';
                            $pay->save();
                            continue;
                        }
                        try {
                            $pay->check_payment_status();
                        } catch (\Throwable $th) {
                        }
                    }
                } catch (\Throwable $th) {
                }
            }
        } catch (\Throwable $th) {
            \Log::warning('v1_manifest: payment cache check failed', ['error' => $th->getMessage()]);
        }
        // $u->autoAssignFreeTrial();

        $APP_VERSION = 20;
        $UPDATE_NOTES = "- Fixed download disappearance bug\n- Added subscription management with free trial\n- Google Sign-In now available\n- Dashboard statistics for watchlist and favorites\n- Improved movie recommendations\n- Fixed profile photo upload issues";
        $WHATSAPP_CONTAT_NUMBER = "+256706638494";
        $take_only = ['id', 'title', 'url', 'thumbnail_url', 'description', 'genre', 'type', 'vj', 'is_premium', 'category_id', 'category'];
        $date = Carbon::parse('2020-01-01 00:00:00');


        // ═══════════════════════════════════════════════════════════
        //  DAILY ROTATION — movies cycle based on last_listing_date
        //  Cached for 10 min to avoid repeated heavy queries
        // ═══════════════════════════════════════════════════════════
        $oldest_listed_movies = collect();
        try {
        $oldest_listed_movies = Cache::remember('v1_rotation_movies', 600, function () use ($take_only) {
            $today_6am = Carbon::today()->addHours(6);
            if (Carbon::now()->hour < 6) {
                $today_6am = Carbon::yesterday()->addHours(6);
            }

            $todays_batch = MovieModel::where([
                'status' => 'Active',
                'type' => 'Movie',
            ])
                ->where('is_muno', 'Yes')
                ->whereNotNull('last_listing_date')
                ->where('last_listing_date', '>=', $today_6am)
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get($take_only);

            if ($todays_batch->count() < 200) {
                $already_listed_ids = $todays_batch->pluck('id')->toArray();
                $needed = 200 - $todays_batch->count();

                $to_stamp = MovieModel::where([
                    'status' => 'Active',
                    'type' => 'Movie',
                ])
                    ->where('is_muno', 'Yes')
                    ->whereNull('last_listing_date')
                    ->whereNotIn('id', $already_listed_ids)
                    ->orderBy('created_at', 'desc')
                    ->limit($needed)
                    ->get($take_only);

                if ($to_stamp->count() < $needed) {
                    $exclude_ids = array_merge($already_listed_ids, $to_stamp->pluck('id')->toArray());
                    $still_needed = $needed - $to_stamp->count();
                    $extra = MovieModel::where([
                        'status' => 'Active',
                        'type' => 'Movie',
                    ])
                        ->where('is_muno', 'Yes')
                        ->whereNotIn('id', $exclude_ids)
                        ->orderBy('last_listing_date', 'asc')
                        ->limit($still_needed)
                        ->get($take_only);
                    $to_stamp = $to_stamp->merge($extra);
                }

                if ($to_stamp->isNotEmpty()) {
                    DB::table('movie_models')
                        ->whereIn('id', $to_stamp->pluck('id')->toArray())
                        ->update(['last_listing_date' => $today_6am]);
                }
            }

            $result = MovieModel::where([
                'status' => 'Active',
                'type' => 'Movie',
            ])
                ->where('is_muno', 'Yes')
                ->whereNotNull('last_listing_date')
                ->where('last_listing_date', '>=', $today_6am)
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get($take_only);

            if ($result->count() < 10) {
                $result = MovieModel::where([
                    'status' => 'Active',
                    'type' => 'Movie',
                ])
                    ->where('is_muno', 'Yes')
                    ->orderBy('last_listing_date', 'desc')
                    ->limit(200)
                    ->get($take_only);
            }

            if ($result->count() === 0) {
                $result = MovieModel::where([
                    'status' => 'Active',
                    'type' => 'Movie',
                ])
                    ->orderBy('created_at', 'desc')
                    ->limit(200)
                    ->get($take_only);
            }

            return $result;
        });
        } catch (\Throwable $th) {
            \Log::warning('v1_manifest: rotation movies cache failed — falling back to simple query', ['error' => $th->getMessage()]);
            try {
                $oldest_listed_movies = MovieModel::where('status', 'Active')->where('type', 'Movie')->where('is_muno', 'Yes')
                    ->orderBy('created_at', 'desc')->limit(50)->get($take_only);
            } catch (\Throwable $e) {
                $oldest_listed_movies = collect();
            }
        }


        $now = Carbon::now();
        $today = $now->format('d');
        $topMovie = null;

        // Safely get top movie — cached 10 min
        try {
            $topMovie = Cache::remember('v1_top_movie', 600, function () {
                return TrendingNotification::getTrendingMovie();
            });
        } catch (\Throwable $th) {
        }



        $lists = [];
        $movies = $oldest_listed_movies;

        //add latest movies list
        $my_list = [];
        $my_list['title'] = "Latest Movies";
        try {
            $my_list['movies'] = Cache::remember('v1_latest_movies', 300, function () use ($take_only) {
                return MovieModel::where('status', 'Active')
                    ->where('type', 'Movie')
                    ->where('is_muno', 'Yes')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get($take_only);
            });
        } catch (\Throwable $th) {
            $my_list['movies'] = collect();
        }
        $lists[] = $my_list;



        //watched_movies continue watching - cached per user for 2 min
        $my_list = [];
        $my_list['title'] = "Continue Watching";

        if ($u && $u->id) {
            try {
                $my_list['movies'] = Cache::remember("v1_watching_{$u->id}", 120, function () use ($u, $take_only) {
                    $watched_movies = MovieView::where('user_id', $u->id)
                        ->orderBy('updated_at', 'desc')
                        ->limit(50)
                        ->get();

                    if ($watched_movies->count() > 0) {
                        $watchedIds = $watched_movies->pluck('movie_model_id')->unique()->toArray();
                        $watchedMoviesMap = MovieModel::whereIn('id', $watchedIds)->get($take_only)->keyBy('id');
                        return $watched_movies->map(function ($view) use ($watchedMoviesMap) {
                            return $watchedMoviesMap->get($view->movie_model_id);
                        })->filter()->values();
                    }
                    return collect();
                });
            } catch (\Throwable $th) {
                $my_list['movies'] = collect();
            }
        } else {
            $my_list['movies'] = [];
        }

        $lists[] = $my_list;

        //top movies - cached globally for 5 min
        if (count($movies) > 10) {
            try {
                $top_movies = Cache::remember('v1_top_movies', 300, function () use ($take_only) {
                    return MovieModel::where('status', 'Active')
                        ->where('type', 'Movie')
                        ->where('is_muno', 'Yes')
                        ->orderBy('views_time_count', 'desc')
                        ->limit(20)
                        ->get($take_only);
                });

                if ($top_movies->count() > 0) {
                    $my_list = [];
                    $my_list['title'] = "Top Movies";
                    $my_list['movies'] = $top_movies;
                    $lists[] = $my_list;
                }
            } catch (\Throwable $th) {
            }
        }



        //trending movies - cached globally for 5 min
        if (count($movies) > 20) {
            try {
                $trending_movies = Cache::remember('v1_trending_movies', 300, function () use ($take_only) {
                    return MovieModel::where('status', 'Active')
                        ->where('type', 'Movie')
                        ->where('is_muno', 'Yes')
                        ->orderBy('downloads_count', 'desc')
                        ->limit(30)
                        ->get($take_only);
                })->shuffle();

                if ($trending_movies->count() > 0) {
                    $my_list = [];
                    $my_list['title'] = "Trending Movies";
                    $my_list['movies'] = $trending_movies;
                    $lists[] = $my_list;
                }
            } catch (\Throwable $th) {
            }
        }


        // For You — shuffled selection from today's batch
        if (count($movies) > 10) {
            $for_you = $movies->shuffle()->take(20);
            $my_list = [];
            $my_list['title'] = "For You";
            $my_list['movies'] = $for_you->values();
            $lists[] = $my_list;
        }

        // Genre sections — cached per genre for 10 min
        $genre_sections = ['Drama', 'Action', 'Comedy', 'Romance', 'Thriller'];
        $dayOfYear = Carbon::today()->dayOfYear;
        foreach ($genre_sections as $genre_name) {
            try {
                $genre_movies = Cache::remember("v1_genre_{$genre_name}_{$dayOfYear}", 600, function () use ($genre_name, $dayOfYear, $take_only) {
                    $genre_query = MovieModel::where('status', 'Active')
                        ->where('type', 'Movie')
                        ->where('is_muno', 'Yes')
                        ->where('genre', 'LIKE', "%{$genre_name}%");

                    $genre_total = $genre_query->count();
                    if ($genre_total < 3) return collect();

                    $genre_pages = max(1, (int) ceil($genre_total / 20));
                    $genre_page  = $dayOfYear % $genre_pages;
                    $result = (clone $genre_query)
                        ->orderByRaw('RAND(' . crc32($genre_name) . ')')
                        ->offset($genre_page * 20)
                        ->limit(20)
                        ->get($take_only);

                    if ($result->count() < 3) {
                        $result = (clone $genre_query)
                            ->orderByRaw('RAND(' . crc32($genre_name) . ')')
                            ->limit(20)
                            ->get($take_only);
                    }

                    return $result;
                });

                if ($genre_movies->count() >= 3) {
                    $my_list = [];
                    $my_list['title'] = "{$genre_name} Movies";
                    $my_list['movies'] = $genre_movies;
                    $lists[] = $my_list;
                }
            } catch (\Throwable $th) {
            }
        }




        $unique_genres = [];
        try {
            $unique_genres = Cache::remember('v1_manifest_genres', 600, function () {
                $unique_genres = [];
                try {
                    $sql = "SELECT DISTINCT genre FROM movie_models WHERE genre IS NOT NULL AND genre != ''";
                    $genres = DB::select($sql);
                    foreach ($genres as $key => $genre) {
                        if (isset($genre->genre) && !empty($genre->genre)) {
                            $slilts = explode(",", $genre->genre);
                            foreach ($slilts as $key => $slit) {
                                $slit = trim($slit);
                                if (strlen($slit) > 0 && !in_array($slit, $unique_genres)) {
                                    $unique_genres[] = $slit;
                                }
                            }
                        }
                    }

                    $temp_genres = $unique_genres;
                    $unique_genres = [];
                    //slits using /
                    foreach ($temp_genres as $key => $genre) {
                        if (!empty($genre)) {
                            $slilts = explode("/", $genre);
                            foreach ($slilts as $key => $slit) {
                                $slit = trim($slit);
                                if (strlen($slit) >= 2 && !in_array($slit, $unique_genres)) {
                                    $unique_genres[] = $slit;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $unique_genres = [];
                }
                return $unique_genres;
            });
        } catch (\Throwable $th) {
            $unique_genres = [];
        }

        $unique_vj = [];
        try {
            $unique_vj = Cache::remember('v1_manifest_vjs', 600, function () {
                $unique_vj = [];
                try {
                    $sql = "SELECT DISTINCT vj FROM movie_models WHERE vj IS NOT NULL AND vj != ''";
                    $vjs = DB::select($sql);
                    foreach ($vjs as $key => $vj) {
                        if (isset($vj->vj) && !empty($vj->vj)) {
                            $slilts = explode(",", $vj->vj);
                            foreach ($slilts as $key => $slit) {
                                $slit = trim($slit);
                                $slit = str_replace(["vj", "VJ", "Vj"], "", $slit);
                                $slit = str_replace([" ", "-"], "", $slit);
                                if (strlen($slit) > 0 && !in_array($slit, $unique_vj)) {
                                    $unique_vj[] = $slit;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $unique_vj = [];
                }
                return $unique_vj;
            });
        } catch (\Throwable $th) {
            $unique_vj = [];
        }

        $platform_type = Utils::get_platform();
        $iosMovies = collect();
        if ($platform_type == 'ios') {
            try {
                $iosMovies = Cache::remember('v1_ios_movies', 600, function () use ($take_only) {
                    return MovieModel::where(['platform_type' => 'ios'])->limit(100)->get($take_only);
                });
            } catch (\Throwable $th) {
            }
        }

        if ($platform_type == 'ios' && $iosMovies->count() > 0) {
            $lists = [];
            $item = [];
            $item['title'] = 'Continue Watching';
            $item['movies'] = $iosMovies;
            $lists[] = $item;

            $item = [];
            $item['title'] = 'Featured Movies';
            $iosMovies = $iosMovies->shuffle();
            $item['movies'] = $iosMovies;
            $lists[] = $item;

            $iosMovies = $iosMovies->shuffle();
            if ($iosMovies->count() > 0) {
                $topMovie = $iosMovies->first();
            }
        } else {
            // For non-iOS platforms, ensure topMovie is from the main movies list
            if ($topMovie == null && $movies->count() > 0) {
                $topMovie = $movies->first();
            }
        }
        // Get subscription information for authenticated user - cached per user for 2 min
        $subscription_info = [
            'has_active_subscription' => false,
            'days_remaining' => 0,
            'hours_remaining' => 0,
            'is_in_grace_period' => false,
            'subscription_status' => 'No Active Subscription',
            'end_date' => null,
            'require_subscription' => true, // Flag to trigger frontend redirect
        ];

        if ($u && $u->id) {
            try {
                $subscription_info = Cache::remember("v1_sub_info_{$u->id}", 120, function () use ($u) {
                    $subscription_status = $u->getSubscriptionStatus();

                    // CRITICAL: Validate subscription data consistency
                    $has_active = $subscription_status['has_active_subscription'] ?? false;
                    $days_remaining = $subscription_status['days_remaining'] ?? 0;
                    $status = $subscription_status['status'] ?? 'No Active Subscription';

                    // VALIDATION: If days_remaining > 0 or status is Active, has_active_subscription MUST be true
                    if (($days_remaining > 0 || $status === 'Active') && !$has_active) {
                        \Log::error('🚨 CRITICAL: Subscription data inconsistency detected!', [
                            'user_id' => $u->id,
                            'has_active_subscription' => $has_active,
                            'days_remaining' => $days_remaining,
                            'status' => $status,
                            'ERROR' => 'has_active_subscription is false but subscription appears active',
                        ]);

                        // FIX IT: Force has_active_subscription to true if logic indicates active
                        $has_active = true;
                    }

                    return [
                        'has_active_subscription' => $has_active,
                        'days_remaining' => $days_remaining,
                        'hours_remaining' => $subscription_status['hours_remaining'] ?? 0,
                        'is_in_grace_period' => $subscription_status['is_in_grace_period'] ?? false,
                        'subscription_status' => $status,
                        'end_date' => $subscription_status['end_date'] ?? null,
                        'require_subscription' => !$has_active,
                    ];
                });
            } catch (\Exception $e) {
                // If subscription check fails, use default values
                \Log::error('💥 Failed to get subscription status in manifest', [
                    'user_id' => $u->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Get dashboard statistics for authenticated user
        $dashboard_stats = [
            'watchlist_count' => 0,
            'watch_history_count' => 0,
            'liked_movies_count' => 0,
            'products_count' => 0,
            'active_chats_count' => 0,
            'total_orders_count' => 0,
        ];

        if ($u && $u->id) {
            try {
                $dashboard_stats = Cache::remember("v1_dash_stats_{$u->id}", 120, function () use ($u) {
                    $stats = [
                        'watchlist_count' => 0,
                        'watch_history_count' => 0,
                        'liked_movies_count' => 0,
                        'products_count' => 0,
                        'active_chats_count' => 0,
                        'total_orders_count' => 0,
                    ];
                    $stats['watchlist_count'] = \App\Models\MovieWishlist::where('user_id', $u->id)->count();
                    $stats['watch_history_count'] = \App\Models\MovieView::where('user_id', $u->id)->count();
                    $stats['liked_movies_count'] = \App\Models\MovieLike::where('user_id', $u->id)->count();
                    $stats['products_count'] = \App\Models\Product::where('user_id', $u->id)->count();
                    $sent_count = \App\Models\ChatMessage::where('sender_id', $u->id)
                        ->distinct('receiver_id')->count('receiver_id');
                    $received_count = \App\Models\ChatMessage::where('receiver_id', $u->id)
                        ->distinct('sender_id')->count('sender_id');
                    $stats['active_chats_count'] = $sent_count + $received_count;
                    return $stats;
                });
            } catch (\Exception $e) {
                // If stats collection fails, use default values (already set above)
            }
        }


        // ✅ MOVIES NOW FREELY BROWSABLE - No subscription required for listing
        // Subscription is enforced only on:
        // 1. Movie Details Page (Flutter - MovieDetailScreen)
        // 2. Video Player Page (Flutter - VideoPlayerScreen)

        $manifest = [
            'top_movie' => $topMovie ? [$topMovie] : [],
            'vj' => $unique_vj ?? [],
            'platform_type' => Utils::get_platform(),
            'genres' => $unique_genres ?? [],
            'APP_VERSION' => $APP_VERSION ?? 20,
            'lists' => $lists ?? [],
            'UPDATE_NOTES' => $UPDATE_NOTES ?? '',
            'WHATSAPP_CONTAT_NUMBER' => $WHATSAPP_CONTAT_NUMBER ?? '',
            'subscription' => $subscription_info, // Add subscription information
            'dashboard_stats' => $dashboard_stats, // Add dashboard statistics
            'client_app_version' => Utils::get_app_version($r), // Return client's app version for debugging
            'safemode_auth' => [ // SafeMode auto-login credentials for MunoWatch
                // Pre-authenticated session (try this first - faster!)
                'user_id' => 169464,
                'session_id' => 'e5d66b26a9392f23e0236221cd260ff1',
                'username' => 'Imdaad',
                'avatar' => 'https://lh3.googleusercontent.com/a/ACg8ocIbh-PGzTzJDeqeMXSyhJZDfZ70cWI1cDTtvwUsSl3cWXzZew=s96-c',
                
                // Fallback: login credentials (if session expired)
                'email' => 'Jumaperejunior@gmail.com',
                'password' => base64_encode('uganda7766'), // Base64 encoded for basic obfuscation
                'api_endpoint' => 'https://munowatch.org/api/users/login/v2',
                'dashboard_endpoint' => 'https://munowatch.org/api/dashboard/v2',
                'bearer_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0',
            ],
        ];

        // Write pre-bootstrap cache (per user+app_type)
        $cacheDir = storage_path('api_cache');
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        $pbcUid = $u ? $u->id : '0';
        $pbcApp = $r->get('app_type', '');
        $pbcKey = 'u_' . md5('/api/manifest' . $pbcUid . $pbcApp);
        @file_put_contents("{$cacheDir}/{$pbcKey}", json_encode(['code' => 1, 'message' => 'Listed successfully.', 'data' => $manifest]));

        // LiteSpeed Cache: headers set by lscache middleware on route

        return response()->json([
            'code' => 1,
            'message' => 'Listed successfully.',
            'data' => $manifest,
        ]);
    }

    public function my_list(Request $r, $model)
    {
        /* $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        } */

        // Map lowercase/incorrect model names to actual model classes
        $modelMapping = [
            'movies' => 'MovieModel',
            'movie' => 'MovieModel',
            'moviemodel' => 'MovieModel',
            'seriesmovies' => 'SeriesMovie',
            'seriesmovie' => 'SeriesMovie',
            'chatmessages' => 'ChatMessage',
            'chatmessage' => 'ChatMessage',
            'chatheads' => 'ChatHead',
            'chathead' => 'ChatHead',
            'products' => 'Product',
            'users' => 'User',
            'subscriptions' => 'Subscription',
            'subscriptionplans' => 'SubscriptionPlan',
            'subscriptionplan' => 'SubscriptionPlan',
        ];

        // Use mapped name if exists, otherwise use the provided model name
        $modelName = $modelMapping[strtolower($model)] ?? $model;

        // ✅ MOVIES NOW FREELY BROWSABLE - No subscription required for listing
        // Subscription is enforced only on:
        // 1. Movie Details Page (Flutter - MovieDetailScreen)
        // 2. Video Player Page (Flutter - VideoPlayerScreen)

        $model = "App\Models\\" . $modelName;

        $data = $model::limit(200)->get();
        Utils::success($data, "Listed successfully. " . $model);
    }

    public function get_movies(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }

        // 🔒 APP VERSION CHECK: Only return movies if app version > 19 OR user has active subscription
        $app_version = Utils::get_app_version($r);
        $can_show_movies = $app_version > 19 || $u->hasActiveSubscription(); {
            $u = Utils::get_user($r);
            if ($u == null) {
                Utils::error("Unauthonticated.");
            }

            // ✅ MOVIES NOW FREELY BROWSABLE - No subscription required for listing
            // Subscription is enforced only on:
            // 1. Movie Details Page (Flutter - MovieDetailScreen)
            // 2. Video Player Page (Flutter - VideoPlayerScreen)

            $model = "App\Models\\MovieModel";
            $data = MovieModel::where('status', 'Active')
                ->where('is_muno', 'Yes')
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get();

            // Batch-load views and likes for this user (2 queries instead of N*2)
            $movieIds = $data->pluck('id')->toArray();
            $userViews = DB::table('movie_views')
                ->where('user_id', $u->id)
                ->whereIn('movie_model_id', $movieIds)
                ->get()
                ->keyBy('movie_model_id');
            $userLikes = DB::table('movie_likes')
                ->where('user_id', $u->id)
                ->where('status', 'Active')
                ->whereIn('movie_model_id', $movieIds)
                ->pluck('movie_model_id')
                ->flip();

            foreach ($data as $movie) {
                $view = $userViews->get($movie->id);
                if ($view) {
                    $movie->watched_movie = 'Yes';
                    $movie->watch_progress = $view->progress;
                    $movie->watch_status = '';
                }
                $movie->liked_movie = $userLikes->has($movie->id) ? 'Yes' : 'No';
            }

            Utils::success($data, "Listed successfully.");
        }
    }





    public function save_view_progress(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }

        $movieId = $r->get('movie_id');

        // Throttle: only save progress once every 30 seconds per user+movie
        $throttleKey = "svp_{$u->id}_{$movieId}";
        if (Cache::has($throttleKey)) {
            // Return success without doing any DB work
            Utils::success(null, "Progress saved successfully.");
            return;
        }

        $movie = MovieModel::find($movieId);
        if ($movie == null) {
            Utils::error("Movie not found.");
        }

        // last_online_at column doesn't exist in production - skip the update
        // (app_type, platform, last_online_at columns are not in the users table)

        $view = MovieView::where([
            'movie_model_id' => $movie->id,
            'user_id' => $u->id,
        ])->first();
        if ($view == null) {
            $view = new MovieView();
            $view->movie_model_id = $movie->id;
            $view->user_id = $u->id;
        }
        $view->progress = $r->get('progress');
        $view->max_progress = $r->get('max_progress');
        $view->status = $r->get('status');
        $view->save();

        // Set throttle for 30 seconds
        Cache::put($throttleKey, true, 30);

        Utils::success($view, "Progress saved successfully.");
    }
    public function my_update(Request $r, $model)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }
        $model = "App\Models\\" . $model;
        $object = $model::find($r->id);
        $isEdit = true;
        if ($object == null) {
            $object = new $model();
            $isEdit = false;
        }

        // Debug: Log file upload attempt
        if ($r->hasFile('photo')) {
            
        } else {
            
        }


        $table_name = $object->getTable();
        $columns = Schema::getColumnListing($table_name);
        $except = ['id', 'created_at', 'updated_at', 'password', 'remember_token', 'company_id', 'status', 'deleted_at'];
        $data = $r->all();

        // Define numeric fields that need validation
        $numericFields = ['latitude', 'longitude', 'height_cm', 'age_range_min', 'age_range_max', 'max_distance_km'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $columns)) {
                continue;
            }
            if (in_array($key, $except)) {
                continue;
            }

            // Clean and validate numeric fields
            if (in_array($key, $numericFields)) {
                // Skip if value is empty
                if (empty($value) || !is_numeric($value)) {
                    continue;
                }

                // Validate and sanitize based on field type
                switch ($key) {
                    case 'latitude':
                        $numValue = floatval($value);
                        if ($numValue >= -90 && $numValue <= 90) {
                            $object->$key = $numValue;
                        }
                        break;
                    case 'longitude':
                        $numValue = floatval($value);
                        if ($numValue >= -180 && $numValue <= 180) {
                            $object->$key = $numValue;
                        }
                        break;
                    case 'height_cm':
                        $numValue = intval($value);
                        if ($numValue >= 100 && $numValue <= 250) {
                            $object->$key = $numValue;
                        }
                        break;
                    case 'age_range_min':
                    case 'age_range_max':
                        $numValue = intval($value);
                        if ($numValue >= 18 && $numValue <= 100) {
                            $object->$key = $numValue;
                        }
                        break;
                    case 'max_distance_km':
                        $numValue = intval($value);
                        if ($numValue >= 1 && $numValue <= 500) {
                            $object->$key = $numValue;
                        }
                        break;
                }
            } else {
                // For non-numeric fields, trim whitespace
                if (is_string($value)) {
                    $object->$key = trim($value);
                } else {
                    $object->$key = $value;
                }
            }
        }
        $object->company_id = $u->company_id;


        //temp_image_field - Handle file uploads (avatar, profile photos, etc.)
        if ($r->temp_file_field != null) {
            if (strlen($r->temp_file_field) > 1) {
                $file = $r->file('photo');
                if ($file != null) {
                    // Validate file
                    if (!$file->isValid()) {
                        Utils::error("Invalid file upload.");
                    }

                    // Validate file type (images only)
                    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($file->getMimeType(), $allowedMimes)) {
                        Utils::error("Invalid file type. Only images are allowed (JPEG, PNG, GIF, WebP).");
                    }

                    // Validate file size (max 5MB)
                    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    if ($file->getSize() > $maxSize) {
                        Utils::error("File too large. Maximum size is 5MB.");
                    }

                    $path = "";
                    try {
                        $path = Utils::file_upload($file);
                    } catch (\Exception $e) {
                        Utils::error("Failed to upload file: " . $e->getMessage());
                    }

                    if (strlen($path) > 3) {
                        $field_name = $r->temp_file_field;
                        // Save the uploaded file path to the specified field
                        $object->$field_name = $path;
                    } else {
                        Utils::error("File upload failed. Please try again.");
                    }
                }
            }
        }

        try {
            $object->save();
        } catch (\Exception $e) {
            Utils::error($e->getMessage());
        }
        $new_object = $model::find($object->id);

        if ($isEdit) {
            Utils::success($new_object, "Updated successfully.");
        } else {
            Utils::success($new_object, "Created successfully.");
        }
    }

    /**
     * @OA\Post(
     *   path="/api/auth/login",
     *   operationId="authLogin",
     *   tags={"Auth"},
     *   summary="Authenticate user with email and password",
     *   description="Returns authenticated user information and JWT token.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *       @OA\Property(property="password", type="string", format="password", example="secret123")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Login successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="integer", example=1),
     *       @OA\Property(property="status", type="integer", example=200),
     *       @OA\Property(property="message", type="string", example="Login successful."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $r)
    {
        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        $user = User::where('email', $r->email)->first();

        if ($user == null) {
            $user = User::where('username', $r->email)->first();
        }

        if ($user == null) {
            $user = User::where('phone_number', $r->email)->first();
        }

        if ($user == null) {
            Utils::error("Account not found.");
        }
        if ($user->status == 'Disabled') {
            Utils::error("Account is disabled.");
        }

        //Disabled

        if (!password_verify($r->password, $user->password)) {
            Utils::error("Invalid password.");
        }



        $token = auth('api')->setTTL(60 * 24 * 365 * 5)->attempt([
            'id' => $user->id,
            'password' => trim($r->password),
        ]);


        if ($token == null) {
            $user->password = password_hash(trim($r->password), PASSWORD_DEFAULT);
            try {
                $user->save();
            } catch (\Exception $e) {
                Utils::error($e->getMessage());
            }
            $user = User::find($user->id);
            $token = auth('api')->setTTL(60 * 24 * 365 * 5)->attempt([
                'id' => $user->id,
                'password' => trim($r->password),
            ]);
        }


        if ($token == null) {
            return $this->error('Wrong credentials.');
        }

        // Update last online timestamp
        $user->last_online_at = now();
        $user->save();

        // Add token to user object for API response (don't save to DB)
        $user_data = $user->toArray();
        $user_data['token'] = $token;
        $user_data['remember_token'] = $token;

        $company = Company::find($user->company_id);
        if ($company == null) {
            Utils::error("Company not found.");
        }

        Utils::success([
            'user' => $user_data,
            'company' => $company,
        ], "Login successful.");
    }

    /**
     * @OA\Post(
     *   path="/api/auth/google",
     *   operationId="authGoogle",
     *   tags={"Auth"},
     *   summary="Authenticate user with Google ID token",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id_token"},
     *       @OA\Property(property="id_token", type="string", example="eyJhbGciOiJSUzI1NiIsImtpZCI6I...")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Google login successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="integer", example=1),
     *       @OA\Property(property="status", type="integer", example=200),
     *       @OA\Property(property="message", type="string", example="Google login successful."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(response=400, description="Invalid Google token")
     * )
     *
     * Google OAuth Authentication
     * Verifies Google ID token and returns JWT token
     */

    // ─────────────────────────────────────────────────────────────────
    // AUTO-REGISTER — Device-based silent account creation
    //
    // Called by the mobile app on first launch when no user is logged in.
    // Generates a unique guest account using the device identifier so the
    // user can start using the app immediately without manual registration.
    //
    // The account is flagged with:
    //   account_origin = 'auto_device'
    //   account_state  = 'auto_created'
    //
    // When the user later completes their profile, the app calls
    //   POST auth/complete-profile  (or PUT account/profile)
    // which upgrades account_state to 'registered'.
    //
    // Security:
    //   - Idempotent: same device_id always returns the same account.
    //   - device_id is combined with a server secret before storage so raw
    //     device IDs in the request cannot be replayed cross-user.
    //   - Throttled via the 'auth' throttle group (shared with login).
    // ─────────────────────────────────────────────────────────────────
    public function auto_register(Request $r)
    {
        $deviceId = trim((string) $r->input('device_id', ''));
        if (strlen($deviceId) < 4) {
            return $this->error('device_id is required (min 4 chars).');
        }

        // Combine device_id with server APP_KEY so raw device IDs cannot be
        // used to probe whether a device exists.
        $hashedDeviceId = hash_hmac('sha256', $deviceId, config('app.key'));

        // Idempotency: if an account for this device already exists, return it.
        $existing = User::where('device_id', $hashedDeviceId)->first();
        if ($existing) {
            $token = auth('api')->setTTL(60 * 24 * 365 * 5)->login($existing);
            if (!$token) {
                return $this->error('Failed to generate session token. Please try again.');
            }

            $existing->last_online_at = now();
            $existing->save();

            $userData            = $existing->toArray();
            $userData['token']   = $token;
            $userData['remember_token'] = $token;

            return $this->success([
                'user'    => $userData,
                'company' => Company::find(1),
            ], 'Auto-login successful.');
        }

        // Build a display name from device info
        $deviceModel    = substr((string) $r->input('device_model', 'Device'), 0, 80);
        $devicePlatform = in_array($r->input('device_platform'), ['android', 'ios'])
            ? $r->input('device_platform')
            : 'android';
        $appType        = Utils::get_app_type($r) ?: 'lugaflix';

        // Generate unique username
        $shortHash = substr($hashedDeviceId, 0, 8);
        $username  = 'guest_' . $shortHash;

        // Ensure username is globally unique (extremely unlikely collision but safe)
        $attempt = 0;
        while (User::where('username', $username)->exists()) {
            $attempt++;
            $username = 'guest_' . $shortHash . '_' . $attempt;
        }

        // Email placeholder — never used for login but required by the schema
        $email = $username . '@auto.lugaflix.app';
        while (User::where('email', $email)->exists()) {
            $email = $username . '_' . uniqid() . '@auto.lugaflix.app';
        }

        // Random secure password (user will never type this — session is token-based)
        $rawPassword = bin2hex(random_bytes(24));

        $newUser                  = new User();
        $newUser->name            = 'Guest User';
        $newUser->first_name      = 'Guest';
        $newUser->last_name       = 'User';
        $newUser->username        = $username;
        $newUser->email           = $email;
        $newUser->password        = password_hash($rawPassword, PASSWORD_DEFAULT);
        $newUser->phone_number    = $email; // placeholder, required by schema
        $newUser->company_id      = 1;
        $newUser->status          = 'Active';
        $newUser->app_type        = $appType;
        $newUser->platform        = $devicePlatform;
        $newUser->account_origin  = 'auto_device';
        $newUser->account_state   = 'auto_created';
        $newUser->device_id       = $hashedDeviceId;
        $newUser->device_model    = $deviceModel;
        $newUser->device_platform = $devicePlatform;

        try {
            $newUser->save();
        } catch (\Exception $e) {
            return $this->error('Failed to create account: ' . $e->getMessage());
        }

        $savedUser = User::find($newUser->id);
        if (!$savedUser) {
            return $this->error('Account creation failed. Please try again.');
        }

        // Assign normal_user role (role_id = 2)
        DB::table('admin_role_users')->insert([
            'user_id'    => $savedUser->id,
            'role_id'    => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = auth('api')->setTTL(60 * 24 * 365 * 5)->login($savedUser);
        if (!$token) {
            return $this->error('Account created but token generation failed. Please login.');
        }

        // Create welcome ticket for the support team welcome-message queue
        try {
            $welcomeTicket = CustomerTicket::create([
                'user_id'                      => $savedUser->id,
                'status'                       => 'open',
                'ticket_type'                  => 'account_opening',
                'resolution_state'             => 'unresolved',
                'subject'                      => 'Welcome message',
                'account_origin'               => 'auto_device',
                'app_type'                     => $appType,
                'platform_type'                => $appType,
                'platform'                     => $devicePlatform,
                'agent_has_contacted_customer' => false,
                'customer_has_responded'       => false,
                'has_unread_user'              => false,
                'has_unread_support'           => true,
                'reply_count'                  => 0,
            ]);

            CustomerTicketRecord::create([
                'customer_ticket_id' => $welcomeTicket->id,
                'sender_type'        => 'auto',
                'sender_id'          => $savedUser->id,
                'message'            => 'Auto-created account registered via device. Device: ' . e($deviceModel) . '. Platform: ' . e($devicePlatform) . '. App: ' . e($appType) . '.',
                'action_type'        => 'status_update',
                'action_description' => 'auto_device_account_created',
                'is_internal_note'   => true,
                'show_to_customer'   => false,
                'is_read_by_user'    => false,
                'is_read_by_support' => false,
                'customer_seen'      => false,
            ]);
        } catch (\Throwable $th) {
            // Welcome ticket failure must NOT block the registration response.
        }

        $userData            = $savedUser->toArray();
        $userData['token']   = $token;
        $userData['remember_token'] = $token;

        return $this->success([
            'user'    => $userData,
            'company' => Company::find(1),
        ], 'Account created and logged in successfully.');
    }

    // ─────────────────────────────────────────────────────────────────
    // COMPLETE PROFILE — Upgrade auto-created account to registered
    //
    // Called after the user fills in their name, email, and/or password
    // from inside the app. Marks the account as 'registered'.
    // ─────────────────────────────────────────────────────────────────
    public function complete_profile(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);

        // Update provided fields — do NOT overwrite if not provided
        if ($r->filled('name')) {
            $names           = explode(' ', trim($r->input('name')), 2);
            $u->first_name   = $names[0];
            $u->last_name    = $names[1] ?? '';
            $u->name         = trim($r->input('name'));
        }
        if ($r->filled('email') && filter_var($r->input('email'), FILTER_VALIDATE_EMAIL)) {
            $existingEmail = User::where('email', $r->input('email'))
                ->where('id', '!=', $u->id)->first();
            if ($existingEmail) {
                return $this->error('Email is already taken by another account.');
            }
            $u->email    = $r->input('email');
            $u->username = $r->input('email');
        }
        if ($r->filled('password') && strlen($r->input('password')) >= 6) {
            $u->password = password_hash($r->input('password'), PASSWORD_DEFAULT);
        }
        if ($r->filled('phone_number')) {
            $u->phone_number = $r->input('phone_number');
        }

        // Mark account as registered
        $u->account_state = 'registered';

        try {
            $u->save();
        } catch (\Exception $e) {
            return $this->error('Failed to update profile: ' . $e->getMessage());
        }

        $freshUser = User::find($u->id);
        $userData  = $freshUser->toArray();
        // Preserve the caller's token — do not regenerate (avoids logging them out)
        $userData['token']          = $r->bearerToken() ?? '';
        $userData['remember_token'] = $userData['token'];

        return $this->success([
            'user'    => $userData,
            'company' => Company::find(1),
        ], 'Profile completed successfully.');
    }

    public function geo_detect(Request $r)
    {
        $ugandaDistricts = [
            'Abim','Adjumani','Agago','Alebtong','Amolatar','Amudat','Amuria','Amuru',
            'Apac','Arua','Budaka','Bududa','Bugiri','Buhweju','Buikwe','Bukedea',
            'Bukomansimbi','Bukwa','Bulambuli','Buliisa','Bundibugyo','Bunyangabu',
            'Bushenyi','Busia','Butaleja','Butebo','Buvuma','Buyende','Dokolo',
            'Gomba','Gulu','Hoima','Ibanda','Iganga','Isingiro','Jinja','Kaabong',
            'Kabale','Kabarole','Kaberamaido','Kagadi','Kakumiro','Kalaki','Kalangala',
            'Kaliro','Kalungu','Kampala','Kamuli','Kamwenge','Kanungu','Kapchorwa',
            'Karenga','Kasanda','Kasese','Katakwi','Kayunga','Kazo','Kibaale',
            'Kiboga','Kibuku','Kikuube','Kiruhura','Kiryandongo','Kisoro','Kitagwenda',
            'Kitgum','Koboko','Kole','Kotido','Kumi','Kwania','Kween','Kyankwanzi',
            'Kyegegwa','Kyenjojo','Kyotera','Lamwo','Lira','Luuka','Luwero',
            'Lwengo','Lyantonde','Madi-Okollo','Manafwa','Maracha','Masaka',
            'Masindi','Mayuge','Mbale','Mbarara','Mitooma','Mityana','Moroto',
            'Moyo','Mpigi','Mubende','Mukono','Nabilatuk','Nakapiripirit','Nakaseke',
            'Nakasongola','Namayingo','Namisindwa','Namutumba','Napak','Nebbi',
            'Ngora','Ntoroko','Ntungamo','Nwoya','Obongi','Omoro','Otuke','Oyam',
            'Pader','Pakwach','Pallisa','Rakai','Rubanda','Rubirizi','Rukiga',
            'Rukungiri','Rwampara','Sembabule','Serere','Sheema','Sironko','Soroti',
            'Tororo','Wakiso','Yumbe','Zombo',
        ];

        $detected = null;
        try {
            $position = Location::get($r->ip());
            if ($position && strtolower((string)$position->countryCode) === 'ug') {
                $detected = [
                    'country'      => 'Uganda',
                    'country_code' => 'UG',
                    'region'       => $position->regionName ?? null,
                    'city'         => $position->cityName ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // IP detection fails silently — client uses manual selection
        }

        return $this->success([
            'detected'         => $detected,
            'uganda_districts' => $ugandaDistricts,
        ], 'Geo info loaded.', 200);
    }

    public function profile_wizard_state(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        return $this->success($this->buildProfileWizardPayload($u), 'Profile wizard state loaded.', 200);
    }

    public function account_merge_wizard_state(Request $r)
    {
        return $this->success([
            'steps' => [],
            'constraints' => [
                'merge_window_open' => false,
                'max_merges_per_account' => 0,
                'uganda_phone_format_example' => '2567XXXXXXXX',
                'preview_token_ttl_seconds' => 0,
            ],
            'my_merge_stats' => [
                'completed_merges' => 0,
                'remaining_merges' => 0,
                'max_merges' => 0,
                'limit_reached' => true,
            ],
        ], 'Account merge wizard state loaded.');
    }

    public function account_merge_preview(Request $r)
    {
        return $this->success([
            'selection_required' => false,
            'accounts' => ['matches' => []],
            'warnings' => [],
        ], 'Account merge is currently unavailable.');
    }

    private function _account_merge_preview_disabled(Request $r)
    {
        $requestUser = $this->resolveProfileWizardUser($r);
        if ($requestUser == null) {
            return $this->error('User not found.', 401);
        }

        /** @var AccountMergeService $mergeService */
        $mergeService = app(AccountMergeService::class);
        if (!$mergeService->isAutoMergeWindowOpen()) {
            return $this->error('Account merge window has closed. Please contact support.');
        }

        // Accept only 'phone' or 'email' as transfer_by (single field search)
        $transferBy = strtolower(trim((string) $r->input('transfer_by', '')));
        if (!in_array($transferBy, ['phone', 'email'], true)) {
            return $this->error('Please choose search method: phone or email.');
        }

        // Normalize the old account contact (single field)
        $oldPhone = $transferBy === 'phone' 
            ? $this->normalizeUgandaPhoneIfPossible((string) $r->input('old_phone_number', ''))
            : '';
        $oldEmail = $transferBy === 'email' 
            ? $this->normalizeMergeEmailIfPossible((string) $r->input('old_email', ''))
            : '';

        // Validate that we have exactly one contact field (the one being searched)
        if ($transferBy === 'phone' && empty($oldPhone)) {
            return $this->error('Please provide a valid Uganda phone number (format: 2567XXXXXXXX).');
        }
        if ($transferBy === 'email' && empty($oldEmail)) {
            return $this->error('Please provide a valid email address.');
        }

        $selectedOldUserId = (int) $r->input('selected_old_user_id', 0);

        // Find all matching old accounts by the single contact field
        $matchedUsers = $this->findMergeUsersByContact($oldPhone, $oldEmail)
            ->filter(fn(User $u) => (int) $u->id !== (int) $requestUser->id)
            ->values();

        if ($matchedUsers->isEmpty()) {
            return $this->error('No account found with this ' . ($transferBy === 'phone' ? 'phone number' : 'email address') . '.');
        }

        $matches = [];
        foreach ($matchedUsers as $candidate) {
            $candidateStats = $mergeService->getMergeStatsForUser((int) $candidate->id);
            $candidateEmail = strtolower(trim((string) ($candidate->email ?? '')));
            $candidatePhone = $this->normalizeUgandaPhoneIfPossible((string) $candidate->phone_number);
            $candidateSubscriptions = DB::table('subscriptions')->where('user_id', $candidate->id)->count();

            $matches[] = array_merge($this->buildMergeAccountPreview($candidate), [
                'email' => $candidateEmail,
                'phone_number' => $candidatePhone,
                'subscriptions_count' => (int) $candidateSubscriptions,
                'merge_stats' => $candidateStats,
                'can_select' => (($candidateStats['limit_reached'] ?? false) !== true),
            ]);
        }

        /** @var User|null $oldUser */
        $oldUser = null;

        if ($selectedOldUserId > 0) {
            $oldUser = $matchedUsers->first(fn(User $u) => (int) $u->id === $selectedOldUserId);
            if ($oldUser == null) {
                return $this->error('Selected account is not in the matched results. Please search again.');
            }
        } elseif ($matchedUsers->count() === 1) {
            $oldUser = $matchedUsers->first();
        }

        if ($oldUser == null) {
            return $this->success([
                'selection_required' => true,
                'selected_old_user_id' => null,
                'accounts' => [
                    'matches' => $matches,
                ],
                'warnings' => [
                    'Multiple accounts were found with this contact.',
                    'Select the exact old account to continue with merge preview.',
                ],
            ], 'Multiple accounts found. Please select one account to continue.');
        }

        // New account is the logged-in user (no need to search)
        $newUser = $requestUser;

        if ((int) $oldUser->id === (int) $newUser->id) {
            return $this->error('Old and new account cannot be the same account.');
        }

        // Check merge limits for both accounts
        $oldStats = $mergeService->getMergeStatsForUser((int) $oldUser->id);
        $newStats = $mergeService->getMergeStatsForUser((int) $newUser->id);
        if (($oldStats['limit_reached'] ?? false) === true) {
            return $this->error('Old account has reached merge limit (6). Cannot proceed with merge.');
        }
        if (($newStats['limit_reached'] ?? false) === true) {
            return $this->error('Your account has reached merge limit (6). Please contact support.');
        }

        $subscriptions = Subscription::query()
            ->with('plan')
            ->where('user_id', $oldUser->id)
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderByDesc('end_date_time')
            ->orderByDesc('id')
            ->get();

        // Count data to be transferred
        $subscriptionsToMove = $subscriptions->count();
        $transactionsToMove = DB::table('subscription_transactions')->where('user_id', $oldUser->id)->count();
        $subscriptionPreviews = $subscriptions
            ->map(fn(Subscription $subscription) => $this->buildMergeSubscriptionPreview($subscription))
            ->values()
            ->all();

        // Generate preview token with single-field metadata
        $previewToken = Str::random(64);
        $cacheKey = 'acct_merge_preview_' . $previewToken;
        Cache::put($cacheKey, [
            'requested_by_user_id' => (int) $requestUser->id,
            'source_user_id' => (int) $oldUser->id,
            'target_user_id' => (int) $newUser->id,
            'transfer_by' => $transferBy,
            'old_phone_number' => $oldPhone,
            'old_email' => $oldEmail,
            'created_at' => now()->toDateTimeString(),
        ], now()->addMinutes(10));

        return $this->success([
            'preview_token' => $previewToken,
            'expires_in_seconds' => 600,
            'selection_required' => false,
            'selected_old_user_id' => (int) $oldUser->id,
            'accounts' => [
                'old' => $this->buildMergeAccountPreview($oldUser),
                'matches' => $matches,
            ],
            'transfer_summary' => [
                'subscriptions_to_move' => (int) $subscriptionsToMove,
                'subscription_transactions_to_move' => (int) $transactionsToMove,
                'subscriptions' => $subscriptionPreviews,
                'merge_limit_per_account' => $mergeService->getMaxMergesPerAccount(),
                'old_account_merge_stats' => $oldStats,
            ],
            'warnings' => [
                'Please confirm the old account details before final merge.',
                'This merge operation is transactional and rollback-safe.',
            ],
        ], 'Old account found. Review and confirm to proceed with merge.');
    }

    public function account_merge_confirm(Request $r)
    {
        return $this->success([
            'accounts_merged' => ['merged' => false],
        ], 'Account merge is currently unavailable.');
    }

    private function _account_merge_confirm_disabled(Request $r)
    {
        $requestUser = $this->resolveProfileWizardUser($r);
        if ($requestUser == null) {
            return $this->error('User not found.', 401);
        }

        $previewToken = trim((string) $r->input('preview_token', ''));
        if ($previewToken === '') {
            return $this->error('Preview token is required.');
        }

        $cacheKey = 'acct_merge_preview_' . $previewToken;
        $previewData = Cache::get($cacheKey);
        if (!is_array($previewData)) {
            return $this->error('Merge preview session expired. Please start again.');
        }

        if ((int) ($previewData['requested_by_user_id'] ?? 0) !== (int) $requestUser->id) {
            return $this->error('This merge session does not belong to your account.', 403);
        }

        $sourceUserId = (int) ($previewData['source_user_id'] ?? 0);
        $targetUserId = (int) ($previewData['target_user_id'] ?? 0);
        if ($sourceUserId < 1 || $targetUserId < 1 || $sourceUserId === $targetUserId) {
            return $this->error('Invalid merge session payload. Please restart merge.');
        }

        /** @var User|null $sourceUser */
        $sourceUser = User::find($sourceUserId);
        /** @var User|null $targetUser */
        $targetUser = User::find($targetUserId);

        if ($sourceUser == null || $targetUser == null) {
            return $this->error('One of the accounts no longer exists. Please restart merge.');
        }

        /** @var AccountMergeService $mergeService */
        $mergeService = app(AccountMergeService::class);

        try {
            $mergedUser = $mergeService->mergeIntoExistingAccount($sourceUser, $targetUser, [
                'reason' => 'manual_merge_wizard_confirmed',
                'match_type' => (string) ($previewData['transfer_by'] ?? 'phone'),
                'request_ip' => $r->ip(),
                'request_user_agent' => (string) $r->userAgent(),
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('ACCOUNT_MERGE_WIZARD_RUNTIME_BLOCK', [
                'source_user_id' => $sourceUserId,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::error('ACCOUNT_MERGE_WIZARD_CONFIRM_FAILED', [
                'source_user_id' => $sourceUserId,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Merge failed and was rolled back. Please try again or contact support.');
        }

        Cache::forget($cacheKey);

        $newToken = auth('api')->setTTL(60 * 24 * 365 * 5)->login($mergedUser);
        if (!$newToken) {
            return $this->error('Merge completed, but failed to create a new session. Please login again.');
        }

        $freshUser = $mergedUser->fresh();
        $userData = $freshUser ? $freshUser->toArray() : $mergedUser->toArray();
        $userData['token'] = $newToken;
        $userData['remember_token'] = $newToken;

        return $this->success([
            'user' => $userData,
            'accounts_merged' => [
                'merged' => true,
                'from_user_id' => $sourceUserId,
                'to_user_id' => (int) $mergedUser->id,
            ],
            'next_steps' => [
                'Use your new account for future login and payments.',
                'If you use third-party integrations, refresh saved account identifiers.',
            ],
        ], 'Accounts merged successfully.');
    }

    public function profile_wizard_personal_info(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $name = trim((string) $r->input('name', ''));
        $sex = strtolower(trim((string) $r->input('sex', '')));
        $dob = trim((string) $r->input('dob', ''));

        if (strlen($name) < 3) {
            return $this->error('Please enter your full name.');
        }

        if ($this->looksLikeAutoGeneratedName($name)) {
            return $this->error('Please enter your real name to continue.');
        }

        $allowedSexes = ['male', 'female', 'other', 'prefer_not_to_say'];
        if (!in_array($sex, $allowedSexes)) {
            return $this->error('Please choose your gender.');
        }

        if (!empty($dob)) {
            try {
                $dobCarbon = Carbon::parse($dob);
                if ($dobCarbon->greaterThan(Carbon::now()->subYears(8))) {
                    return $this->error('Date of birth looks invalid.');
                }
                $u->dob = $dobCarbon->format('Y-m-d');
            } catch (\Throwable $th) {
                return $this->error('Please provide a valid date of birth.');
            }
        } else {
            $u->dob = null;
        }

        $u->name = $name;
        $names = preg_split('/\s+/', $name);
        $u->first_name = $names[0] ?? $name;
        $u->last_name = count($names) > 1 ? implode(' ', array_slice($names, 1)) : '';
        $u->sex = $sex;

        // Professional profile fields (optional)
        $occupation = trim((string) $r->input('occupation', ''));
        $jobTitle   = trim((string) $r->input('job_title', ''));
        $bio        = trim((string) $r->input('bio', ''));
        if ($occupation !== '' && Schema::hasColumn('admin_users', 'occupation')) {
            $u->occupation = $occupation;
        }
        if ($jobTitle !== '' && Schema::hasColumn('admin_users', 'job_title')) {
            $u->job_title = $jobTitle;
        }
        if ($bio !== '' && Schema::hasColumn('admin_users', 'bio')) {
            $u->bio = substr($bio, 0, 500);
        }

        $this->syncProfileWizardProgress($u);
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = max((int) ($u->profile_completion_step ?? 0), 1);
        }
        $u->save();

        return $this->success($this->buildProfileWizardPayload($u), 'Personal details saved.', 200);
    }

    public function profile_wizard_contact(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $phone = trim((string) $r->input('phone_number', ''));
        $email = trim((string) $r->input('email', ''));

        if (empty($phone)) {
            return $this->error('Phone number is required.');
        }

        try {
            $normalizedPhone = $this->normalizeUgandaPhone($phone);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        if (empty($normalizedPhone)) {
            return $this->error('Please enter a valid Uganda phone number.');
        }

        $normalizedEmail = '';
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Please enter a valid email address.');
            }

            if ($this->looksLikeAutoGeneratedEmail($email)) {
                return $this->error('Please enter your real email address.');
            }

            $normalizedEmail = strtolower($email);
        }

        $skipAutoMerge = filter_var(
            $r->input('skip_auto_merge', $r->input('skipAutoMerge', false)),
            FILTER_VALIDATE_BOOLEAN
        ) === true;

        // Auto-merge paused — phone/email are saved directly below.

        $u->phone_number = $normalizedPhone;

        if (!empty($normalizedEmail)) {
            $u->email = $normalizedEmail;
            // Keep username/email in sync for DBs where username is non-null.
            $u->username = $normalizedEmail;
        }

        $this->syncProfileWizardProgress($u);
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = max((int) ($u->profile_completion_step ?? 0), 2);
        }
        $u->save();

        return $this->success($this->buildProfileWizardPayload($u), 'Contact details saved.', 200);
    }

    public function profile_wizard_password(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (!$this->profileWizardRequiresPasswordSetup($u)) {
            return $this->success($this->buildProfileWizardPayload($u), 'Password already configured.', 200);
        }

        $password = (string) $r->input('password', '');
        $passwordConfirmation = (string) $r->input('password_confirmation', '');

        if (strlen(trim($password)) < 6) {
            return $this->error('Password must be at least 6 characters.');
        }

        if ($password !== $passwordConfirmation) {
            return $this->error('Password confirmation does not match.');
        }

        $u->password = password_hash($password, PASSWORD_DEFAULT);
        if ($this->profileWizardHasColumn('last_password_change')) {
            $u->last_password_change = Carbon::now();
        }

        $this->syncProfileWizardProgress($u);
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = max((int) ($u->profile_completion_step ?? 0), 3);
        }
        $u->save();

        return $this->success($this->buildProfileWizardPayload($u), 'Password saved successfully.', 200);
    }

    public function profile_wizard_preferences(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $preferredGenres = $r->input('preferred_genres', []);
        if (is_string($preferredGenres)) {
            $decodedGenres = json_decode($preferredGenres, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $preferredGenres = $decodedGenres;
            } else {
                $preferredGenres = array_filter(array_map('trim', explode(',', $preferredGenres)));
            }
        }

        if (!is_array($preferredGenres)) {
            return $this->error('Preferred genres must be a list.');
        }

        $allowedGenres = collect($this->profileWizardGenreOptions())->pluck('value')->toArray();
        $preferredGenres = array_values(array_unique(array_filter(array_map(function ($genre) use ($allowedGenres) {
            $value = strtolower(trim((string) $genre));
            return in_array($value, $allowedGenres) ? $value : null;
        }, $preferredGenres))));

        if (count($preferredGenres) < 1) {
            return $this->error('Choose at least one genre you like.');
        }

        $contentMaturityLevel = $this->normalizeProfileMaturityLevel(
            strtolower(trim((string) $r->input('content_maturity_level', '')))
        );
        if (empty($contentMaturityLevel)) {
            return $this->error('Please choose your content maturity level.');
        }

        if ($this->profileWizardHasColumn('preferred_genres')) {
            $u->preferred_genres = json_encode($preferredGenres);
        }
        if ($this->profileWizardHasColumn('content_maturity_level')) {
            $u->content_maturity_level = $contentMaturityLevel;
        }

        $this->syncProfileWizardProgress($u);
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = max((int) ($u->profile_completion_step ?? 0), 3);
        }
        $u->save();

        return $this->success($this->buildProfileWizardPayload($u), 'Viewing preferences saved.', 200);
    }

    public function profile_wizard_photo(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $skipPhoto = $this->profileWizardBool($r->input('skip_photo', false));
        try {
            if ($skipPhoto) {
                if ($this->profileWizardHasColumn('profile_photo_skipped')) {
                    $u->profile_photo_skipped = true;
                }
            } else {
                $hasPhotoUpload = $r->hasFile('photo');
                if (!$hasPhotoUpload && !empty($u->avatar)) {
                    // Client may submit without file when avatar already exists.
                    if ($this->profileWizardHasColumn('profile_photo_skipped')) {
                        $u->profile_photo_skipped = false;
                    }
                } else {
                    if (!$hasPhotoUpload) {
                        return $this->error('Please upload a profile photo or skip this step.');
                    }

                    $validator = \Illuminate\Support\Facades\Validator::make($r->all(), [
                        'photo' => 'required|image|mimes:jpeg,jpg,png,webp,heic,heif|max:5120',
                    ]);
                    if ($validator->fails()) {
                        return $this->error($validator->errors()->first());
                    }

                    $uploaded = Utils::file_upload($r->file('photo'));
                    if (empty($uploaded)) {
                        Log::warning('PROFILE_WIZARD_PHOTO_UPLOAD_EMPTY', [
                            'user_id' => (int) $u->id,
                            'file_present' => true,
                        ]);
                        return $this->error('Failed to upload profile photo. Please try a different image.');
                    }

                    $u->avatar = $uploaded;
                    if ($this->profileWizardHasColumn('profile_photo_skipped')) {
                        $u->profile_photo_skipped = false;
                    }
                }
            }

            $this->syncProfileWizardProgress($u);
            if ($this->profileWizardHasColumn('profile_completion_step')) {
                $u->profile_completion_step = max((int) ($u->profile_completion_step ?? 0), 4);
            }
            $u->save();
        } catch (\Throwable $e) {
            Log::error('PROFILE_WIZARD_PHOTO_SAVE_FAILED', [
                'user_id' => (int) $u->id,
                'skip_photo' => $skipPhoto,
                'has_file' => $r->hasFile('photo'),
                'error' => $e->getMessage(),
            ]);
            return $this->error('Unable to save photo preference right now. Please try again.');
        }

        return $this->success($this->buildProfileWizardPayload($u), 'Photo preference saved.', 200);
    }

    public function finish_profile_wizard(Request $r)
    {
        $u = $this->resolveProfileWizardUser($r);
        if ($u == null) {
            return $this->error('User not found.');
        }

        $progress = $this->calculateProfileWizardProgress($u);
        $progressPct = (int) ($progress['progress_pct'] ?? 0);

        if ($progressPct < 100 && !$this->canLenientCompleteProfileWizard($u, $progress)) {
            return $this->error('Please complete all required steps before finishing.');
        }

        if ($progressPct < 100) {
            $missing = array_values(array_map(
                fn($step) => (string) ($step['key'] ?? 'unknown'),
                array_filter($progress['steps'] ?? [], fn($step) => ($step['completed'] ?? false) !== true)
            ));
            Log::warning('PROFILE_WIZARD_LENIENT_FINISH_APPLIED', [
                'user_id' => (int) $u->id,
                'progress_pct' => $progressPct,
                'missing_steps' => $missing,
            ]);
        }

        $u->account_state = 'registered';
        if ($this->profileWizardHasColumn('profile_completed_at')) {
            $u->profile_completed_at = Carbon::now();
        }
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = 5;
        }
        $u->completed_profile_pct = 100;
        $u->save();

        return $this->success($this->buildProfileWizardPayload($u), 'Profile completed successfully.', 200);
    }

    private function canLenientCompleteProfileWizard(User $u, array $progress): bool
    {
        $steps = $progress['steps'] ?? [];
        if (!is_array($steps) || empty($steps)) {
            return false;
        }

        $completedByKey = [];
        foreach ($steps as $step) {
            $key = (string) ($step['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $completedByKey[$key] = (($step['completed'] ?? false) === true);
        }

        // Core completion: identity + reachable contact + photo choice.
        return ($completedByKey['personal_info'] ?? false)
            && ($completedByKey['contact'] ?? false)
            && ($completedByKey['photo'] ?? false);
    }

    private function resolveProfileWizardUser(Request $r): ?User
    {
        $authUser = Utils::get_user($r);
        if ($authUser == null) {
            return null;
        }

        return User::find($authUser->id);
    }

    private function findMergeUsersByContact(?string $normalizedPhone, ?string $normalizedEmail)
    {
        if (!empty($normalizedPhone)) {
            $digits = preg_replace('/\D+/', '', $normalizedPhone);
            $last9 = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
            $phoneColumns = $this->mergePhoneColumns();
            $paymentAccountUserIds = $this->findPaymentAccountUserIdsByPhone($normalizedPhone);

            $variants = array_values(array_unique(array_filter([
                $normalizedPhone,
                '+' . $normalizedPhone,
                $digits,
                strlen($last9) === 9 ? ('0' . $last9) : null,
                $last9,
            ])));

            return User::where(function ($q) use ($variants, $digits, $last9, $phoneColumns, $paymentAccountUserIds) {
                foreach ($phoneColumns as $column) {
                    $q->orWhereIn($column, $variants);

                    if (!empty($digits)) {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                            [$digits]
                        );
                    }

                    if (!empty($last9)) {
                        $q->orWhere($column, 'like', "%{$last9}");
                    }
                }

                if (!empty($paymentAccountUserIds)) {
                    $q->orWhereIn('id', $paymentAccountUserIds);
                }
            })
                ->where(function ($q) {
                    $q->whereNull('account_state')
                        ->orWhere('account_state', '!=', 'merged');
                })
                ->orderByDesc('id')
                ->get();
        }

        if (!empty($normalizedEmail)) {
            return User::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($normalizedEmail))])
                ->where(function ($q) {
                    $q->whereNull('account_state')
                        ->orWhere('account_state', '!=', 'merged');
                })
                ->orderByDesc('id')
                ->get();
        }

        return collect();
    }

    private function normalizeMergeEmailIfPossible(?string $value): string
    {
        $email = strtolower(trim((string) $value));
        if (empty($email)) {
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        if ($this->looksLikeAutoGeneratedEmail($email)) {
            return '';
        }

        return $email;
    }

    private function buildMergeAccountPreview(User $u): array
    {
        $phone = $this->normalizeUgandaPhoneIfPossible($u->phone_number);
        $email = strtolower(trim((string) ($u->email ?? '')));

        return [
            'id' => (int) $u->id,
            'name' => (string) ($u->name ?? ''),
            'first_name' => (string) ($u->first_name ?? ''),
            'last_name' => (string) ($u->last_name ?? ''),
            'email_masked' => $this->maskEmail($email),
            'phone_masked' => $this->maskPhone($phone),
            'account_state' => (string) ($u->account_state ?? ''),
            'app_type' => (string) ($u->app_type ?? ''),
            'created_at' => !empty($u->created_at) ? Carbon::parse($u->created_at)->toDateTimeString() : null,
        ];
    }

    private function buildMergeSubscriptionPreview(Subscription $subscription): array
    {
        $plan = $subscription->plan;
        $amount = $subscription->amount_paid;
        $currency = trim((string) ($subscription->currency ?? 'UGX'));

        return [
            'id' => (int) $subscription->id,
            'plan_name' => (string) ($plan->name ?? 'Subscription'),
            'status' => (string) ($subscription->status ?? ''),
            'payment_status' => (string) ($subscription->payment_status ?? ''),
            'amount_label' => $amount !== null ? trim($currency . ' ' . number_format((float) $amount, 0)) : $currency,
            'days' => (int) ($subscription->days ?? ($plan->duration_days ?? 0)),
            'start_date' => !empty($subscription->start_date_time) ? Carbon::parse($subscription->start_date_time)->toDateTimeString() : null,
            'end_date' => !empty($subscription->end_date_time) ? Carbon::parse($subscription->end_date_time)->toDateTimeString() : null,
            'days_remaining' => method_exists($subscription, 'daysRemaining') ? $subscription->daysRemaining() : null,
            'is_active' => method_exists($subscription, 'isActive') ? $subscription->isActive() : false,
            'is_in_grace_period' => method_exists($subscription, 'isInGracePeriod') ? $subscription->isInGracePeriod() : false,
        ];
    }

    private function maskEmail(string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 2) {
            $maskedLocal = str_repeat('*', strlen($local));
        } else {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '');
        if (empty($digits) || strlen($digits) < 5) {
            return '';
        }

        return substr($digits, 0, 4) . str_repeat('*', max(1, strlen($digits) - 7)) . substr($digits, -3);
    }

    private function findPhoneOwnerForMerge(string $normalizedPhone, int $excludeUserId): ?User
    {
        $digits = preg_replace('/\D+/', '', $normalizedPhone);
        $last9 = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
        $phoneColumns = $this->mergePhoneColumns();
        $paymentAccountUserIds = $this->findPaymentAccountUserIdsByPhone($normalizedPhone);

        $variants = array_values(array_unique(array_filter([
            $normalizedPhone,
            '+' . $normalizedPhone,
            $digits,
            strlen($last9) === 9 ? ('0' . $last9) : null,
            $last9,
        ])));

        return User::where('id', '!=', $excludeUserId)
            ->where(function ($q) use ($variants, $digits, $last9, $phoneColumns, $paymentAccountUserIds) {
                foreach ($phoneColumns as $column) {
                    $q->orWhereIn($column, $variants);

                    if (!empty($digits)) {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                            [$digits]
                        );
                    }

                    if (!empty($last9)) {
                        $q->orWhere($column, 'like', "%{$last9}");
                    }
                }

                if (!empty($paymentAccountUserIds)) {
                    $q->orWhereIn('id', $paymentAccountUserIds);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function findPaymentAccountUserIdsByPhone(string $normalizedPhone): array
    {
        if (!Schema::hasTable('subscription_transactions') || !Schema::hasColumn('subscription_transactions', 'payment_account')) {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $normalizedPhone);
        if (empty($digits)) {
            return [];
        }

        $last9 = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
        $variants = array_values(array_unique(array_filter([
            $normalizedPhone,
            '+' . $normalizedPhone,
            $digits,
            strlen($last9) === 9 ? ('0' . $last9) : null,
            $last9,
        ])));

        return DB::table('subscription_transactions')
            ->whereNotNull('user_id')
            ->where(function ($q) use ($variants, $digits, $last9) {
                $q->whereIn('payment_account', $variants)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(payment_account, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                        [$digits]
                    );

                if (!empty($last9)) {
                    $q->orWhere('payment_account', 'like', "%{$last9}");
                }
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function mergePhoneColumns(): array
    {
        $columns = [];
        foreach (['phone_number', 'phone_number_1', 'phone', 'telephone'] as $column) {
            if (Schema::hasColumn('admin_users', $column)) {
                $columns[] = $column;
            }
        }

        return !empty($columns) ? $columns : ['phone_number'];
    }

    private function buildProfileWizardPayloadWithToken(User $u, string $token, ?array $mergeMeta = null): array
    {
        $payload = $this->buildProfileWizardPayload($u);
        $freshUser = $u->fresh();
        $userData = $freshUser ? $freshUser->toArray() : $u->toArray();
        $userData['token'] = $token;
        $userData['remember_token'] = $token;
        $payload['user'] = $userData;
        if ($mergeMeta != null) {
            $payload['accounts_merged'] = $mergeMeta;
        }

        return $payload;
    }

    private function buildProfileWizardPayload(User $u): array
    {
        $progress = $this->calculateProfileWizardProgress($u);
        $requiresPasswordSetup = $this->profileWizardRequiresPasswordSetup($u);
        $u->completed_profile_pct = $progress['progress_pct'];
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = $progress['current_step'];
        }
        $u->save();

        return [
            'user' => $u->fresh(),
            'progress_pct' => $progress['progress_pct'],
            'current_step' => $progress['current_step'],
            'total_steps' => $progress['total_steps'],
            'should_show_wizard' => $progress['progress_pct'] < 100,
            'requires_password_setup' => $requiresPasswordSetup,
            'steps' => $progress['steps'],
            'form_data' => [
                'name' => $this->looksLikeAutoGeneratedName($u->name) ? '' : (string) ($u->name ?? ''),
                'sex' => (string) ($u->sex ?? ''),
                'dob' => !empty($u->dob) ? Carbon::parse($u->dob)->format('Y-m-d') : '',
                'phone_number' => $this->looksLikeAutoGeneratedPhone($u->phone_number) ? '' : (string) ($u->phone_number ?? ''),
                'email' => $this->looksLikeAutoGeneratedEmail($u->email) ? '' : (string) ($u->email ?? ''),
                'requires_password_setup' => $requiresPasswordSetup,
                'preferred_genres' => $this->decodePreferredGenres($u->preferred_genres),
                'content_maturity_level' => $this->normalizeProfileMaturityLevel((string) ($u->content_maturity_level ?? '')) ?? '',
                'avatar' => (string) ($u->avatar ?? ''),
                'profile_photo_skipped' => $this->profileWizardBool($u->profile_photo_skipped ?? false),
            ],
            'options' => [
                'genres' => $this->profileWizardGenreOptions(),
                'content_maturity_levels' => [
                    ['value' => 'g', 'label' => 'G (General)'],
                    ['value' => 'pg', 'label' => 'PG (Parental Guidance)'],
                    ['value' => 'pg_13', 'label' => 'PG-13'],
                    ['value' => 'r', 'label' => 'R (Restricted)'],
                ],
                'sexes' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                    ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say'],
                ],
            ],
        ];
    }

    private function syncProfileWizardProgress(User $u): void
    {
        $progress = $this->calculateProfileWizardProgress($u);
        $u->completed_profile_pct = $progress['progress_pct'];
        if ($this->profileWizardHasColumn('profile_completion_step')) {
            $u->profile_completion_step = $progress['current_step'];
        }

        if ($progress['progress_pct'] >= 100) {
            if ($this->profileWizardHasColumn('profile_completed_at')) {
                $u->profile_completed_at = $u->profile_completed_at ?? Carbon::now();
            }
        } else {
            if ($this->profileWizardHasColumn('profile_completed_at')) {
                $u->profile_completed_at = null;
            }
        }
    }

    private function calculateProfileWizardProgress(User $u): array
    {
        $requiresPasswordSetup = $this->profileWizardRequiresPasswordSetup($u);
        $steps = [
            [
                'key' => 'personal_info',
                'title' => 'Personal details',
                'completed' => !$this->looksLikeAutoGeneratedName($u->name) && !empty($u->sex),
            ],
            [
                'key' => 'contact',
                'title' => 'Contact details',
                'completed' => !empty($this->normalizeUgandaPhoneIfPossible($u->phone_number)) && !$this->looksLikeAutoGeneratedPhone($u->phone_number),
            ],
        ];

        if ($requiresPasswordSetup) {
            $steps[] = [
                'key' => 'password',
                'title' => 'Set password',
                'completed' => !empty((string) ($u->last_password_change ?? '')),
            ];
        }

        $steps = array_merge($steps, [
            [
                'key' => 'preferences',
                'title' => 'Watching preferences',
                'completed' => count($this->decodePreferredGenres($u->preferred_genres)) > 0 && !empty($u->content_maturity_level),
            ],
            [
                'key' => 'photo',
                'title' => 'Profile photo',
                'completed' => !empty($u->avatar) || $this->profileWizardBool($u->profile_photo_skipped ?? false),
            ],
        ]);

        $completedCount = count(array_filter($steps, fn($step) => $step['completed'] === true));
        $progressPct = (int) round(($completedCount / count($steps)) * 100);
        $currentStep = count($steps) + 1;
        foreach ($steps as $index => $step) {
            if (!$step['completed']) {
                $currentStep = $index + 1;
                break;
            }
        }

        return [
            'steps' => $steps,
            'total_steps' => count($steps),
            'progress_pct' => $progressPct,
            'current_step' => $currentStep,
        ];
    }

    private function profileWizardRequiresPasswordSetup(User $u): bool
    {
        $accountState = strtolower(trim((string) ($u->account_state ?? '')));
        $accountOrigin = strtolower(trim((string) ($u->account_origin ?? '')));

        $isAutoGuest = $accountState === 'auto_created' || $accountOrigin === 'auto_device' || $this->looksLikeAutoGeneratedEmail((string) ($u->email ?? ''));
        if (!$isAutoGuest) {
            return false;
        }

        // Keep password step visible for guest accounts so it remains editable;
        // completion is tracked through last_password_change in step progress.
        return true;
    }

    private function decodePreferredGenres($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function profileWizardGenreOptions(): array
    {
        return [
            ['value' => 'action', 'label' => 'Action'],
            ['value' => 'comedy', 'label' => 'Comedy'],
            ['value' => 'drama', 'label' => 'Drama'],
            ['value' => 'romance', 'label' => 'Romance'],
            ['value' => 'family', 'label' => 'Family'],
            ['value' => 'gospel', 'label' => 'Gospel'],
            ['value' => 'documentary', 'label' => 'Documentary'],
            ['value' => 'series', 'label' => 'Series'],
        ];
    }

    private function looksLikeAutoGeneratedName(?string $value): bool
    {
        $name = strtolower(trim((string) $value));
        if ($name === '') {
            return true;
        }

        foreach (['guest', 'auto user', 'auto account', 'lugaflix user'] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return preg_match('/^user\s*\d+$/', $name) === 1;
    }

    private function looksLikeAutoGeneratedEmail(?string $value): bool
    {
        $email = strtolower(trim((string) $value));
        if ($email === '') {
            return false;
        }

        return str_contains($email, '@auto.') || str_contains($email, '@autogen.') || str_contains($email, 'device-');
    }

    private function looksLikeAutoGeneratedPhone(?string $value): bool
    {
        $phone = preg_replace('/\D+/', '', (string) $value);
        if (empty($phone)) {
            return false;
        }

        return in_array($phone, ['0000000000', '256000000000'], true);
    }

    private function normalizeUgandaPhoneIfPossible(?string $value): string
    {
        try {
            return $this->normalizeUgandaPhone((string) $value);
        } catch (\Throwable $th) {
            return '';
        }
    }

    private function profileWizardBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeProfileMaturityLevel(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'g', 'general' => 'g',
            'pg' => 'pg',
            'pg13', 'pg-13', 'pg_13', 'teens' => 'pg_13',
            'r', 'mature' => 'r',
            default => null,
        };
    }

    private function profileWizardHasColumn(string $column): bool
    {
        static $columnCache = [];

        if (!array_key_exists($column, $columnCache)) {
            $columnCache[$column] = Schema::hasColumn('admin_users', $column);
        }

        return $columnCache[$column] === true;
    }

    public function googleAuth(Request $r)
    {
        // Validate input
        if (!$r->id_token) {
            return $this->error('Google ID token is required.');
        }

        try {
            // Verify Google ID token
            $google_user = $this->verifyGoogleToken($r->id_token);

            if (!$google_user) {
                return $this->error('Invalid Google token.');
            }

            // Check if user exists by email, username, or phone number
            $user = User::where('email', $google_user['email'])
                ->orWhere('username', $google_user['email'])
                ->orWhere('phone_number', $google_user['email'])
                ->first();

            if (!$user) {
                // Create new user if doesn't exist
                $user = new User();
                $user->name = $google_user['name'];
                $user->email = $google_user['email'];
                $user->email_verified_at = now(); // Google accounts are pre-verified
                $user->google_id = $google_user['sub'];
                $user->avatar = $google_user['picture'] ?? null;

                // Set a random password (user will use Google auth)
                $user->password = password_hash(uniqid(), PASSWORD_DEFAULT);

                // Set username (required field)
                $user->username = $google_user['email'];

                // Set default company (adjust as needed)
                $company = Company::first();
                if ($company) {
                    $user->company_id = $company->id;
                } else {
                    return $this->error('No company found. Please contact support.');
                }

                try {
                    $user->save();
                   
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to create Google OAuth user', [
                        'error' => $e->getMessage(),
                        'email' => $google_user['email']
                    ]);
                    return $this->error('Failed to create user account: ' . $e->getMessage());
                }
            } else {
               
                // Update existing user's Google info if needed
                $updated = false;
                if (!$user->google_id) {
                    $user->google_id = $google_user['sub'];
                    $updated = true;
                }
                if (!$user->avatar && isset($google_user['picture'])) {
                    $user->avatar = $google_user['picture'];
                    $updated = true;
                }
                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $updated = true;
                }

                if ($updated) {
                    $user->save();
                }
            }

            // Generate JWT token (10-year TTL)
            try {
                $token = auth('api')->setTTL(60 * 24 * 365 * 10)->attempt(['email' => $user->email], true);
                if (!$token) {
                    $token = auth('api')->setTTL(60 * 24 * 365 * 10)->login($user);
                }
            } catch (\Exception $e) {
                $token = auth('api')->setTTL(60 * 24 * 365 * 10)->login($user);
            }

            if (!$token) {
                return $this->error('Failed to generate authentication token.');
            }

            // Auto-assign free trial if applicable


            // Prepare response data
            $user_data = $user->toArray();
            $user_data['token'] = $token;
            $user_data['remember_token'] = $token;

            $company = Company::find($user->company_id);
            if (!$company) {
                return $this->error("Company not found.");
            }

            return $this->success([
                'user' => $user_data,
                'company' => $company,
            ], "Google login successful.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google auth error: ' . $e->getMessage());
            return $this->error('Google authentication failed. Please try again.');
        }
    }

    /**
     * Verify Google ID token
     */
    private function verifyGoogleToken($id_token)
    {
        try {
            // Use Google's tokeninfo endpoint to verify the token
            $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return false;
            }

            $token_data = json_decode($response, true);

            // Verify token is valid and audience matches (you should set your Google Client ID)
            if (!isset($token_data['email']) || !isset($token_data['email_verified'])) {
                return false;
            }

            // Verify audience (aud) matches one of our Google Client IDs
            $valid_client_ids = [
                '561132731615-vgr65ea6ad5ee0jhr25qv0vic9n0hdic.apps.googleusercontent.com', // Web client (Firebase)
                '1073633720466-fp22v0ttbh8s60fbi3phasq2bcg39gha.apps.googleusercontent.com', // Legacy client
            ];
            if (!in_array($token_data['aud'], $valid_client_ids)) {
                return false;
            }

            return $token_data;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google token verification error: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * @OA\Post(
     *   path="/api/auth/register",
     *   operationId="authRegister",
     *   tags={"Auth"},
     *   summary="Register a new user account",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password"},
     *       @OA\Property(property="name", type="string", example="John Doe"),
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *       @OA\Property(property="password", type="string", format="password", example="secret123"),
     *       @OA\Property(property="app_type", type="string", example="lugaflix"),
     *       @OA\Property(property="platform", type="string", example="android")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Registration successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="integer", example=1),
     *       @OA\Property(property="status", type="integer", example=200),
     *       @OA\Property(property="message", type="string", example="Registration successful."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $r)
    {



        if ($r->name == null) {
            Utils::error("First name is required.");
        }


        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $r->email)->first();
        if ($u != null) {
            //if Disabled
            if ($u->status == 'Disabled') {
                Utils::error("Email is already registered.");
            } else {
                Utils::error("Email is already registered. Please login.");
            }
        }
        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        $name = $r->name;
        $names = explode(" ", $name);
        $first_name = null;
        $last_name = null;
        if (count($names) == 1) {
            $first_name = $names[0];
            $last_name = "";
        } else {
            $first_name = $names[0];
            $last_name = $names[1];
        }

        if ($u != null) {
            $new_user = $u;
        } else {
            $new_user = new User();
        }

        $new_user->first_name = $first_name;
        $new_user->last_name = $last_name;
        $new_user->username = $r->email;
        $new_user->email = $r->email;
        $new_user->password = password_hash($r->password, PASSWORD_DEFAULT);
        $new_user->name = $first_name . " " . $last_name;
        $new_user->phone_number = $r->email;
        $new_user->company_id = 1;
        $new_user->status = "Active";

        // Set app_type and platform from request
        $reg_app_type = Utils::get_app_type($r);
        $valid_app_types = ['ugflix', 'lugaflix', 'muno_app', 'web', 'vjjunior', 'katogo'];
        $new_user->app_type = in_array($reg_app_type, $valid_app_types) ? $reg_app_type : 'ugflix';
        $reg_platform = Utils::get_platform_from_request($r);
        $new_user->platform = $reg_platform ?: 'android';

        try {
            $new_user->save();
        } catch (\Exception $e) {
            Utils::error($e->getMessage());
        }

        $registered_user = User::find($new_user->id);
        if ($registered_user == null) {
            Utils::error("Failed to register user.");
        }


        //DB instert into admin_role_users
        DB::table('admin_role_users')->insert([
            'user_id' => $registered_user->id,
            'role_id' => 2,
        ]);

        // Generate JWT token (same as login)
        $token = auth('api')->setTTL(60 * 24 * 365 * 5)->attempt([
            'id' => $registered_user->id,
            'password' => trim($r->password),
        ]);

        if ($token == null) {
            Utils::error("Registration succeeded but token generation failed. Please login.");
        }

        $user_data = $registered_user->toArray();
        $user_data['token'] = $token;
        $user_data['remember_token'] = $token;

        Utils::success([
            'user' => $user_data,
            'company' => Company::find(1),
        ], "Registration successful.");
    }

    /**
     * @OA\Post(
     *   path="/api/auth/password-reset",
     *   operationId="authPasswordReset",
     *   tags={"Auth"},
     *   summary="Reset account password using reset code",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","code","password"},
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *       @OA\Property(property="code", type="string", example="123456"),
     *       @OA\Property(property="password", type="string", format="password", example="newsecret123")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Password reset successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="integer", example=1),
     *       @OA\Property(property="status", type="integer", example=200),
     *       @OA\Property(property="message", type="string", example="Password reset successful."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function password_reset(Request $r)
    {

        if ($r->code == null) {
            Utils::error("Secret code is required.");
        }

        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $r->email)->first();
        if ($u == null) {
            Utils::error("Account not found with $r->email.");
        }
        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        //check code
        if ($u->secret_code != $r->code) {
            Utils::error("Invalid secret code.");
        }
        //set new password
        $u->password = password_hash($r->password, PASSWORD_DEFAULT);
        $u->secret_code = null;
        $u->save();
        $u = User::find($u->id);
        Utils::success([
            'user' => $u,
            'company' => Company::find(1),
        ], "Password reset successful.");
    }
    /**
     * @OA\Post(
     *   path="/api/auth/request-password-reset-code",
     *   operationId="authRequestPasswordResetCode",
     *   tags={"Auth"},
     *   summary="Request password reset code",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email"},
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Code sent successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="integer", example=1),
     *       @OA\Property(property="status", type="integer", example=200),
     *       @OA\Property(property="message", type="string", example="Code sent successfully."),
     *       @OA\Property(property="data", type="object")
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function request_password_reset_code(Request $r)
    {
        $email = trim((string)$r->email);

        //check if email is provided
        if ($email === '') {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $email)->first();
        if ($u == null) {
            Utils::error("Account not found with $email.");
        }

        if (empty($u->email) || !filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Account email is missing or invalid. Please contact support.");
        }

        $code = rand(100000, 999999);
        $u->secret_code = $code;
        $u->save();

        $recipientName = trim((string)($u->name ?? ''));
        if ($recipientName === '') {
            $recipientName = 'there';
        }

        $mail_body = <<<EOD
            <p>Dear {$recipientName},</p>
            <p>Your password reset code is <b><code>$code</code></b></p>
            <p>Thank you.</p>
            EOD;
        $data['email'] = $u->email;
        $data['subject'] = "Password Reset Code - " . env('APP_NAME');
        $data['body'] = $mail_body;
        $data['data'] = $data['body'];
        $data['name'] = $recipientName;
        try {
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            return Utils::error("Unable to send reset code email. " . $th->getMessage());
        }
        $u = User::find($u->id);
        Utils::success([
            'user' => $u,
        ], "Code sent successfully.");
    }
}
