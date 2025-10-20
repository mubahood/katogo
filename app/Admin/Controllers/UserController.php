<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class UserController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Users';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());
        $grid->model()->orderBy('id', 'desc');
        $grid->filter(function ($filter) {
            // Remove the default id filter
            $filter->disableIdFilter();
            $filter->like('username', 'Username');
            $filter->like('name', 'Name');
            $filter->like('email', 'Email');
            $filter->equal('status', 'Status')->select([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'banned' => 'Banned',
            ]);
            $filter->between('created_at', 'Created At')->datetime();
            $filter->between('updated_at', 'Updated At')->datetime();
        });

        $grid->column('id', __('Id'));
        $grid->column('username', __('Username'));
        $grid->column('name', __('Name'));
        $grid->column('avatar', __('Avatar'))->lightbox();
        $grid->column('app_type', __('App Type'))->sortable()
            ->filter([
                'ugflix' => 'Ugflix',
                'lugaflix' => 'Lugaflix',
            ])
            ->editable('select', [
                'ugflix' => 'Ugflix',
                'lugaflix' => 'Lugaflix',
            ]);
        $grid->column('platform', __('Platform'))->sortable()
            ->filter([
                'android' => 'Android',
                'ios' => 'iOS',
            ]);

        $grid->column('terms_of_service_accepted', __('Terms of service accepted'));
        $grid->column('privacy_policy_accepted', __('Privacy policy accepted'));
        $grid->column('community_guidelines_accepted', __('Community guidelines accepted'));
        $grid->column('marketing_emails_consent', __('Marketing emails consent'));
        $grid->column('data_processing_consent', __('Data processing consent'));
        $grid->column('content_moderation_consent', __('Content moderation consent'));
        $grid->column('terms_accepted_date', __('Terms accepted date'));
        $grid->column('privacy_accepted_date', __('Privacy accepted date'));
        $grid->column('guidelines_accepted_date', __('Guidelines accepted date'));
        $grid->column('notification_preferences', __('Notification preferences'));
        $grid->column('push_notifications', __('Push notifications'));
        $grid->column('email_notifications', __('Email notifications'));
        $grid->column('profile_visibility', __('Profile visibility'));
        $grid->column('content_filtering', __('Content filtering'));
        $grid->column('safe_mode', __('Safe mode'));
        $grid->column('location_sharing', __('Location sharing'));
        $grid->column('analytics_consent', __('Analytics consent'));
        $grid->column('crash_reporting', __('Crash reporting'));
        $grid->column('company_id', __('Company id'));
        $grid->column('first_name', __('First name'));
        $grid->column('last_name', __('Last name'));
        $grid->column('phone_number', __('Phone number'));
        $grid->column('phone_number_2', __('Phone number 2'));
        $grid->column('address', __('Address'));
        $grid->column('sex', __('Sex'));
        $grid->column('dob', __('Dob'));
        $grid->column('status', __('Status'));
        $grid->column('email', __('Email'));
        $grid->column('email_verified_at', __('Email verified at'));
        $grid->column('google_id', __('Google id'));
        $grid->column('secret_code', __('Secret code'));
        $grid->column('profile_photos', __('Profile photos'));
        $grid->column('bio', __('Bio'));
        $grid->column('tagline', __('Tagline'));
        $grid->column('phone_country_name', __('Phone country name'));
        $grid->column('phone_country_code', __('Phone country code'));
        $grid->column('phone_country_international', __('Phone country international'));
        $grid->column('sexual_orientation', __('Sexual orientation'));
        $grid->column('height_cm', __('Height cm'));
        $grid->column('body_type', __('Body type'));
        $grid->column('country', __('Country'));
        $grid->column('state', __('State'));
        $grid->column('city', __('City'));
        $grid->column('latitude', __('Latitude'));
        $grid->column('longitude', __('Longitude'));
        $grid->column('last_online_at', __('Last online at'));
        $grid->column('online_status', __('Online status'));
        $grid->column('looking_for', __('Looking for'));
        $grid->column('interested_in', __('Interested in'));
        $grid->column('age_range_min', __('Age range min'));
        $grid->column('age_range_max', __('Age range max'));
        $grid->column('max_distance_km', __('Max distance km'));
        $grid->column('smoking_habit', __('Smoking habit'));
        $grid->column('drinking_habit', __('Drinking habit'));
        $grid->column('pet_preference', __('Pet preference'));
        $grid->column('religion', __('Religion'));
        $grid->column('political_views', __('Political views'));
        $grid->column('languages_spoken', __('Languages spoken'));
        $grid->column('education_level', __('Education level'));
        $grid->column('occupation', __('Occupation'));
        $grid->column('email_verified', __('Email verified'));
        $grid->column('phone_verified', __('Phone verified'));
        $grid->column('verification_code', __('Verification code'));
        $grid->column('failed_login_attempts', __('Failed login attempts'));
        $grid->column('last_password_change', __('Last password change'));
        $grid->column('subscription_tier', __('Subscription tier'));
        $grid->column('subscription_expires', __('Subscription expires'));
        $grid->column('credits_balance', __('Credits balance'));
        $grid->column('profile_views', __('Profile views'));
        $grid->column('likes_received', __('Likes received'));
        $grid->column('matches_count', __('Matches count'));
        $grid->column('completed_profile_pct', __('Completed profile pct'));
        $grid->column('is_guest', __('Is guest'));
        $grid->column('last_trending_notification_sent', __('Last trending notification sent'));
        $grid->column('last_trending_notification_period', __('Last trending notification period'));
        $grid->column('last_trending_notification_date', __('Last trending notification date'));
        $grid->column('trending_notifications_today', __('Trending notifications today'));
        $grid->column('max_trending_notifications_per_day', __('Max trending notifications per day'));
        $grid->column('created_at', __('Created'));
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(User::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('username', __('Username'));
        $show->field('password', __('Password'));
        $show->field('name', __('Name'));
        $show->field('avatar', __('Avatar'));
        $show->field('remember_token', __('Remember token'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('terms_of_service_accepted', __('Terms of service accepted'));
        $show->field('privacy_policy_accepted', __('Privacy policy accepted'));
        $show->field('community_guidelines_accepted', __('Community guidelines accepted'));
        $show->field('marketing_emails_consent', __('Marketing emails consent'));
        $show->field('data_processing_consent', __('Data processing consent'));
        $show->field('content_moderation_consent', __('Content moderation consent'));
        $show->field('terms_accepted_date', __('Terms accepted date'));
        $show->field('privacy_accepted_date', __('Privacy accepted date'));
        $show->field('guidelines_accepted_date', __('Guidelines accepted date'));
        $show->field('notification_preferences', __('Notification preferences'));
        $show->field('push_notifications', __('Push notifications'));
        $show->field('email_notifications', __('Email notifications'));
        $show->field('profile_visibility', __('Profile visibility'));
        $show->field('content_filtering', __('Content filtering'));
        $show->field('safe_mode', __('Safe mode'));
        $show->field('location_sharing', __('Location sharing'));
        $show->field('analytics_consent', __('Analytics consent'));
        $show->field('crash_reporting', __('Crash reporting'));
        $show->field('company_id', __('Company id'));
        $show->field('first_name', __('First name'));
        $show->field('last_name', __('Last name'));
        $show->field('phone_number', __('Phone number'));
        $show->field('phone_number_2', __('Phone number 2'));
        $show->field('address', __('Address'));
        $show->field('sex', __('Sex'));
        $show->field('dob', __('Dob'));
        $show->field('status', __('Status'));
        $show->field('email', __('Email'));
        $show->field('email_verified_at', __('Email verified at'));
        $show->field('google_id', __('Google id'));
        $show->field('secret_code', __('Secret code'));
        $show->field('profile_photos', __('Profile photos'));
        $show->field('bio', __('Bio'));
        $show->field('tagline', __('Tagline'));
        $show->field('phone_country_name', __('Phone country name'));
        $show->field('phone_country_code', __('Phone country code'));
        $show->field('phone_country_international', __('Phone country international'));
        $show->field('sexual_orientation', __('Sexual orientation'));
        $show->field('height_cm', __('Height cm'));
        $show->field('body_type', __('Body type'));
        $show->field('country', __('Country'));
        $show->field('state', __('State'));
        $show->field('city', __('City'));
        $show->field('latitude', __('Latitude'));
        $show->field('longitude', __('Longitude'));
        $show->field('last_online_at', __('Last online at'));
        $show->field('online_status', __('Online status'));
        $show->field('looking_for', __('Looking for'));
        $show->field('interested_in', __('Interested in'));
        $show->field('age_range_min', __('Age range min'));
        $show->field('age_range_max', __('Age range max'));
        $show->field('max_distance_km', __('Max distance km'));
        $show->field('smoking_habit', __('Smoking habit'));
        $show->field('drinking_habit', __('Drinking habit'));
        $show->field('pet_preference', __('Pet preference'));
        $show->field('religion', __('Religion'));
        $show->field('political_views', __('Political views'));
        $show->field('languages_spoken', __('Languages spoken'));
        $show->field('education_level', __('Education level'));
        $show->field('occupation', __('Occupation'));
        $show->field('email_verified', __('Email verified'));
        $show->field('phone_verified', __('Phone verified'));
        $show->field('verification_code', __('Verification code'));
        $show->field('failed_login_attempts', __('Failed login attempts'));
        $show->field('last_password_change', __('Last password change'));
        $show->field('subscription_tier', __('Subscription tier'));
        $show->field('subscription_expires', __('Subscription expires'));
        $show->field('credits_balance', __('Credits balance'));
        $show->field('profile_views', __('Profile views'));
        $show->field('likes_received', __('Likes received'));
        $show->field('matches_count', __('Matches count'));
        $show->field('completed_profile_pct', __('Completed profile pct'));
        $show->field('is_guest', __('Is guest'));
        $show->field('last_trending_notification_sent', __('Last trending notification sent'));
        $show->field('last_trending_notification_period', __('Last trending notification period'));
        $show->field('last_trending_notification_date', __('Last trending notification date'));
        $show->field('trending_notifications_today', __('Trending notifications today'));
        $show->field('max_trending_notifications_per_day', __('Max trending notifications per day'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new User());

        //app_type
        $form->select('app_type', __('App Type'))->options([
            'ugflix' => 'Ugflix',
            'lugaflix' => 'Lugaflix',
        ])->default('ugflix');

        $form->text('username', __('Username'));
        $form->password('password', __('Password'));
        $form->text('name', __('Name'));
        $form->image('avatar', __('Avatar'));
        $form->text('remember_token', __('Remember token'));
        $form->text('terms_of_service_accepted', __('Terms of service accepted'));
        $form->text('privacy_policy_accepted', __('Privacy policy accepted'));
        $form->text('community_guidelines_accepted', __('Community guidelines accepted'));
        $form->text('marketing_emails_consent', __('Marketing emails consent'));
        $form->text('data_processing_consent', __('Data processing consent'));
        $form->text('content_moderation_consent', __('Content moderation consent'));
        $form->datetime('terms_accepted_date', __('Terms accepted date'))->default(date('Y-m-d H:i:s'));
        $form->datetime('privacy_accepted_date', __('Privacy accepted date'))->default(date('Y-m-d H:i:s'));
        $form->datetime('guidelines_accepted_date', __('Guidelines accepted date'))->default(date('Y-m-d H:i:s'));
        $form->text('notification_preferences', __('Notification preferences'));
        $form->text('push_notifications', __('Push notifications'));
        $form->text('email_notifications', __('Email notifications'));
        $form->text('profile_visibility', __('Profile visibility'))->default('Public');
        $form->text('content_filtering', __('Content filtering'))->default('On');
        $form->text('safe_mode', __('Safe mode'))->default('On');
        $form->text('location_sharing', __('Location sharing'));
        $form->text('analytics_consent', __('Analytics consent'));
        $form->text('crash_reporting', __('Crash reporting'));
        $form->number('company_id', __('Company id'));
        $form->textarea('first_name', __('First name'));
        $form->textarea('last_name', __('Last name'));
        $form->textarea('phone_number', __('Phone number'));
        $form->textarea('phone_number_2', __('Phone number 2'));
        $form->textarea('address', __('Address'));
        $form->textarea('sex', __('Sex'));
        $form->date('dob', __('Dob'))->default(date('Y-m-d'));
        $form->text('status', __('Status'))->default('active');
        $form->email('email', __('Email'));
        $form->datetime('email_verified_at', __('Email verified at'))->default(date('Y-m-d H:i:s'));
        $form->text('google_id', __('Google id'));
        $form->text('secret_code', __('Secret code'));
        $form->text('profile_photos', __('Profile photos'));
        $form->textarea('bio', __('Bio'));
        $form->text('tagline', __('Tagline'));
        $form->text('phone_country_name', __('Phone country name'));
        $form->text('phone_country_code', __('Phone country code'));
        $form->text('phone_country_international', __('Phone country international'));
        $form->text('sexual_orientation', __('Sexual orientation'));
        $form->number('height_cm', __('Height cm'));
        $form->text('body_type', __('Body type'));
        $form->text('country', __('Country'));
        $form->text('state', __('State'));
        $form->text('city', __('City'));
        $form->decimal('latitude', __('Latitude'));
        $form->decimal('longitude', __('Longitude'));
        $form->datetime('last_online_at', __('Last online at'))->default(date('Y-m-d H:i:s'));
        $form->text('online_status', __('Online status'))->default('Offline');
        $form->textarea('looking_for', __('Looking for'));
        $form->textarea('interested_in', __('Interested in'));
        $form->number('age_range_min', __('Age range min'));
        $form->number('age_range_max', __('Age range max'));
        $form->number('max_distance_km', __('Max distance km'));
        $form->text('smoking_habit', __('Smoking habit'));
        $form->text('drinking_habit', __('Drinking habit'));
        $form->text('pet_preference', __('Pet preference'));
        $form->text('religion', __('Religion'));
        $form->text('political_views', __('Political views'));
        $form->textarea('languages_spoken', __('Languages spoken'));
        $form->text('education_level', __('Education level'));
        $form->text('occupation', __('Occupation'));
        $form->switch('email_verified', __('Email verified'));
        $form->switch('phone_verified', __('Phone verified'));
        $form->text('verification_code', __('Verification code'));
        $form->number('failed_login_attempts', __('Failed login attempts'));
        $form->datetime('last_password_change', __('Last password change'))->default(date('Y-m-d H:i:s'));
        $form->text('subscription_tier', __('Subscription tier'));
        $form->datetime('subscription_expires', __('Subscription expires'))->default(date('Y-m-d H:i:s'));
        $form->number('credits_balance', __('Credits balance'));
        $form->number('profile_views', __('Profile views'));
        $form->number('likes_received', __('Likes received'));
        $form->number('matches_count', __('Matches count'));
        $form->number('completed_profile_pct', __('Completed profile pct'));
        $form->text('is_guest', __('Is guest'))->default('No');
        $form->datetime('last_trending_notification_sent', __('Last trending notification sent'))->default(date('Y-m-d H:i:s'));
        $form->text('last_trending_notification_period', __('Last trending notification period'));
        $form->date('last_trending_notification_date', __('Last trending notification date'))->default(date('Y-m-d'));
        $form->number('trending_notifications_today', __('Trending notifications today'));
        $form->number('max_trending_notifications_per_day', __('Max trending notifications per day'))->default(4);

        return $form;
    }
}
