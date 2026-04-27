<?php

namespace App\OpenApi;

/**
 * @OA\Get(
 *   path="/api/account/dashboard",
 *   operationId="autoGetapiaccountdashboard",
 *   tags={"Account"},
 *   summary="Get user account dashboard",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_account_dashboard",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/UserResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/account/likes",
 *   operationId="autoGetapiaccountlikes",
 *   tags={"Account"},
 *   summary="List movies liked by the user",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_liked_movies",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/account/likes/toggle",
 *   operationId="autoPostapiaccountlikestoggle",
 *   tags={"Account"},
 *   summary="Toggle like on a movie",
 *   description="Adds or removes a like for the specified movie for the authenticated user.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/LikeToggleRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/account/watch-history",
 *   operationId="autoGetapiaccountwatchhistory",
 *   tags={"Account"},
 *   summary="Get user watch history",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_watch_history",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/VideoProgressResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/account/watchlist",
 *   operationId="autoGetapiaccountwatchlist",
 *   tags={"Account"},
 *   summary="List user watchlist",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_watchlist",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/account/watchlist/add",
 *   operationId="autoPostapiaccountwatchlistadd",
 *   tags={"Account"},
 *   summary="Add a movie to the watchlist",
 *   description="Adds the specified movie to the authenticated user\'s watchlist.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/WatchlistAddRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Delete(
 *   path="/api/account/watchlist/{movie_id}",
 *   operationId="autoDeleteapiaccountwatchlistmovieid",
 *   tags={"Account"},
 *   summary="Remove a movie from the watchlist",
 *   description="Removes the specified movie from the authenticated user\'s watchlist.",
 *   @OA\Parameter(name="movie_id", in="path", required=true, description="Movie id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/account/wishlist",
 *   operationId="autoGetapiaccountwishlist",
 *   tags={"Account"},
 *   summary="List user wishlist",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_wishlisted_movies",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/account/wishlist/toggle",
 *   operationId="autoPostapiaccountwishlisttoggle",
 *   tags={"Account"},
 *   summary="Toggle movie wishlist",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@toggle_movie_wishlist",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/api/{model}",
 *   operationId="autoPostapiapimodel",
 *   tags={"Diagnostics/Test"},
 *   summary="My update",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@my_update",
 *   @OA\Parameter(name="model", in="path", required=true, description="Model", @OA\Schema(type="string", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/api/{model}",
 *   operationId="autoGetapiapimodel",
 *   tags={"Diagnostics/Test"},
 *   summary="My list",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@my_list",
 *   @OA\Parameter(name="model", in="path", required=true, description="Model", @OA\Schema(type="string", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/chat-delete",
 *   operationId="autoPostapichatdelete",
 *   tags={"Account"},
 *   summary="Chat delete",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_delete",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/chat-heads",
 *   operationId="autoGetapichatheads",
 *   tags={"Account"},
 *   summary="Chat heads",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_heads",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/chat-mark-as-read",
 *   operationId="autoPostapichatmarkasread",
 *   tags={"Account"},
 *   summary="Chat mark as read",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_mark_as_read",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/chat-messages",
 *   operationId="autoGetapichatmessages",
 *   tags={"Account"},
 *   summary="List chat messages",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_messages",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/chat-send",
 *   operationId="autoPostapichatsend",
 *   tags={"Account"},
 *   summary="Chat send",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_send",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/chat-start",
 *   operationId="autoPostapichatstart",
 *   tags={"Account"},
 *   summary="Chat start",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@chat_start",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/checkers/{any?}",
 *   operationId="autoGetapicheckersany",
 *   tags={"Diagnostics/Test"},
 *   summary="Get any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/checkers/{any?}",
 *   operationId="autoPostapicheckersany",
 *   tags={"Diagnostics/Test"},
 *   summary="Post any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/coins/{any?}",
 *   operationId="autoGetapicoinsany",
 *   tags={"Diagnostics/Test"},
 *   summary="Get any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/coins/{any?}",
 *   operationId="autoPostapicoinsany",
 *   tags={"Diagnostics/Test"},
 *   summary="Post any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/consultation-card-payment",
 *   operationId="autoPostapiconsultationcardpayment",
 *   tags={"Diagnostics/Test"},
 *   summary="Consultation card payment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@consultation_card_payment",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/debug-chat/{id}",
 *   operationId="autoGetapidebugchatid",
 *   tags={"Diagnostics/Test"},
 *   summary="Debug chat",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@debug_chat",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/disable-account",
 *   operationId="autoPostapidisableaccount",
 *   tags={"Diagnostics/Test"},
 *   summary="Disable account",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@disable_account",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/dynamic-delete",
 *   operationId="autoPostapidynamicdelete",
 *   tags={"Diagnostics/Test"},
 *   summary="Delete",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@delete",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/dynamic-list",
 *   operationId="autoGetapidynamiclist",
 *   tags={"Diagnostics/Test"},
 *   summary="Index",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/dynamic-save",
 *   operationId="autoPostapidynamicsave",
 *   tags={"Diagnostics/Test"},
 *   summary="Save",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@save",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/file-uploading",
 *   operationId="autoPostapifileuploading",
 *   tags={"Diagnostics/Test"},
 *   summary="File uploading",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@file_uploading",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/fix-series-type-single",
 *   operationId="autoPostapifixseriestypesingle",
 *   tags={"Diagnostics/Test"},
 *   summary="Post fix series type single",
 *   description="Controller: Closure",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/game/{any?}",
 *   operationId="autoGetapigameany",
 *   tags={"Diagnostics/Test"},
 *   summary="Get any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/game/{any?}",
 *   operationId="autoPostapigameany",
 *   tags={"Diagnostics/Test"},
 *   summary="Post any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/ludo/{any?}",
 *   operationId="autoGetapiludoany",
 *   tags={"Diagnostics/Test"},
 *   summary="Get any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/ludo/{any?}",
 *   operationId="autoPostapiludoany",
 *   tags={"Diagnostics/Test"},
 *   summary="Post any?",
 *   description="Controller: Closure",
 *   @OA\Parameter(name="any", in="path", required=false, description="Any", @OA\Schema(type="string", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/manifest",
 *   operationId="autoGetapimanifest",
 *   tags={"Diagnostics/Test"},
 *   summary="Manifest",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@manifest",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/me",
 *   operationId="autoGetapime",
 *   tags={"Diagnostics/Test"},
 *   summary="Me",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@me",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/block-user",
 *   operationId="autoPostapimoderationblockuser",
 *   tags={"Moderation"},
 *   summary="Block another user",
 *   description="Blocks another user to prevent them from interacting with the current user.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BlockUserRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/moderation/blocked-users",
 *   operationId="autoGetapimoderationblockedusers",
 *   tags={"Moderation"},
 *   summary="List users blocked by the current user",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@getBlockedUsers",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/moderation/dashboard",
 *   operationId="autoGetapimoderationdashboard",
 *   tags={"Moderation"},
 *   summary="Get moderation admin dashboard data",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@getModerationDashboard",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/filter-content",
 *   operationId="autoPostapimoderationfiltercontent",
 *   tags={"Moderation"},
 *   summary="Filter content for policy violations",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@filterContent",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ReportContentRequest")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/legal-consent",
 *   operationId="autoPostapimoderationlegalconsent",
 *   tags={"Moderation"},
 *   summary="Submit legal consent",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@updateLegalConsent",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/LegalConsentRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/moderation/legal-consent-status",
 *   operationId="autoGetapimoderationlegalconsentstatus",
 *   tags={"Moderation"},
 *   summary="Get current legal consent status",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@getLegalConsentStatus",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/moderation/my-reports",
 *   operationId="autoGetapimoderationmyreports",
 *   tags={"Moderation"},
 *   summary="List content reports filed by the current user",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@getUserReports",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/report",
 *   operationId="autoPostapimoderationreport",
 *   tags={"Moderation"},
 *   summary="Report content for moderation",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@reportContent",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ReportContentRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/report-content",
 *   operationId="autoPostapimoderationreportcontent",
 *   tags={"Moderation"},
 *   summary="Report content for moderation review",
 *   description="Submits a content report for moderator review.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ReportContentRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/stock-item",
 *   operationId="autoPostapimoderationstockitem",
 *   tags={"Moderation"},
 *   summary="Moderate stock item",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@moderateStockItem",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/stock-sub-category",
 *   operationId="autoPostapimoderationstocksubcategory",
 *   tags={"Moderation"},
 *   summary="Moderate stock sub category",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@moderateStockSubCategory",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/unblock-user",
 *   operationId="autoPostapimoderationunblockuser",
 *   tags={"Moderation"},
 *   summary="Unblock a previously blocked user",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@unblockUser",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UnblockUserRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/moderation/update-legal-consent",
 *   operationId="autoPostapimoderationupdatelegalconsent",
 *   tags={"Moderation"},
 *   summary="Update legal consent preferences",
 *   description="Updates the authenticated user\'s consent preferences for terms, privacy policy, and marketing.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/LegalConsentRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/moderation/user-reports",
 *   operationId="autoGetapimoderationuserreports",
 *   tags={"Moderation"},
 *   summary="List all user-filed reports (admin)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ModerationController@getUserReports",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/movie/{id}",
 *   operationId="autoGetapimovieid",
 *   tags={"Movies"},
 *   summary="Get movie details (alias)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@movie",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/movies",
 *   operationId="autoGetapimovies",
 *   tags={"Movies"},
 *   summary="List all movies",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@movies",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/movies/{id}",
 *   operationId="autoGetapimoviesid",
 *   tags={"Movies"},
 *   summary="Get movie details by ID",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@movie",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/post-media-upload",
 *   operationId="autoPostapipostmediaupload",
 *   tags={"Diagnostics/Test"},
 *   summary="Upload media",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@upload_media",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/product-create",
 *   operationId="autoPostapiproductcreate",
 *   tags={"Diagnostics/Test"},
 *   summary="Product create",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@product_create",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/products-1",
 *   operationId="autoGetapiproducts1",
 *   tags={"Diagnostics/Test"},
 *   summary="Products 1",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@products_1",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/products-delete",
 *   operationId="autoPostapiproductsdelete",
 *   tags={"Diagnostics/Test"},
 *   summary="Products delete",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@products_delete",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/random-movie",
 *   operationId="autoGetapirandommovie",
 *   tags={"Movies"},
 *   summary="Get a random movie",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@random_movie",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/run-migration",
 *   operationId="autoPostapirunmigration",
 *   tags={"Diagnostics/Test"},
 *   summary="[Internal] Run pending database migrations",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\MigrationController@runMigration",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/save-view-progress",
 *   operationId="autoPostapisaveviewprogress",
 *   tags={"Diagnostics/Test"},
 *   summary="Save view progress",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiController@save_view_progress",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/stock-items",
 *   operationId="autoGetapistockitems",
 *   tags={"Diagnostics/Test"},
 *   summary="Get stock items",
 *   description="Controller: Closure",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/stock-sub-categories",
 *   operationId="autoGetapistocksubcategories",
 *   tags={"Diagnostics/Test"},
 *   summary="Get stock sub categories",
 *   description="Controller: Closure",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscription-plans",
 *   operationId="autoGetapisubscriptionplans",
 *   tags={"Subscription"},
 *   summary="List all available subscription plans",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@listPlans",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/SubscriptionPlanResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/check-status",
 *   operationId="autoPostapisubscriptionscheckstatus",
 *   tags={"Subscription"},
 *   summary="Check status",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@checkStatus",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/create",
 *   operationId="autoPostapisubscriptionscreate",
 *   tags={"Subscription"},
 *   summary="Create",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@create",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/default-gateway",
 *   operationId="autoGetapisubscriptionsdefaultgateway",
 *   tags={"Subscription"},
 *   summary="Get default gateway",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@getDefaultGateway",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/default-gateway",
 *   operationId="autoPostapisubscriptionsdefaultgateway",
 *   tags={"Subscription"},
 *   summary="Set default gateway",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@setDefaultGateway",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/flutterwave/callback",
 *   operationId="autoGetapisubscriptionsflutterwavecallback",
 *   tags={"Subscription"},
 *   summary="Flutterwave callback",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@flutterwaveCallback",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/flutterwave/webhook",
 *   operationId="autoPostapisubscriptionsflutterwavewebhook",
 *   tags={"Subscription"},
 *   summary="Receive Flutterwave payment webhook",
 *   description="Webhook endpoint called by Flutterwave after a payment event. Must be publicly accessible.",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/history",
 *   operationId="autoGetapisubscriptionshistory",
 *   tags={"Subscription"},
 *   summary="Get subscription payment history",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@history",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/SubscriptionResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/my-subscription",
 *   operationId="autoGetapisubscriptionsmysubscription",
 *   tags={"Subscription"},
 *   summary="My subscription",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@mySubscription",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/payment-gateways",
 *   operationId="autoGetapisubscriptionspaymentgateways",
 *   tags={"Subscription"},
 *   summary="Payment gateways",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@paymentGateways",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/payment-status/{trackingId}",
 *   operationId="autoGetapisubscriptionspaymentstatustrackingId",
 *   tags={"Subscription"},
 *   summary="Check payment status by tracking ID",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@getPaymentStatus",
 *   @OA\Parameter(name="trackingId", in="path", required=true, description="TrackingId", @OA\Schema(type="string", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/pending",
 *   operationId="autoGetapisubscriptionspending",
 *   tags={"Subscription"},
 *   summary="Get pending",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@getPending",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/pesapal/callback",
 *   operationId="autoGetapisubscriptionspesapalcallback",
 *   tags={"Subscription"},
 *   summary="Pesapal callback",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@pesapalCallback",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/pesapal/callback",
 *   operationId="autoPostapisubscriptionspesapalcallback",
 *   tags={"Subscription"},
 *   summary="Pesapal callback",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@pesapalCallback",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/pesapal/ipn",
 *   operationId="autoPostapisubscriptionspesapalipn",
 *   tags={"Subscription"},
 *   summary="Pesapal ipn",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@pesapalIpn",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/subscriptions/pesapal/test",
 *   operationId="autoGetapisubscriptionspesapaltest",
 *   tags={"Subscription"},
 *   summary="Pesapal test",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@pesapalTest",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/retry-payment",
 *   operationId="autoPostapisubscriptionsretrypayment",
 *   tags={"Subscription"},
 *   summary="Retry payment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@retryPayment",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/{id}/action",
 *   operationId="autoPostapisubscriptionsidaction",
 *   tags={"Subscription"},
 *   summary="Admin action",
 *   description="Controller: App\\\\Admin\\\\Controllers\\\\SubscriptionController@adminAction",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/{id}/cancel",
 *   operationId="autoPostapisubscriptionsidcancel",
 *   tags={"Subscription"},
 *   summary="Cancel pending",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@cancelPending",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/{id}/check-payment",
 *   operationId="autoPostapisubscriptionsidcheckpayment",
 *   tags={"Subscription"},
 *   summary="Check pending payment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\SubscriptionApiController@checkPendingPayment",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/subscriptions/{id}/initiate-payment",
 *   operationId="autoPostapisubscriptionsidinitiatepayment",
 *   tags={"Subscription"},
 *   summary="Initiate a new subscription payment",
 *   description="Initiates a new subscription payment. Returns a payment URL or reference to complete checkout.",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/InitPaymentRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/test-auto-assignment/{user_id?}",
 *   operationId="autoGetapitestautoassignmentuserid",
 *   tags={"Diagnostics/Test"},
 *   summary="Test auto assignment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\FreeTrialTestController@testAutoAssignment",
 *   @OA\Parameter(name="user_id", in="path", required=false, description="User id", @OA\Schema(type="string", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Delete(
 *   path="/api/test-free-trial-cleanup/{user_id?}",
 *   operationId="autoDeleteapitestfreetrialcleanupuserid",
 *   tags={"Diagnostics/Test"},
 *   summary="Cleanup test data",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\FreeTrialTestController@cleanupTestData",
 *   @OA\Parameter(name="user_id", in="path", required=false, description="User id", @OA\Schema(type="string", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/test-free-trial-plan",
 *   operationId="autoGetapitestfreetrialplan",
 *   tags={"Diagnostics/Test"},
 *   summary="Get free trial plan",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\FreeTrialTestController@getFreeTrialPlan",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/test-free-trial-stats",
 *   operationId="autoGetapitestfreetrialstats",
 *   tags={"Diagnostics/Test"},
 *   summary="Get free trial stats",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\FreeTrialTestController@getFreeTrialStats",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/test-free-trial/{user_id?}",
 *   operationId="autoGetapitestfreetrialuserid",
 *   tags={"Diagnostics/Test"},
 *   summary="Test free trial",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\FreeTrialTestController@testFreeTrial",
 *   @OA\Parameter(name="user_id", in="path", required=false, description="User id", @OA\Schema(type="string", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/track-event",
 *   operationId="autoPostapitrackevent",
 *   tags={"Diagnostics/Test"},
 *   summary="Event",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\PageVisitController@event",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/track-visit",
 *   operationId="autoPostapitrackvisit",
 *   tags={"Diagnostics/Test"},
 *   summary="Store",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\PageVisitController@store",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/user",
 *   operationId="autoGetapiuser",
 *   tags={"Diagnostics/Test"},
 *   summary="Get user",
 *   description="Controller: Closure",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/users",
 *   operationId="autoGetapiusers",
 *   tags={"Diagnostics/Test"},
 *   summary="Get users",
 *   description="Controller: Closure",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/users-list",
 *   operationId="autoGetapiuserslist",
 *   tags={"Diagnostics/Test"},
 *   summary="Users list",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@users_list",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/blog",
 *   operationId="autoGetapiv2blog",
 *   tags={"V2 Blog"},
 *   summary="List blog posts (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/blog/comment/{id}/like",
 *   operationId="autoPostapiv2blogcommentidlike",
 *   tags={"V2 Blog"},
 *   summary="Toggle comment like",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@toggleCommentLike",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/blog/comment/{id}/report",
 *   operationId="autoPostapiv2blogcommentidreport",
 *   tags={"V2 Blog"},
 *   summary="Report comment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@reportComment",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/blog/marquee",
 *   operationId="autoGetapiv2blogmarquee",
 *   tags={"V2 Blog"},
 *   summary="Marquee",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@marquee",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/blog/{id}",
 *   operationId="autoGetapiv2blogid",
 *   tags={"V2 Blog"},
 *   summary="Get blog post details (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@show",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/blog/{id}/comment",
 *   operationId="autoPostapiv2blogidcomment",
 *   tags={"V2 Blog"},
 *   summary="Add comment",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@addComment",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/blog/{id}/like",
 *   operationId="autoPostapiv2blogidlike",
 *   tags={"V2 Blog"},
 *   summary="Toggle like",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\BlogController@toggleLike",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/downloads/record",
 *   operationId="autoPostapiv2downloadsrecord",
 *   tags={"V2 Downloads"},
 *   summary="Record a movie download (v2)",
 *   description="Records that the user has downloaded a movie, tracking download type.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DownloadRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/downloads/stats",
 *   operationId="autoGetapiv2downloadsstats",
 *   tags={"V2 Downloads"},
 *   summary="Get download statistics (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\DownloadController@stats",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/game-stats",
 *   operationId="autoGetapiv2gamestats",
 *   tags={"V2 Game Stats"},
 *   summary="Get game statistics leaderboard (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\GameStatsController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/game-stats/sync",
 *   operationId="autoPostapiv2gamestatssync",
 *   tags={"V2 Game Stats"},
 *   summary="Sync bulk game statistics (v2)",
 *   description="Submits an array of game statistics (up to 10 game types) in a single request.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/GameStatsBulkRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/manifest",
 *   operationId="autoGetapiv2manifest",
 *   tags={"V2 Manifest"},
 *   summary="Get app content manifest (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\ManifestController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/movies",
 *   operationId="autoGetapiv2movies",
 *   tags={"V2 Movies"},
 *   summary="List movies (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/movies/search",
 *   operationId="autoGetapiv2moviessearch",
 *   tags={"V2 Movies"},
 *   summary="Search",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@search",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/movies/{id}",
 *   operationId="autoGetapiv2moviesid",
 *   tags={"V2 Movies"},
 *   summary="Get movie details (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@show",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/movies/{id}/fix",
 *   operationId="autoPostapiv2moviesidfix",
 *   tags={"V2 Movies"},
 *   summary="Fix",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@fix",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/movies/{id}/playback",
 *   operationId="autoPostapiv2moviesidplayback",
 *   tags={"V2 Movies"},
 *   summary="Playback",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@playback",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/movies/{id}/related",
 *   operationId="autoGetapiv2moviesidrelated",
 *   tags={"V2 Movies"},
 *   summary="Related",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@related",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/safemode/history",
 *   operationId="autoGetapiv2safemodehistory",
 *   tags={"V2 SafeMode"},
 *   summary="Get safemode view history",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SafeModeAnalyticsController@history",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/safemode/progress",
 *   operationId="autoPostapiv2safemodeprogress",
 *   tags={"V2 SafeMode"},
 *   summary="Save safemode video progress",
 *   description="Saves the playback progress for a safemode video.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SafeModeProgressRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/safemode/progress/{external_video_id}",
 *   operationId="autoGetapiv2safemodeprogressexternalvideoid",
 *   tags={"V2 SafeMode"},
 *   summary="Get saved progress for a safemode video",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SafeModeAnalyticsController@getProgress",
 *   @OA\Parameter(name="external_video_id", in="path", required=true, description="External video id", @OA\Schema(type="string", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/safemode/track",
 *   operationId="autoPostapiv2safemodetrack",
 *   tags={"V2 SafeMode"},
 *   summary="Track a safemode video interaction",
 *   description="Records a user interaction (view, play, like, or add to list) for a safemode video.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SafeModeTrackRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/all",
 *   operationId="autoGetapiv2searchall",
 *   tags={"V2 Search"},
 *   summary="Search all",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@searchAll",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/all/suggestions",
 *   operationId="autoGetapiv2searchallsuggestions",
 *   tags={"V2 Search"},
 *   summary="All suggestions",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@allSuggestions",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/all/trending",
 *   operationId="autoGetapiv2searchalltrending",
 *   tags={"V2 Search"},
 *   summary="All trending",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@allTrending",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/history",
 *   operationId="autoGetapiv2searchhistory",
 *   tags={"V2 Search"},
 *   summary="History",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@history",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Delete(
 *   path="/api/v2/search/history",
 *   operationId="autoDeleteapiv2searchhistory",
 *   tags={"V2 Search"},
 *   summary="Clear history",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@clearHistory",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Delete(
 *   path="/api/v2/search/history/{id}",
 *   operationId="autoDeleteapiv2searchhistoryid",
 *   tags={"V2 Search"},
 *   summary="Delete history",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@deleteHistory",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/series",
 *   operationId="autoGetapiv2searchseries",
 *   tags={"V2 Search"},
 *   summary="Search series",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@searchSeries",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/suggestions",
 *   operationId="autoGetapiv2searchsuggestions",
 *   tags={"V2 Search"},
 *   summary="Get search suggestions (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@suggestions",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/search/trending",
 *   operationId="autoGetapiv2searchtrending",
 *   tags={"V2 Search"},
 *   summary="Trending",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SearchController@trending",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/series",
 *   operationId="autoGetapiv2series",
 *   tags={"V2 Movies"},
 *   summary="List series (v2)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@seriesIndex",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/MovieResource")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/series/{id}/episodes",
 *   operationId="autoGetapiv2seriesidepisodes",
 *   tags={"V2 Movies"},
 *   summary="Episodes",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\MovieController@episodes",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/streaming/categories",
 *   operationId="autoGetapiv2streamingcategories",
 *   tags={"V2 Streaming"},
 *   summary="Categories",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\StreamingController@categories",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/streaming/home",
 *   operationId="autoGetapiv2streaminghome",
 *   tags={"V2 Streaming"},
 *   summary="Home",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\StreamingController@home",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/streaming/stations",
 *   operationId="autoGetapiv2streamingstations",
 *   tags={"V2 Streaming"},
 *   summary="Index",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\StreamingController@index",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/streaming/stations/{id}",
 *   operationId="autoGetapiv2streamingstationsid",
 *   tags={"V2 Streaming"},
 *   summary="Show",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\StreamingController@show",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/subscriptions/auto-fix",
 *   operationId="autoPostapiv2subscriptionsautofix",
 *   tags={"Subscription"},
 *   summary="Auto fix",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SubscriptionFixController@autoFix",
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/subscriptions/fixable",
 *   operationId="autoGetapiv2subscriptionsfixable",
 *   tags={"Subscription"},
 *   summary="Fixable",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SubscriptionFixController@fixable",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/subscriptions/{id}/diagnostic",
 *   operationId="autoGetapiv2subscriptionsiddiagnostic",
 *   tags={"Subscription"},
 *   summary="Diagnostic",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SubscriptionFixController@diagnostic",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/v2/subscriptions/{id}/force-check",
 *   operationId="autoPostapiv2subscriptionsidforcecheck",
 *   tags={"Subscription"},
 *   summary="Force check",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\SubscriptionFixController@forceCheck",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/trivia/questions",
 *   operationId="autoGetapiv2triviaquestions",
 *   tags={"V2 Trivia"},
 *   summary="Questions",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\TriviaController@questions",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/trivia/stats",
 *   operationId="autoGetapiv2triviastats",
 *   tags={"V2 Trivia"},
 *   summary="Stats",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\TriviaController@stats",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/v2/trivia/version",
 *   operationId="autoGetapiv2triviaversion",
 *   tags={"V2 Trivia"},
 *   summary="Version",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\V2\\\\TriviaController@version",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/video-playback-failures",
 *   operationId="autoPostapivideoplaybackfailures",
 *   tags={"Movies"},
 *   summary="Report a video playback failure",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\Api\\\\VideoPlaybackFailureController@store",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/VideoProgressRequest")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/video-progress",
 *   operationId="autoPostapivideoprogress",
 *   tags={"Watch History"},
 *   summary="Save video playback progress",
 *   description="Saves the current playback position for a movie so the user can resume later.",
 *   @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/VideoProgressRequest")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/video-progress/{movie_id}",
 *   operationId="autoGetapivideoprogressmovieid",
 *   tags={"Watch History"},
 *   summary="Get video progress",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_video_progress",
 *   @OA\Parameter(name="movie_id", in="path", required=true, description="Movie id", @OA\Schema(type="integer", example="1")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/video-progress/{movie_id}/delete",
 *   operationId="autoPostapivideoprogressmovieiddelete",
 *   tags={"Watch History"},
 *   summary="Delete video progress",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@delete_video_progress",
 *   @OA\Parameter(name="movie_id", in="path", required=true, description="Movie id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/video-transfers",
 *   operationId="autoGetapivideotransfers",
 *   tags={"Movies"},
 *   summary="List video transfer tasks",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiVideoTransferController@index",
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/video-transfers/{id}",
 *   operationId="autoGetapivideotransfersid",
 *   tags={"Movies"},
 *   summary="Show",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiVideoTransferController@show",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Post(
 *   path="/api/video-transfers/{id}/retry",
 *   operationId="autoPostapivideotransfersidretry",
 *   tags={"Movies"},
 *   summary="Retry",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiVideoTransferController@retry",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\RequestBody(required=false, @OA\JsonContent(type="object")),
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/video-transfers/{id}/stream-url",
 *   operationId="autoGetapivideotransfersidstreamurl",
 *   tags={"Movies"},
 *   summary="Get stream url",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\ApiVideoTransferController@getStreamUrl",
 *   @OA\Parameter(name="id", in="path", required=true, description="Id", @OA\Schema(type="integer", example="1")),
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 * @OA\Get(
 *   path="/api/watch-history",
 *   operationId="autoGetapiwatchhistory",
 *   tags={"Watch History"},
 *   summary="Get full watch history (v1)",
 *   description="Controller: App\\\\Http\\\\Controllers\\\\DynamicCrudController@get_watch_history",
 *   security={{"bearerAuth":{}}},
 *   @OA\Response(response=200, description="Successful response", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *   @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=404, description="Not Found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
 *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
 * )
 */
class GeneratedApiEndpoints
{
}
