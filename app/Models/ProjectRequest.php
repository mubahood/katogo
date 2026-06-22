<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRequest extends Model
{
    protected $fillable = [
        'name', 'whatsapp_number', 'country', 'email',
        'category', 'details', 'proposed_budget_ugx',
        'status', 'admin_notes', 'reviewed_at', 'user_id',
    ];

    protected $casts = [
        'proposed_budget_ugx' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public static array $categories = [
        'Mobile App (Android & iOS)',
        'Web Application / Website',
        'School Management System',
        'Hospital & Healthcare System',
        'Government / NGO System',
        'E-Commerce Platform',
        'Agricultural Information System',
        'Database & Data Analytics',
        'ERP / Enterprise System',
        'Other / Custom System',
    ];

    public static array $statuses = [
        'Pending',
        'Under Discussion',
        'Accepted',
        'In Progress',
        'Declined',
    ];
}
