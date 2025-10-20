<?php

namespace App\Http\Controllers;

use App\Models\ChatHead;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Image;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockSubCategory;
use App\Models\SubscriptionTransaction;
use App\Models\TrendingNotification;
use App\Models\User;
use App\Models\Utils;
use App\Services\SubscriptionPesapalService;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
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

        foreach ($chat_heads as $head) {
            try {
                // Determine the other participant
                $their_id = null;
                $is_customer = ($me->id == $head->customer_id);

                if ($is_customer) {
                    $their_id = $head->product_owner_id;
                } else {
                    $their_id = $head->customer_id;
                }

                // Get the other user
                $them = User::find($their_id);
                if ($them == null) {
                    // Skip if other user doesn't exist
                    continue;
                }

                // Get the last message for this chat head
                $lastMesg = ChatMessage::where('chat_head_id', $head->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Calculate unread message counts for the current user
                $my_unread_count = ChatMessage::where('chat_head_id', $head->id)
                    ->where('receiver_id', $me->id)
                    ->where('status', '!=', 'read')
                    ->count();

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
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }

        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }

        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
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
        $messages = ChatMessage::where([
            'chat_head_id' => $chat_head->id,
            'receiver_id' => $receiver->id,
        ])->get();
        foreach ($messages as $key => $message) {
            $message->status = 'read';
            $message->save();
        }
        return $this->success(null, 'Makerd as read for chat head: ' . $chat_head->id . ' and receiver: ' . $receiver->id);
    }

    public function chat_send(Request $r)
    {

        $u = Utils::get_user($r);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
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

        $chat_message = new ChatMessage();
        $chat_message->chat_head_id = $chat_head->id;
        $chat_message->sender_id = $sender->id;
        $chat_message->receiver_id = $receiver->id;
        $chat_message->sender_name = $sender->name;
        $chat_message->sender_photo = $sender->photo;
        $chat_message->receiver_name = $receiver->name;
        $chat_message->receiver_photo = $receiver->photo;
        $chat_message->body = $r->body;
        $chat_message->type = 'text';
        $chat_message->status = 'sent';
        $chat_message->save();
        $chat_head->last_message_body = $r->body;
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
        $u = Utils::get_user($r);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $app_type = Utils::get_app_type($r);
                $app_types = ['ugflix', 'lugaflix'];
                if (!in_array($app_type, $app_types)) {
                    $u->app_type = 'ugflix';
                }
                $platform = Utils::get_platform_from_request($r);
                if ($platform != null) {
                    $u->platform = $platform;
                }
                $u->last_online_at = now();
                $u->save();
            }
        }
        if ($u == null) {
            return $this->error('User not found.');
        }

        $u = User::find($u->id);

        $pendingPayments = SubscriptionTransaction::whereNotIn('status', ['Completed'])
            ->where('created_at', '>=', Carbon::now()->subHours(24 * 3)) // only check last 72 hours
            ->where('user_id', $u->id)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
        //set time limit
        set_time_limit(900); // 15 minutes 
        // $pendingPayments = SubscriptionTransaction::where('id', 82)->get();
        foreach ($pendingPayments as $key => $pay) {
            if ($pay->status == 'Completed') {
                continue;
            }
            $number_of_times_checked = (int) $pay->number_of_times_checked;
            if ($number_of_times_checked > 20) {
                //mark as failed
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
        // $u->autoAssignFreeTrial();

        $APP_VERSION = 19;
        $UPDATE_NOTES = "- Fixed download disappearance bug
        - Added subscription management with free trial
        - Google Sign-In now available
        - Dashboard statistics for watchlist and favorites
        - Improved movie recommendations
        - Fixed profile photo upload issues";
        $WHATSAPP_CONTAT_NUMBER = "+256783204665";
        $take_only = ['id', 'title', 'url', 'thumbnail_url', 'description',   'genre', 'type', 'vj', 'is_premium', 'category_id', 'category'];
        $date = Carbon::parse('2020-01-01 00:00:00');


        // 12 hours ago
        $min_time = Carbon::now()->subHours(12);
        //maxk time now
        $max_time = Carbon::now();

        //setting movies for today listing
        $temp_movies = MovieModel::where([
            'status' => 'Active',
            'type' => 'Movie',
        ])
            ->whereNotNull('last_listing_date')
            ->where('is_muno', 'Yes')
            ->whereBetween('last_listing_date', [$min_time, $max_time])
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get($take_only);
        //if temp_movies is less than 200, set last_listing_date to null for all movies
        if (count($temp_movies) < 200) {
            $latest_movies_with_listing_date_as_null = MovieModel::where([
                'status' => 'Active',
                'type' => 'Movie',
            ])
                ->whereNull('last_listing_date')
                ->where('is_muno', 'Yes')
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get();
            //check if latest_movies_with_listing_date_as_null is less than 200
            if (count($latest_movies_with_listing_date_as_null) < 200) {
                //get latest 2000 movies
                $latest_movies_with_listing_date_as_null_ids = $latest_movies_with_listing_date_as_null->pluck('id')->toArray();
                $latest_random_movies = MovieModel::where([
                    'status' => 'Active',
                    'type' => 'Movie',
                ])
                    ->whereNotIn('id', $latest_movies_with_listing_date_as_null_ids)
                    ->orderBy('created_at', 'desc')
                    ->limit(200 - count($latest_movies_with_listing_date_as_null))
                    ->get();
                $latest_movies_with_listing_date_as_null = $latest_movies_with_listing_date_as_null->merge($latest_random_movies);
                //set $latest_movies_with_listing_date_as_null as today's listing (use sql)
                $latest_movies_with_listing_date_as_null_ids = $latest_movies_with_listing_date_as_null->pluck('id')->toArray();
                $six_am_today = Carbon::now()->startOfDay()->addHours(6);
                DB::table('movie_models')
                    ->whereIn('id', $latest_movies_with_listing_date_as_null_ids)
                    ->update(['last_listing_date' => $six_am_today]);
            }
        }

        //movies with last_listing_date is between 12 hours ago and now
        $oldest_listed_movies = MovieModel::where([
            'status' => 'Active',
            'type' => 'Movie',
        ])
            ->whereNotNull('last_listing_date')
            ->where('is_muno', 'Yes')
            ->whereBetween('last_listing_date', [$min_time, $max_time])
            ->orderBy('last_listing_date', 'desc')
            ->limit(200)
            ->get($take_only);


        //if less than 200, get the rest of the movies
        if (count($oldest_listed_movies) < 200) {
            $oldest_listed_movies = MovieModel::where([
                'status' => 'Active',
                'type' => 'Movie',
            ])
                ->where('is_muno', 'Yes')
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get($take_only);
        }

        // Early return if no movies are available
        if ($oldest_listed_movies->count() === 0) {
            $oldest_listed_movies = MovieModel::where([
                'status' => 'Active',
                'type' => 'Movie',
            ])
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get($take_only);
        }


        $now = Carbon::now();
        $today = $now->format('d');
        $topMovie = null;

        // Safely get top movie with proper null checks

        try {
            $trending =  TrendingNotification::getTendingMovie();
            if ($trending != null) {
                $topMovie = $trending;
            }
        } catch (\Throwable $th) {
        }



        $lists = [];
        $movies = $oldest_listed_movies;
        $my_view_ids = [];

        // Safely get user's viewed movies
        if ($u && $u->id) {
            $my_view_ids = MovieView::where('user_id', $u->id)
                ->pluck('movie_model_id')
                ->toArray();
        }

        //add latest movies list
        $my_list = [];
        $my_list['title'] = "Latest Movies";
        $my_list['movies'] = MovieModel::where('status', 'Active')
            ->where('type', 'Movie')
            ->where('is_muno', 'Yes')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get($take_only);
        $lists[] = $my_list;



        //watched_movies continue watching
        $watched_movies = collect();
        if ($u && $u->id) {
            $watched_movies = MovieView::where('user_id', $u->id)
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();
        }

        $my_list = [];
        $my_list['title'] = "Continue Watching";

        if ($watched_movies->count() > 0) {
            $my_list['movies'] = $watched_movies->take(50)->map(function ($view) {
                return MovieModel::find($view->movie_model_id);
            })->filter(function ($movie) {
                return $movie != null;
            })->values();
        } else {
            $my_list['movies'] = [];
        }

        $lists[] = $my_list;

        //top movies
        if (count($movies) > 10) {

            //top movies 
            //movies with most views_time_count but not in my_view_ids
            $top_movies = MovieModel::whereNotIn('id', $my_view_ids)
                ->where('status', 'Active')
                ->where('type', 'Movie')
                ->where('is_muno', 'Yes')
                ->orderBy('views_time_count', 'desc')
                ->limit(20)
                ->get($take_only);

            //shuffle $top_movies
            // $top_movies = $top_movies->shuffle(); 

            if ($top_movies->count() > 0) {
                $my_list = [];
                $my_list['title'] = "Top Movies";
                $my_list['movies'] = $top_movies;
                $lists[] = $my_list;
            }
        }



        //trending movies
        if (count($movies) > 20) {

            $note_include_ids = [];
            //get trending movies that are not in my_view_ids
            if (is_array($my_view_ids) || is_object($my_view_ids)) {
                foreach ($my_view_ids as $id) {
                    $note_include_ids[] = $id;
                }
            }

            //add already added movies add to note_include_ids
            foreach ($lists as $key => $list) {
                if (!isset($list['movies']) || count($list['movies']) < 1) {
                    continue;
                }
                foreach ($list['movies'] as $key2 => $movie) {
                    if ($movie && isset($movie->id)) {
                        $note_include_ids[] = $movie->id;
                    }
                }
            }


            //trending movies
            $trending_movies = MovieModel::whereNotIn('id', $note_include_ids)
                ->where('status', 'Active')
                ->where('type', 'Movie')
                ->where('is_muno', 'Yes')
                ->orderBy('downloads_count', 'desc')
                ->limit(30)
                ->get($take_only);

            //shuffle $trending_movies
            $trending_movies = $trending_movies->shuffle();


            //if trending movies is empty, return empty list
            if ($trending_movies->count() < 1) {
                //get top 10 of that platform
                $trending_movies = MovieModel::where('status', 'Active')
                    ->where('type', 'Movie')
                    ->where('is_muno', 'Yes')
                    ->orderBy('downloads_count', 'desc')
                    ->limit(10)
                    ->get($take_only);
            }

            if ($trending_movies->count() > 0) {
                $my_list = [];
                $my_list['title'] = "Trending Movies";
                $my_list['movies'] = $trending_movies;
                $lists[] = $my_list;
            }
        }


        //for you movies

        if (count($movies) > 10) {
            $my_list['title'] = "For You";
            $my_list['movies'] = $movies->skip(10)->take(10);
            $lists[] = $my_list;
        }


        //continue watching
        if (count($movies) > 30) {
            $my_list['title'] = "Continue Watching";
            $my_list['movies'] = $movies->skip(20)->take(10);
            $lists[] = $my_list;
        }
        //latest movies
        if (count($movies) > 40) {
            $my_list['title'] = "Latest Movies";
            $my_list['movies'] = $movies->skip(30)->take(10);
            $lists[] = $my_list;
        }


        //drama movies
        if (count($movies) > 60) {
            $my_list['title'] = "Drama Movies";
            $my_list['movies'] = $movies->skip(40)->take(10);
            $lists[] = $my_list;
        }
        //action movies
        if (count($movies) > 70) {
            $my_list['title'] = "Action Movies";
            $my_list['movies'] = $movies->skip(70)->take(10);
            $lists[] = $my_list;
        }

        //comedy movies
        if (count($movies) > 80) {
            $my_list['title'] = "Comedy Movies";
            $my_list['movies'] = $movies->skip(80)->take(10);
            $lists[] = $my_list;
        }


        //latest movies
        if (count($movies) > 210) {
            $my_list['title'] = "Latest Movies";
            $my_list['movies'] = $movies->skip(210)->take(10);
            $lists[] = $my_list;
        }


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
            // If genre processing fails, continue with empty array
            $unique_genres = [];
        }

        $unique_vj = [];
        try {
            $sql = "SELECT DISTINCT vj FROM movie_models WHERE vj IS NOT NULL AND vj != ''";
            $vjs = DB::select($sql);
            foreach ($vjs as $key => $vj) {
                if (isset($vj->vj) && !empty($vj->vj)) {
                    $slilts = explode(",", $vj->vj);
                    foreach ($slilts as $key => $slit) {
                        $slit = trim($slit);
                        //remove vj from vj
                        $slit = str_replace(["vj", "VJ", "Vj"], "", $slit);
                        $slit = str_replace([" ", "-"], "", $slit);
                        if (strlen($slit) > 0 && !in_array($slit, $unique_vj)) {
                            $unique_vj[] = $slit;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // If VJ processing fails, continue with empty array
            $unique_vj = [];
        }

        $iosMovies = MovieModel::where(['platform_type' => 'ios'])->get();

        $platform_type = Utils::get_platform();
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
        // Get subscription information for authenticated user
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
                    \Log::info('✅ FIXED: Corrected has_active_subscription to true');
                }

                $subscription_info = [
                    'has_active_subscription' => $has_active,
                    'days_remaining' => $days_remaining,
                    'hours_remaining' => $subscription_status['hours_remaining'] ?? 0,
                    'is_in_grace_period' => $subscription_status['is_in_grace_period'] ?? false,
                    'subscription_status' => $status,
                    'end_date' => $subscription_status['end_date'] ?? null,
                    'require_subscription' => !$has_active,
                ];

                \Log::info('✅ Manifest: Subscription info built successfully', [
                    'user_id' => $u->id,
                    'final_data' => $subscription_info,
                ]);
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
                // Count watchlist items
                $dashboard_stats['watchlist_count'] = \App\Models\MovieWishlist::where('user_id', $u->id)
                    ->count();

                // Count watch history
                $dashboard_stats['watch_history_count'] = \App\Models\MovieView::where('user_id', $u->id)
                    ->count();

                // Count liked movies
                $dashboard_stats['liked_movies_count'] = \App\Models\MovieLike::where('user_id', $u->id)
                    ->count();

                // Count user's products
                $dashboard_stats['products_count'] = \App\Models\Product::where('user_id', $u->id)
                    ->count();

                // Count active chats (messages sent by user)
                $sent_count = \App\Models\ChatMessage::where('sender_id', $u->id)
                    ->distinct('receiver_id')
                    ->count('receiver_id');

                // Count active chats (messages received by user)
                $received_count = \App\Models\ChatMessage::where('receiver_id', $u->id)
                    ->distinct('sender_id')
                    ->count('sender_id');

                $dashboard_stats['active_chats_count'] = $sent_count + $received_count;

                // Orders count - set to 0 for now (Order model doesn't exist yet)
                $dashboard_stats['total_orders_count'] = 0;
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
            'APP_VERSION' => $APP_VERSION ?? 19,
            'lists' => $lists ?? [],
            'UPDATE_NOTES' => $UPDATE_NOTES ?? '',
            'WHATSAPP_CONTAT_NUMBER' => $WHATSAPP_CONTAT_NUMBER ?? '',
            'subscription' => $subscription_info, // Add subscription information
            'dashboard_stats' => $dashboard_stats, // Add dashboard statistics
            'client_app_version' => Utils::get_app_version($r), // Return client's app version for debugging
        ];

        return Utils::success($manifest, "Listed successfully.");
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

        $data = $model::where([])->limit(1000000)->get();
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
            $data = [];
            $temp_data = $model::where([])->limit(1000000)->get();
            foreach ($temp_data as $key => $movie) {
                $view = DB::table('movie_views')->where([
                    'movie_model_id' => $movie->id,
                    'user_id' => $u->id,
                ])->first();
                if ($view != null) {
                    $movie->watched_movie = 'Yes';
                    $movie->watch_progress = $view->progress;
                    $movie->watch_status = '';
                }


                $liked = DB::table('movie_likes')->where('movie_model_id', $movie->id)->where('user_id', $u->id)
                    ->where('status', 'Active')->first();
                if ($liked != null) {
                    $movie->liked_movie = 'Yes';
                } else {
                    $movie->liked_movie = 'No';
                }
                $data[] = $movie;
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
        $movie = MovieModel::find($r->get('movie_id'));
        if ($movie == null) {
            Utils::error("Movie not found.");
        }

        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }


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
            \Log::info('Photo file received', [
                'temp_file_field' => $r->temp_file_field,
                'file_name' => $r->file('photo')->getClientOriginalName(),
                'file_size' => $r->file('photo')->getSize(),
                'mime_type' => $r->file('photo')->getMimeType()
            ]);
        } else {
            \Log::info('No photo file in request', [
                'temp_file_field' => $r->temp_file_field,
                'has_files' => $r->hasFile('photo')
            ]);
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
            Utils::error("Account not found.");
        }


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
     * Google OAuth Authentication
     * Verifies Google ID token and returns JWT token
     */
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
                    \Illuminate\Support\Facades\Log::info('New user created via Google OAuth', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'google_id' => $user->google_id
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to create Google OAuth user', [
                        'error' => $e->getMessage(),
                        'email' => $google_user['email']
                    ]);
                    return $this->error('Failed to create user account: ' . $e->getMessage());
                }
            } else {
                // User exists - update Google info and log them in
                \Illuminate\Support\Facades\Log::info('Existing user logging in via Google OAuth', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'had_google_id' => !empty($user->google_id)
                ]);

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

            // Generate JWT token
            try {
                // Try to set TTL for long-lasting token (5 years)
                $token = auth('api')->attempt(['email' => $user->email], true);
                if (!$token) {
                    // If attempt fails, try direct login
                    $token = auth('api')->login($user);
                }
            } catch (\Exception $e) {
                $token = auth('api')->login($user);
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

            // Optional: Verify audience (aud) matches your Google Client ID
            // if ($token_data['aud'] !== 'YOUR_GOOGLE_CLIENT_ID') {
            //     return false;
            // }

            return $token_data;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google token verification error: ' . $e->getMessage());
            return false;
        }
    }


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

        Utils::success([
            'user' => $registered_user,
            'company' => Company::find(1),
        ], "Registration successful.");
    }



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


    public function request_password_reset_code(Request $r)
    {



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
        $code = rand(100000, 999999);
        $u->secret_code = $code;
        $u->save();

        $mail_body = <<<EOD
            <p>Dear {$u->name},</p>
            <p>Your password reset code is <b><code>$code</code></b></p>
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
        } catch (\Throwable $th) {
            return Utils::error($th->getMessage());
        }
        $u = User::find($u->id);
        Utils::success([
            'user' => $u,
        ], "Code sent successfully.");
    }
}
