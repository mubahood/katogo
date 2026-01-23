<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ImportedUsersController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'System Users Management';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());
        
        // Show all users (both imported and regular)
        $grid->model()->orderBy('id', 'desc');

        $grid->quickSearch('first_name', 'last_name', 'phone_number', 'phone_number_2', 'email', 'username')
            ->placeholder('Search by name, phone, email or username');

        $grid->column('id', __('ID'))->sortable();
        
        $grid->column('avatar', __('Photo'))->image('', 50, 50);
        
        $grid->column('name', __('Name'))->display(function () {
            return $this->first_name . ' ' . $this->last_name;
        })->sortable();
        
        $grid->column('phone_number', __('Phone'))->sortable();
        $grid->column('phone_number_2', __('Phone 2'))->hide();
        
        $grid->column('email', __('Email'))->hide();
        $grid->column('username', __('Username'))->hide();
        
        $grid->column('address', __('Location'))->sortable();
        
        $grid->column('sex', __('Gender'))
            ->filter([
                'Male' => 'Male',
                'Female' => 'Female',
                'Other' => 'Other'
            ])->sortable();
        
        $grid->column('dob', __('DOB'))->sortable()->hide();
        
        $grid->column('age', __('Age'))->display(function () {
            if ($this->dob) {
                return \Carbon\Carbon::parse($this->dob)->age;
            }
            return 'N/A';
        })->sortable();
        
        $grid->column('import_source', __('Source'))
            ->filter([
                'Uganda Hot Girls' => 'Uganda Hot Girls',
            ])->sortable()->label([
                'Uganda Hot Girls' => 'info',
            ]);
        
        $grid->column('external_profile_url', __('Profile URL'))->display(function ($url) {
            if ($url) {
                return "<a href='{$url}' target='_blank' class='btn btn-xs btn-primary'><i class='fa fa-external-link'></i> View</a>";
            }
            return '';
        });
        
        $grid->column('is_imported', __('Imported'))
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No'
            ])->sortable()->label([
                'Yes' => 'success',
                'No' => 'default'
            ]);
        
        $grid->column('status', __('Status'))
            ->dot([
                'Active' => 'success',
                'Inactive' => 'danger',
                'Suspended' => 'warning',
            ], 'info')
            ->filter([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
                'Suspended' => 'Suspended'
            ])->sortable();
        
        $grid->column('app_type', __('Type'))->hide();
        
        $grid->column('imported_at', __('Imported At'))
            ->display(function ($date) {
                return $date ? date('Y-m-d H:i', strtotime($date)) : 'N/A';
            })->sortable();
        
        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('Y-m-d H:i', strtotime($created_at));
            })->sortable();
        
        $grid->column('last_online_at', __('Last Online'))
            ->display(function ($date) {
                return $date ? date('Y-m-d H:i', strtotime($date)) : 'Never';
            })->sortable()->hide();

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->like('first_name', 'First Name');
            $filter->like('last_name', 'Last Name');
            $filter->like('phone_number', 'Phone');
            $filter->like('address', 'Location');
            
            $filter->equal('sex', 'Gender')->select([
                'Male' => 'Male',
                'Female' => 'Female',
                'Other' => 'Other'
            ]);
            
            $filter->equal('status', 'Status')->select([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
                'Suspended' => 'Suspended'
            ]);
            
            $filter->equal('import_source', 'Source')->select([
                'Uganda Hot Girls' => 'Uganda Hot Girls',
            ]);
            
            $filter->between('created_at', 'Created')->datetime();
            $filter->between('imported_at', 'Imported')->datetime();
        });

        // Export
        $grid->exporter(function ($export) {
            $export->filename('Imported_Users_' . date('Y-m-d'));
            $export->except(['password', 'remember_token']);
        });

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

        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
        });

        $show->divider('Basic Information');
        $show->field('id', __('ID'));
        $show->field('avatar', __('Photo'))->image('', 100, 100);
        $show->field('first_name', __('First Name'));
        $show->field('last_name', __('Last Name'));
        $show->field('name', __('Full Name'));
        $show->field('username', __('Username'));
        $show->field('email', __('Email'));
        
        $show->divider('Contact Information');
        $show->field('phone_number', __('Primary Phone'));
        $show->field('phone_number_2', __('Secondary Phone'));
        $show->field('address', __('Address/Location'));
        
        $show->divider('Personal Details');
        $show->field('sex', __('Gender'));
        $show->field('dob', __('Date of Birth'));
        $show->field('age', __('Age'))->as(function () {
            if ($this->dob) {
                return \Carbon\Carbon::parse($this->dob)->age . ' years';
            }
            return 'N/A';
        });
        
        $show->divider('Import Information');
        $show->field('is_imported', __('Is Imported'))->using([
            'Yes' => 'Yes',
            'No' => 'No'
        ])->label([
            'Yes' => 'success',
            'No' => 'default'
        ]);
        $show->field('import_source', __('Import Source'));
        $show->field('external_profile_url', __('External Profile URL'))->link();
        $show->field('imported_at', __('Imported At'));
        
        $show->divider('Profile Details');
        $show->field('bio', __('Bio/Description'))->unescape();
        $show->field('tagline', __('Tagline'));
        $show->field('app_type', __('App Type'));
        
        $show->divider('Dating Profile Specifics');
        $show->field('sexual_orientation', __('Sexual Orientation'));
        $show->field('height_cm', __('Height (cm)'));
        $show->field('body_type', __('Body Type'));
        $show->field('smoking_habit', __('Smoking Habit'));
        $show->field('drinking_habit', __('Drinking Habit'));
        $show->field('pet_preference', __('Pet Preference'));
        $show->field('religion', __('Religion'));
        $show->field('political_views', __('Political Views'));
        $show->field('languages_spoken', __('Languages Spoken'));
        $show->field('education_level', __('Education Level'));
        $show->field('occupation', __('Occupation'));
        
        $show->divider('Location Details');
        $show->field('country', __('Country'));
        $show->field('state', __('State'));
        $show->field('city', __('City'));
        $show->field('latitude', __('Latitude'));
        $show->field('longitude', __('Longitude'));
        
        $show->divider('Account Status');
        $show->field('status', __('Status'))->using([
            'Active' => 'Active',
            'Inactive' => 'Inactive',
            'Suspended' => 'Suspended'
        ])->dot([
            'Active' => 'success',
            'Inactive' => 'danger',
            'Suspended' => 'warning'
        ]);
        $show->field('online_status', __('Online Status'));
        $show->field('last_online_at', __('Last Online'));
        
        $show->divider('Statistics');
        $show->field('profile_views', __('Profile Views'));
        $show->field('likes_received', __('Likes Received'));
        $show->field('matches_count', __('Matches Count'));
        $show->field('completed_profile_pct', __('Profile Completion'))->as(function ($val) {
            return $val . '%';
        });
        
        $show->divider('Verification');
        $show->field('email_verified', __('Email Verified'))->using([
            1 => 'Yes',
            0 => 'No'
        ]);
        $show->field('phone_verified', __('Phone Verified'))->using([
            1 => 'Yes',
            0 => 'No'
        ]);
        
        $show->divider('Timestamps');
        $show->field('created_at', __('Created At'));
        $show->field('updated_at', __('Updated At'));

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

        $form->tab('Basic Info', function ($form) {
            $form->text('first_name', __('First Name'))->required();
            $form->text('last_name', __('Last Name'))->required();
            $form->text('username', __('Username'));
            $form->email('email', __('Email'));
            $form->image('avatar', __('Photo'));
            
            $form->radio('sex', __('Gender'))
                ->options([
                    'Male' => 'Male',
                    'Female' => 'Female',
                    'Other' => 'Other',
                ])->default('Female');
            
            $form->date('dob', __('Date of Birth'));
        });

        $form->tab('Contact', function ($form) {
            $form->text('phone_number', __('Primary Phone'));
            $form->text('phone_number_2', __('Secondary Phone'));
            $form->text('address', __('Address/Location'));
            $form->text('city', __('City'));
            $form->text('state', __('State'));
            $form->text('country', __('Country'))->default('Uganda');
        });

        $form->tab('Profile', function ($form) {
            $form->textarea('bio', __('Bio/Description'))->rows(5);
            $form->text('tagline', __('Tagline'));
            
            $form->select('sexual_orientation', __('Sexual Orientation'))
                ->options([
                    'Straight' => 'Straight',
                    'Gay' => 'Gay',
                    'Lesbian' => 'Lesbian',
                    'Bisexual' => 'Bisexual',
                    'Other' => 'Other'
                ]);
            
            $form->number('height_cm', __('Height (cm)'));
            $form->text('body_type', __('Body Type'));
            $form->text('occupation', __('Occupation'));
            $form->text('education_level', __('Education Level'));
        });

        $form->tab('Import Info', function ($form) {
            $form->radio('is_imported', __('Is Imported'))
                ->options([
                    'Yes' => 'Yes',
                    'No' => 'No',
                ])->default('Yes')->readOnly();
            
            $form->text('import_source', __('Import Source'))->readOnly();
            $form->url('external_profile_url', __('External Profile URL'))->readOnly();
            $form->datetime('imported_at', __('Imported At'))->readOnly();
        });

        $form->tab('Status', function ($form) {
            $form->radio('status', __('Status'))
                ->options([
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'Suspended' => 'Suspended',
                ])->default('Active');
            
            $form->text('app_type', __('App Type'))->default('Dating Profile')->readOnly();
        });

        // Remove password fields for imported users
        $form->saving(function (Form $form) {
            if (!$form->password) {
                $form->password = bcrypt(uniqid());
            }
        });

        return $form;
    }
}
