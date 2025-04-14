<?php

namespace App\Admin\Controllers;

use App\Models\School;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SchoolController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'School';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {

        /* $schools = School::where(
            'fax',
            '!=',
            'NO',
        )
            ->limit(20000)
            ->get();
        foreach ($schools as $school) {
            //get duplicate schools for url
            $dups = School::where('url', $school->url)
                ->where('id', '!=', $school->id)
                ->get();
            if ($dups->count() > 0) {
                $count = 0;
                foreach ($dups as $dup) {
                    $count++;
                    $dup->delete();
                } 
                echo("Deleted " . $count . " duplicates for " . $school->name . " " . $school->id . " <br>"); 
            }
            $school->fax = 'NO';
            $school->save();
            echo("Doe with " . $school->name . " " . $school->id. " <br>");
        }
        die('Done'); */

        $grid = new Grid(new School());
        $grid->model()->orderBy('name', 'asc');
        $grid->quickSearch('district',);
        $grid->disableBatchActions();
        $grid->picture('image', __('Image'))->image(
            '',
            100,
            100,
        )->width(100);


        $grid->column('id', __('Id'))->sortable()->hide();
        $grid->column('created_at', __('Created at'))->hide();
        $grid->column('updated_at', __('Updated at'))->hide();
        $grid->column('name', __('Name'))->sortable()
            ->filter('like')
            ->width(250);
        $grid->column('district', __('District'))->sortable()->hide();
        $grid->column('sub_county', __('Sub county'))->hide();
        $grid->column('parish', __('Parish'))->sortable()
            ->filter('like');
        $grid->column('address', __('Address'))->hide();
        $grid->column('p_o_box', __('P o box'))->hide();
        $grid->column('email', __('Email'))->sortable()->editable();
        $grid->column('website', __('Website'))->sortable()->editable();
        $grid->column('phone', __('Phone'))->sortable()->editable();
        $grid->column('fax', __('Fax'))->hide();
        $grid->column('level', __('School Level'))
            ->label([
                'Nursery' => 'info',
                'Primary' => 'info',
                'Secondary' => 'success',
                'Tertiary' => 'danger',
                'University' => 'warning',
            ])
            ->filter([
                'Nursery' => 'Nursery',
                'Primary' => 'Primary',
                'Secondary' => 'Secondary',
                'Tertiary' => 'Tertiary',
                'University' => 'University',
            ])
            ->sortable();
        $grid->column('reg_no', __('Reg no'))->hide();
        $grid->column('operation_status', __('Operation status'))->sortable()->hide();
        $grid->column('founder', __('Founder'))->hide();
        $grid->column('funder', __('Funder'))->hide();
        $grid->column('boys_girls', __('Boys girls'))->sortable()->hide();
        $grid->column('day_boarding', __('Day boarding'))->hide();
        $grid->column('nearest_school', __('Nearest school'))->hide();
        $grid->column('nearest_school_distance', __('Nearest school distance'))->hide();
        $grid->column('founding_year', __('Founding year'))->sortable();

        $grid->column('latitude', __('Latitude'))->hide();
        $grid->column('longitude', __('Longitude'))->hide();
        $grid->column('highest_class', __('Highest class'))->hide();
        $grid->column('access', __('Access'))->hide();
        $grid->column('details', __('Details'))->hide();
        $grid->column('has_email', __('Has email'))->sortable()->filter([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->hide();
        $grid->column('has_website', __('Has website'))->hide()->filter([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->hide();
        $grid->column('has_phone', __('Has phone'))->sortable()->filter([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->hide();
        $grid->column('contated', __('Contated'))->sortable();
        $grid->column('replied', __('Replied'))->sortable();
        $grid->column('success', __('Success'))->sortable();
        $grid->column('reply_message', __('Reply message'))->hide();
        $grid->column('url', __('Url'))->hide();

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
        $show = new Show(School::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('name', __('Name'));
        $show->field('district', __('District'));
        $show->field('county', __('County'));
        $show->field('sub_county', __('Sub county'));
        $show->field('parish', __('Parish'));
        $show->field('address', __('Address'));
        $show->field('p_o_box', __('P o box'));
        $show->field('email', __('Email'));
        $show->field('website', __('Website'));
        $show->field('phone', __('Phone'));
        $show->field('fax', __('Fax'));
        $show->field('school_type', __('School type'));
        $show->field('service_code', __('Service code'));
        $show->field('reg_no', __('Reg no'));
        $show->field('center_no', __('Center no'));
        $show->field('operation_status', __('Operation status'));
        $show->field('founder', __('Founder'));
        $show->field('funder', __('Funder'));
        $show->field('boys_girls', __('Boys girls'));
        $show->field('day_boarding', __('Day boarding'));
        $show->field('nearest_school', __('Nearest school'));
        $show->field('nearest_school_distance', __('Nearest school distance'));
        $show->field('founding_year', __('Founding year'));
        $show->field('level', __('Level'));
        $show->field('latitude', __('Latitude'));
        $show->field('longitude', __('Longitude'));
        $show->field('highest_class', __('Highest class'));
        $show->field('access', __('Access'));
        $show->field('details', __('Details'));
        $show->field('has_email', __('Has email'));
        $show->field('has_website', __('Has website'));
        $show->field('has_phone', __('Has phone'));
        $show->field('contated', __('Contated'));
        $show->field('replied', __('Replied'));
        $show->field('success', __('Success'));
        $show->field('reply_message', __('Reply message'));
        $show->field('url', __('Url'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new School());
        $form->text('name', __('Name'))->rules('required');

        $form->image('photo', __('Photo'))
            ->uniqueName()
            ->removable();


        $form->text('district', __('District'))->rules('required');
        $form->text('parish', __('Parish'))->rules('required');
        $form->email('email', __('Email'));


        $form->radio('level', __('School Level'))
            ->options(['Nursery' => 'Nursery', 'Primary' => 'Primary', 'Secondary' => 'Secondary', 'Tertiary' => 'Tertiary', 'University' => 'University'])
            ->default('Secondary')
            ->rules('required');



        $form->radioCard('has_website', __('Advanced'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No')
            ->when('Yes', function (Form $form) {
                $form->radio('operation_status', __('Operation Status'))
                    ->options(['Open' => 'Open', 'Closed' => 'Closed'])
                    ->default('Open');
                $form->radio('boys_girls', __('Boys or Girls'))
                    ->options([
                        'Boys' => 'Boys',
                        'Girls' => 'Girls',
                        'Mixed' => 'Mixed',
                    ]);
                $form->text('reg_no', __('School Reg no'));
                $form->text('center_no', __('School Center no'));
                $form->text('address', __('School Address'));
                $form->text('p_o_box', __('P o box'));
                $form->text('phone', __('School Phone Number'));
                $form->text('website', __('Website'))->rules('required');
                $form->text('founder', __('Founder'));
                $form->radio('funder', __('Funder'))
                    ->options([
                        'Government' => 'Government',
                        'Private' => 'Private',
                        'Religious' => 'Religious',
                        'Community' => 'Community',
                        'NGO' => 'NGO',
                        'Other' => 'Other',
                    ]);
                $form->text('day_boarding', __('Day boarding'));
                $form->decimal('founding_year', __('Founding year'));
                $form->text('latitude', __('GPS Latitude'));
                $form->text('longitude', __('GPS Longitude'));
                $form->text('highest_class', __('Highest class'));
                $form->text('contated', __('Contated'))->default('No');
                $form->text('replied', __('Replied'))->default('No');
                $form->text('success', __('Success'))->default('No');
                $form->text('reply_message', __('Reply message'));
            });

        $form->quill('details', __('Details'));
        $form->multipleImage('photos', __('Photos'))
            ->removable()
            ->sortable()
            ->uniqueName();



        $form->disableReset();
        $form->disableViewCheck();



        return $form;
    }
}
