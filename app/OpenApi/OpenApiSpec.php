<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Katogo API Documentation",
 *     description="Complete OpenAPI documentation for all Katogo Laravel APIs. Use the Authorize button to enter your JWT Bearer token before testing protected endpoints."
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Local development (MAMP)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=1),
 *     @OA\Property(property="status", type="integer", example=200),
 *     @OA\Property(property="message", type="string", example="Request successful"),
 *     @OA\Property(property="data", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=0),
 *     @OA\Property(property="status", type="integer", example=422),
 *     @OA\Property(property="message", type="string", example="Validation failed"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Pagination",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="total", type="integer", example=250),
 *     @OA\Property(property="last_page", type="integer", example=13)
 * )
 *
 * @OA\Parameter(
 *     parameter="idPathParam",
 *     name="id",
 *     in="path",
 *     required=true,
 *     @OA\Schema(type="integer", example=1)
 * )
 *
 * @OA\Parameter(
 *     parameter="pageQueryParam",
 *     name="page",
 *     in="query",
 *     required=false,
 *     @OA\Schema(type="integer", example=1)
 * )
 *
 * @OA\Parameter(
 *     parameter="perPageQueryParam",
 *     name="per_page",
 *     in="query",
 *     required=false,
 *     @OA\Schema(type="integer", example=20)
 * )
 *
 * @OA\Parameter(
 *     parameter="langQueryParam",
 *     name="lang",
 *     in="query",
 *     required=false,
 *     @OA\Schema(type="string", example="en")
 * )
 *
 * @OA\Tag(name="Auth", description="Authentication APIs")
 * @OA\Tag(name="Subscription", description="Subscription and payment APIs")
 * @OA\Tag(name="Movies", description="Movie and catalog APIs")
 * @OA\Tag(name="Account", description="User account APIs")
 * @OA\Tag(name="Moderation", description="Content moderation APIs")
 * @OA\Tag(name="Watch History", description="Video progress and watch history APIs")
 * @OA\Tag(name="V2 Manifest", description="Version 2 manifest APIs")
 * @OA\Tag(name="V2 Movies", description="Version 2 movie APIs")
 * @OA\Tag(name="V2 Search", description="Version 2 search APIs")
 * @OA\Tag(name="V2 Streaming", description="Version 2 streaming APIs")
 * @OA\Tag(name="V2 Blog", description="Version 2 blog APIs")
 * @OA\Tag(name="V2 Downloads", description="Version 2 download APIs")
 * @OA\Tag(name="V2 SafeMode", description="Version 2 safemode analytics APIs")
 * @OA\Tag(name="V2 Trivia", description="Version 2 trivia APIs")
 * @OA\Tag(name="V2 Game Stats", description="Version 2 game stats APIs")
 * @OA\Tag(name="Diagnostics/Test", description="Internal diagnostics and test endpoints")
 *
 * ─── Domain Resource Schemas ────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="MovieResource",
 *     type="object",
 *     description="A single movie/content item",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="The Return"),
 *     @OA\Property(property="description", type="string", example="A gripping Luganda drama about resilience."),
 *     @OA\Property(property="thumbnail", type="string", format="url", example="https://cdn.katogo.app/thumbnails/1.jpg"),
 *     @OA\Property(property="genre", type="string", example="Drama"),
 *     @OA\Property(property="year", type="integer", example=2024),
 *     @OA\Property(property="is_free", type="boolean", example=false),
 *     @OA\Property(property="duration_seconds", type="integer", example=5400),
 *     @OA\Property(property="language", type="string", example="Luganda"),
 *     @OA\Property(property="views_count", type="integer", example=1200),
 *     @OA\Property(property="likes_count", type="integer", example=340)
 * )
 *
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     description="Authenticated user profile",
 *     @OA\Property(property="id", type="integer", example=42),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="https://cdn.katogo.app/avatars/42.jpg"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+256700000000"),
 *     @OA\Property(property="has_active_subscription", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="SubscriptionPlanResource",
 *     type="object",
 *     description="A subscription pricing plan",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Monthly Premium"),
 *     @OA\Property(property="price", type="number", format="float", example=15000),
 *     @OA\Property(property="currency", type="string", example="UGX"),
 *     @OA\Property(property="duration_days", type="integer", example=30),
 *     @OA\Property(property="features", type="array", @OA\Items(type="string", example="HD Streaming"))
 * )
 *
 * @OA\Schema(
 *     schema="SubscriptionResource",
 *     type="object",
 *     description="An active or historical user subscription",
 *     @OA\Property(property="id", type="integer", example=5),
 *     @OA\Property(property="plan_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"active","expired","cancelled"}, example="active"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-01-01T00:00:00Z"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", example="2026-02-01T00:00:00Z"),
 *     @OA\Property(property="payment_gateway", type="string", example="flutterwave")
 * )
 *
 * @OA\Schema(
 *     schema="VideoProgressResource",
 *     type="object",
 *     description="Saved video playback progress for a user",
 *     @OA\Property(property="movie_model_id", type="integer", example=10),
 *     @OA\Property(property="progress", type="number", format="float", example=125.5, description="Playback position in seconds"),
 *     @OA\Property(property="duration", type="number", format="float", example=5400, description="Total video duration in seconds"),
 *     @OA\Property(property="percent_watched", type="number", format="float", example=2.32),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-04-01T12:00:00Z")
 * )
 *
 * ─── Request Body Schemas ────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="InitPaymentRequest",
 *     required={"plan_id"},
 *     type="object",
 *     @OA\Property(property="plan_id", type="integer", example=1, description="ID of the subscription plan to purchase"),
 *     @OA\Property(property="callback_url", type="string", format="url", nullable=true, example="https://yourapp.com/payment-callback"),
 *     @OA\Property(property="payment_gateway", type="string", enum={"pesapal","flutterwave"}, nullable=true, example="flutterwave"),
 *     @OA\Property(property="payment_channel", type="string", enum={"card","mobile_money"}, nullable=true, example="mobile_money"),
 *     @OA\Property(property="mobile_money_phone", type="string", nullable=true, example="+256700000000", maxLength=30)
 * )
 *
 * @OA\Schema(
 *     schema="InitGatewayRequest",
 *     required={"payment_gateway"},
 *     type="object",
 *     @OA\Property(property="payment_gateway", type="string", enum={"pesapal","flutterwave"}, example="flutterwave")
 * )
 *
 * @OA\Schema(
 *     schema="VerifyPaymentRequest",
 *     required={"subscription_id"},
 *     type="object",
 *     @OA\Property(property="subscription_id", type="integer", example=5),
 *     @OA\Property(property="callback_url", type="string", format="url", nullable=true, example="https://yourapp.com/payment-callback"),
 *     @OA\Property(property="payment_gateway", type="string", enum={"pesapal","flutterwave"}, nullable=true, example="flutterwave")
 * )
 *
 * @OA\Schema(
 *     schema="CancelSubscriptionRequest",
 *     required={"subscription_id"},
 *     type="object",
 *     @OA\Property(property="subscription_id", type="integer", example=5)
 * )
 *
 * @OA\Schema(
 *     schema="VideoProgressRequest",
 *     required={"movie_model_id","progress","duration"},
 *     type="object",
 *     @OA\Property(property="movie_model_id", type="integer", example=10),
 *     @OA\Property(property="progress", type="number", format="float", example=125.5, description="Playback position in seconds"),
 *     @OA\Property(property="duration", type="number", format="float", example=5400, description="Total video duration in seconds")
 * )
 *
 * @OA\Schema(
 *     schema="WatchlistAddRequest",
 *     required={"movie_id"},
 *     type="object",
 *     @OA\Property(property="movie_id", type="integer", example=10)
 * )
 *
 * @OA\Schema(
 *     schema="LikeToggleRequest",
 *     required={"movie_id"},
 *     type="object",
 *     @OA\Property(property="movie_id", type="integer", example=10)
 * )
 *
 * @OA\Schema(
 *     schema="WishlistAddRequest",
 *     required={"movie_id"},
 *     type="object",
 *     @OA\Property(property="movie_id", type="integer", example=10)
 * )
 *
 * @OA\Schema(
 *     schema="BlockUserRequest",
 *     required={"blocked_user_id"},
 *     type="object",
 *     @OA\Property(property="blocked_user_id", type="integer", example=99),
 *     @OA\Property(property="reason", type="string", nullable=true, example="Harassment", maxLength=500),
 *     @OA\Property(property="block_type", type="string", enum={"user_initiated","moderator_initiated","automatic"}, nullable=true, example="user_initiated")
 * )
 *
 * @OA\Schema(
 *     schema="UnblockUserRequest",
 *     required={"blocked_user_id"},
 *     type="object",
 *     @OA\Property(property="blocked_user_id", type="integer", example=99)
 * )
 *
 * @OA\Schema(
 *     schema="ReportContentRequest",
 *     type="object",
 *     @OA\Property(property="reported_content_type", type="string", example="movie", description="Type of content being reported"),
 *     @OA\Property(property="reported_content_id", type="integer", example=10, description="ID of the reported content item"),
 *     @OA\Property(property="reason", type="string", example="Inappropriate content")
 * )
 *
 * @OA\Schema(
 *     schema="LegalConsentRequest",
 *     type="object",
 *     @OA\Property(property="terms_of_service_accepted", type="string", enum={"Yes","No"}, nullable=true, example="Yes"),
 *     @OA\Property(property="privacy_policy_accepted", type="string", enum={"Yes","No"}, nullable=true, example="Yes"),
 *     @OA\Property(property="community_guidelines_accepted", type="string", enum={"Yes","No"}, nullable=true, example="Yes"),
 *     @OA\Property(property="marketing_emails_consent", type="string", enum={"Yes","No"}, nullable=true, example="No"),
 *     @OA\Property(property="data_processing_consent", type="string", enum={"Yes","No"}, nullable=true, example="Yes"),
 *     @OA\Property(property="content_moderation_consent", type="string", enum={"Yes","No"}, nullable=true, example="Yes")
 * )
 *
 * @OA\Schema(
 *     schema="SafeModeTrackRequest",
 *     required={"external_video_id","action"},
 *     type="object",
 *     @OA\Property(property="external_video_id", type="integer", example=501),
 *     @OA\Property(property="action", type="string", enum={"view","play","like","mylist"}, example="play"),
 *     @OA\Property(property="video_title", type="string", nullable=true, example="Best of Katogo", maxLength=500),
 *     @OA\Property(property="category", type="string", nullable=true, example="Comedy", maxLength=100),
 *     @OA\Property(property="genre", type="string", nullable=true, example="Drama", maxLength=100)
 * )
 *
 * @OA\Schema(
 *     schema="SafeModeProgressRequest",
 *     required={"external_video_id","progress_seconds","duration_seconds"},
 *     type="object",
 *     @OA\Property(property="external_video_id", type="integer", example=501),
 *     @OA\Property(property="progress_seconds", type="number", format="float", example=340.5),
 *     @OA\Property(property="duration_seconds", type="number", format="float", example=5400),
 *     @OA\Property(property="video_title", type="string", nullable=true, example="Best of Katogo", maxLength=500)
 * )
 *
 * @OA\Schema(
 *     schema="GameStatsBulkRequest",
 *     required={"stats"},
 *     type="object",
 *     @OA\Property(
 *         property="stats",
 *         type="array",
 *         maxItems=10,
 *         @OA\Items(
 *             type="object",
 *             required={"game_type","games_played","wins","losses","draws"},
 *             @OA\Property(property="game_type", type="string", example="ludo"),
 *             @OA\Property(property="games_played", type="integer", example=10),
 *             @OA\Property(property="wins", type="integer", example=6),
 *             @OA\Property(property="losses", type="integer", example=3),
 *             @OA\Property(property="draws", type="integer", example=1),
 *             @OA\Property(property="high_score", type="integer", example=500),
 *             @OA\Property(property="total_play_seconds", type="integer", example=7200)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="DownloadRequest",
 *     required={"movie_model_id","download_type"},
 *     type="object",
 *     @OA\Property(property="movie_model_id", type="integer", example=10),
 *     @OA\Property(property="download_type", type="string", enum={"gallery","in_app"}, example="in_app")
 * )
 */
class OpenApiSpec
{
}
