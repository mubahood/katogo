<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Administrator implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $table = 'admin_users';

    //company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    //boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $name = "";
            if ($model->first_name != null && strlen($model->first_name) > 0) {
                $name = $model->first_name;
            }
            if ($model->last_name != null && strlen($model->last_name) > 0) {
                $name .= " " . $model->last_name;
            }
            $name = trim($name);

            if ($name != null && strlen($name) > 0) {
                $model->name = $name;
            }
            $model->username = $model->email;

            if ($model->password == null || strlen($model->password) < 3) {
                $model->password = bcrypt('admin');
            }

            if ($model->phone_number == null && strlen($model->phone_number) > 6) {
                $phone_number = $model->phone_number;
                $existing_user = User::where('phone_number', $phone_number)->first();
                if ($existing_user != null) {
                    throw new \Exception('Phone number already exists');
                }
            }

            if ($model->email == null && strlen($model->email) > 6) {
                $email = $model->email;
                $existing_user = User::where('email', $email)->first();
                if ($existing_user != null) {
                    throw new \Exception('Email already exists');
                }
            }

            //do the same for username
            if ($model->username == null && strlen($model->username) > 6) {
                $username = $model->username;
                $existing_user = User::where('username', $username)->first();
                if ($existing_user != null) {
                    throw new \Exception('Username already exists');
                }
            }

            return $model;
        });


        static::updating(function ($model) {
            $name = "";
            if ($model->first_name != null && strlen($model->first_name) > 0) {
                $name = $model->first_name;
            }
            if ($model->last_name != null && strlen($model->last_name) > 0) {
                $name .= " " . $model->last_name;
            }
            $name = trim($name);

            if ($name != null && strlen($name) > 0) {
                $model->name = $name;
            }

            if ($model->phone_number == null && strlen($model->phone_number) > 6) {
                $phone_number = $model->phone_number;
                $existing_user = User::where('phone_number', $phone_number)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Phone number already exists');
                }
            }
            if ($model->email == null && strlen($model->email) > 6) {
                $email = $model->email;
                $existing_user = User::where('email', $email)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Email already exists');
                }
            }
            //do the same for username
            if ($model->username == null && strlen($model->username) > 6) {
                $username = $model->username;
                $existing_user = User::where('username', $username)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Username already exists');
                }
            }

            $model->username = $model->email;
            return $model;
        });

        // Handle manual cascade deletes for moderation-related data
        static::deleting(function ($user) {
            // Delete content reports where user is reporter or reported user
            \App\Models\ContentReport::where('reporter_id', $user->id)->delete();
            \App\Models\ContentReport::where('reported_user_id', $user->id)->delete();
            
            // Delete user blocks where user is blocker or blocked
            \App\Models\UserBlock::where('blocker_id', $user->id)->delete();
            \App\Models\UserBlock::where('blocked_user_id', $user->id)->delete();
            
            // Delete moderation logs for this user
            \App\Models\ContentModerationLog::where('user_id', $user->id)->delete();
            
            // Set moderator_id to null for logs where this user was the moderator
            \App\Models\ContentModerationLog::where('moderator_id', $user->id)
                ->update(['moderator_id' => null]);
                
            // Set moderator_id to null for reports where this user was the moderator
            \App\Models\ContentReport::where('moderator_id', $user->id)
                ->update(['moderator_id' => null]);
        });
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    // getter for avatar
    public function getAvatarAttribute($value)
    {
        return $value;
        if ($value == null || strlen($value) < 3) {
            return url('logo.png');
        }
        $path = public_path('storage/' . $value);
        if (!file_exists($path)) {
            return url('logo.png');
        }
        return $value;
    }

    //getter for online_status
    public function getOnlineStatusAttribute($value)
    {
        $last_online_at = $this->last_online_at;
        if ($last_online_at == null || strlen($last_online_at) < 3) {
            $this->last_online_at = $this->updated_at;
            $this->save();
        }
        $last_online_at = null;
        try {
            $last_online_at = \Carbon\Carbon::parse($this->last_online_at);
        } catch (\Exception $e) {
            return 'Offline';
        }
        $now = \Carbon\Carbon::now();
        //mins ago
        $diff = $last_online_at->diffInMinutes($now);
        if ($diff < 25) {
            return 'Online';
        }
        return Utils::time_ago($last_online_at) . ' ago';
    }

    //setter for languages_spoken if is array to json

    /**
     * Moderation-related relationships
     */
    
    // Content reports made by this user
    public function contentReports()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'reporter_id');
    }
    
    // Content reports about this user
    public function reportsAgainst()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'reported_user_id');
    }
    
    // Reports moderated by this user (if admin/moderator)
    public function moderatedReports()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'moderator_id');
    }
    
    // Users blocked by this user
    public function blockedUsers()
    {
        return $this->hasMany(\App\Models\UserBlock::class, 'blocker_id');
    }
    
    // Users who blocked this user
    public function blockedBy()
    {
        return $this->hasMany(\App\Models\UserBlock::class, 'blocked_user_id');
    }
    
    // Moderation logs for this user
    public function moderationLogs()
    {
        return $this->hasMany(\App\Models\ContentModerationLog::class, 'user_id');
    }
    
    // Moderation actions taken by this user (if admin/moderator)
    public function moderationActions()
    {
        return $this->hasMany(\App\Models\ContentModerationLog::class, 'moderator_id');
    }
    
    /**
     * Check if user is blocked by another user
     */
    public function isBlockedBy($userId)
    {
        return \App\Models\UserBlock::where('blocker_id', $userId)
            ->where('blocked_user_id', $this->id)
            ->active()
            ->exists();
    }
    
    /**
     * Check if user has blocked another user  
     */
    public function hasBlocked($userId)
    {
        return \App\Models\UserBlock::where('blocker_id', $this->id)
            ->where('blocked_user_id', $userId)
            ->active()
            ->exists();
    }
}
