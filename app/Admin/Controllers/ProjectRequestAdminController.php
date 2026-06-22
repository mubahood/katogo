<?php

namespace App\Admin\Controllers;

use App\Models\ProjectRequest;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Illuminate\Support\Facades\DB;

class ProjectRequestAdminController extends AdminController
{
    protected $title = 'Project Requests';

    // ── INDEX ────────────────────────────────────────────────────────────

    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description('People who contacted you about building something')
            ->body($this->summary())
            ->body($this->grid());
    }

    // ── SUMMARY STATS ────────────────────────────────────────────────────

    protected function summary()
    {
        $total     = ProjectRequest::count();
        $pending   = ProjectRequest::where('status', 'Pending')->count();
        $active    = ProjectRequest::whereIn('status', ['Under Discussion', 'Accepted', 'In Progress'])->count();
        $declined  = ProjectRequest::where('status', 'Declined')->count();
        $thisWeek  = ProjectRequest::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        $totalBudget = ProjectRequest::whereIn('status', ['Accepted', 'In Progress'])
            ->sum('proposed_budget_ugx');

        $html = <<<HTML
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
  <div style="flex:1;min-width:130px;background:#1a1a2e;border:1px solid #FFD700;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:28px;font-weight:900;color:#FFD700;">$total</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">TOTAL REQUESTS</div>
  </div>
  <div style="flex:1;min-width:130px;background:#1a1a2e;border:1px solid #f39c12;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:28px;font-weight:900;color:#f39c12;">$pending</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">PENDING REVIEW</div>
  </div>
  <div style="flex:1;min-width:130px;background:#1a1a2e;border:1px solid #27ae60;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:28px;font-weight:900;color:#27ae60;">$active</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">ACTIVE / IN PROGRESS</div>
  </div>
  <div style="flex:1;min-width:130px;background:#1a1a2e;border:1px solid #e74c3c;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:28px;font-weight:900;color:#e74c3c;">$declined</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">DECLINED</div>
  </div>
  <div style="flex:1;min-width:130px;background:#1a1a2e;border:1px solid #3498db;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:28px;font-weight:900;color:#3498db;">$thisWeek</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">THIS WEEK</div>
  </div>
  <div style="flex:1;min-width:160px;background:#1a1a2e;border:1px solid #9b59b6;border-radius:10px;padding:16px 20px;text-align:center;">
    <div style="font-size:18px;font-weight:900;color:#9b59b6;">UGX&nbsp;{$this->formatUGX($totalBudget)}</div>
    <div style="color:#aaa;font-size:12px;margin-top:4px;">BUDGET (ACCEPTED)</div>
  </div>
</div>
HTML;

        return new Box('Overview', $html);
    }

    // ── GRID ─────────────────────────────────────────────────────────────

    protected function grid()
    {
        $grid = new Grid(new ProjectRequest());

        $grid->model()->orderByDesc('id');

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('status', 'Status')->select(
                array_combine(ProjectRequest::$statuses, ProjectRequest::$statuses)
            );
            $filter->equal('category', 'Category')->select(
                array_combine(ProjectRequest::$categories, ProjectRequest::$categories)
            );
            $filter->like('name', 'Name');
            $filter->like('whatsapp_number', 'WhatsApp');
            $filter->like('country', 'Country');
            $filter->between('created_at', 'Submitted')->datetime();
        });

        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->perPages([20, 50, 100]);

        // Columns
        $grid->column('id', 'ID')->sortable()->width(60);

        $grid->column('status', 'Status')->display(function ($status) {
            $colors = [
                'Pending'          => '#f39c12',
                'Under Discussion' => '#3498db',
                'Accepted'         => '#27ae60',
                'In Progress'      => '#9b59b6',
                'Declined'         => '#e74c3c',
            ];
            $color = $colors[$status] ?? '#888';
            return "<span style='background:{$color};color:#fff;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;'>{$status}</span>";
        })->width(140);

        $grid->column('name', 'Name')->display(function ($name) {
            return "<strong style='color:#fff;'>{$name}</strong>";
        })->width(150);

        $grid->column('whatsapp_number', 'WhatsApp')->display(function ($num) {
            return "<a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $num) . "' target='_blank' style='color:#25D366;font-weight:600;'>{$num}</a>";
        })->width(140);

        $grid->column('country', 'Country')->width(100);

        $grid->column('category', 'Category')->display(function ($cat) {
            return "<span style='background:#1a1a2e;border:1px solid #FFD700;color:#FFD700;padding:2px 8px;border-radius:12px;font-size:11px;'>{$cat}</span>";
        })->width(200);

        $grid->column('proposed_budget_ugx', 'Budget (UGX)')->display(function ($amount) {
            if (!$amount) return '<span style="color:#888">—</span>';
            return '<span style="color:#FFD700;font-weight:700;">UGX ' . number_format($amount) . '</span>';
        })->sortable()->width(150);

        $grid->column('details', 'Details')->display(function ($text) {
            $short = mb_substr($text, 0, 80);
            if (mb_strlen($text) > 80) $short .= '…';
            return "<span style='color:#ccc;font-size:12px;'>{$short}</span>";
        });

        $grid->column('created_at', 'Submitted')->display(function ($date) {
            $diff = Carbon::parse($date)->diffForHumans();
            $fmt  = Carbon::parse($date)->format('M d, Y');
            return "<span title='{$fmt}' style='color:#aaa;font-size:12px;'>{$diff}</span>";
        })->sortable()->width(120);

        return $grid;
    }

    // ── SHOW ─────────────────────────────────────────────────────────────

    public function show($id, Content $content)
    {
        $r = ProjectRequest::findOrFail($id);

        $statusColor = [
            'Pending'          => '#f39c12',
            'Under Discussion' => '#3498db',
            'Accepted'         => '#27ae60',
            'In Progress'      => '#9b59b6',
            'Declined'         => '#e74c3c',
        ][$r->status] ?? '#888';

        $waNumber = preg_replace('/[^0-9]/', '', $r->whatsapp_number);
        $budget   = $r->proposed_budget_ugx ? 'UGX ' . number_format($r->proposed_budget_ugx) : 'Not specified';
        $reviewed = $r->reviewed_at ? Carbon::parse($r->reviewed_at)->format('M d, Y H:i') : 'Not yet reviewed';
        $submitted = Carbon::parse($r->created_at)->format('M d, Y H:i');
        $adminNotes = e($r->admin_notes ?? '—');
        $details   = nl2br(e($r->details));

        $html = <<<HTML
<div style="background:#0d0d1a;border:1px solid #333;border-radius:12px;padding:24px;color:#eee;font-family:sans-serif;">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="margin:0;color:#FFD700;font-size:22px;">{$r->name}</h2>
      <div style="color:#aaa;margin-top:4px;">{$r->country} &nbsp;·&nbsp; Submitted {$submitted}</div>
    </div>
    <span style="background:{$statusColor};color:#fff;padding:6px 18px;border-radius:20px;font-size:13px;font-weight:700;">{$r->status}</span>
  </div>

  <!-- Contact -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
    <div style="background:#1a1a2e;border-radius:8px;padding:14px;">
      <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:6px;">WhatsApp</div>
      <a href="https://wa.me/{$waNumber}" target="_blank" style="color:#25D366;font-size:16px;font-weight:700;text-decoration:none;">📱 {$r->whatsapp_number}</a>
    </div>
    <div style="background:#1a1a2e;border-radius:8px;padding:14px;">
      <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:6px;">Email</div>
      <a href="mailto:{$r->email}" style="color:#3498db;font-size:15px;font-weight:600;text-decoration:none;">✉️ {$r->email}</a>
    </div>
    <div style="background:#1a1a2e;border-radius:8px;padding:14px;">
      <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:6px;">Category</div>
      <div style="color:#FFD700;font-size:14px;font-weight:600;">{$r->category}</div>
    </div>
    <div style="background:#1a1a2e;border-radius:8px;padding:14px;">
      <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:6px;">Proposed Budget</div>
      <div style="color:#FFD700;font-size:16px;font-weight:700;">{$budget}</div>
    </div>
  </div>

  <!-- Details -->
  <div style="background:#1a1a2e;border-radius:8px;padding:16px;margin-bottom:20px;">
    <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:10px;">Project Details</div>
    <div style="color:#ddd;line-height:1.7;font-size:14px;">{$details}</div>
  </div>

  <!-- Admin Notes -->
  <div style="background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:16px;margin-bottom:20px;">
    <div style="color:#888;font-size:11px;text-transform:uppercase;margin-bottom:8px;">Admin Notes</div>
    <div style="color:#ccc;font-size:13px;font-style:italic;">{$adminNotes}</div>
    <div style="margin-top:8px;color:#666;font-size:11px;">Reviewed: {$reviewed}</div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="https://wa.me/{$waNumber}?text=Hi%20{$r->name}%2C%20I%20received%20your%20project%20request%20about%20{$r->category}." target="_blank"
       style="background:#25D366;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;">
      💬 WhatsApp {$r->name}
    </a>
    <a href="/admin/project-requests/{$id}/edit"
       style="background:#FFD700;color:#000;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;">
      ✏️ Update Status
    </a>
  </div>

</div>
HTML;

        return $content
            ->title('Project Request — ' . $r->name)
            ->description($r->category)
            ->body(new Box('Request Details', $html));
    }

    // ── FORM (edit status + notes only) ──────────────────────────────────

    protected function form()
    {
        $form = new Form(new ProjectRequest());

        $form->display('name', 'Name');
        $form->display('whatsapp_number', 'WhatsApp');
        $form->display('email', 'Email');
        $form->display('country', 'Country');
        $form->display('category', 'Category');
        $form->display('proposed_budget_ugx', 'Budget (UGX)');
        $form->display('created_at', 'Submitted At');

        $form->divider('Review');

        $form->select('status', 'Status')
            ->options(array_combine(ProjectRequest::$statuses, ProjectRequest::$statuses))
            ->required();

        $form->textarea('admin_notes', 'Admin Notes')
            ->placeholder('Notes about this request (only visible to you)...')
            ->rows(4);

        $form->saving(function (Form $form) {
            $form->model()->reviewed_at = now();
        });

        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();

        return $form;
    }

    // ── HELPERS ──────────────────────────────────────────────────────────

    private function formatUGX(int $amount): string
    {
        return number_format($amount);
    }
}
