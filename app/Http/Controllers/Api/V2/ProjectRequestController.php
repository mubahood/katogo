<?php
namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ProjectRequest;
use App\Models\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectRequestController extends Controller
{
    /**
     * GET /api/v2/developer/portfolio
     * Public — returns developer profile data.
     */
    public function portfolio(): JsonResponse
    {
        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => [
                'name'       => 'Muhindo Mubaraka',
                'title'      => 'Manager, Information Systems',
                'tagline'    => 'Building enterprise systems that work in the real conditions of East Africa.',
                'location'   => 'Kampala, Uganda',
                'experience_years' => 9,
                'whatsapp'   => '+256783204665',
                'email'      => 'mubahood360@gmail.com',
                'github'     => 'https://github.com/mubahood',
                'youtube'    => 'https://youtube.com/@LearnItWithMuhindo',
                'youtube_subscribers' => '23,000+',
                'website'    => 'https://learnitwithmuhindo.com',
                'msc_research' => 'Blockchain-Based Federated Learning for Livestock Disease Early Warning Systems',
                'skills' => [
                    'Flutter / Dart', 'Laravel / PHP', 'React.js', 'Python',
                    'MySQL / PostgreSQL', 'Linux Server Admin', 'Firebase',
                    'REST API Design', 'Android (Kotlin)', 'Docker / CI-CD',
                    'GIS / GPS Integration', 'Mobile Money / Payment Gateways',
                ],
                'flagship_projects' => [
                    ['name' => 'Uganda Livestock Info System (ULITS)', 'client' => 'Ministry of Agriculture', 'url' => 'https://u-lits.com', 'tech' => 'Flutter, Laravel, MySQL, GIS', 'desc' => 'National livestock registration, movement & disease surveillance platform.'],
                    ['name' => 'School Dynamics', 'client' => 'Schools across Uganda', 'url' => 'https://schooldynamics.ug', 'tech' => 'Laravel, Flutter, Mobile Money', 'desc' => 'SaaS school management: fees, exams, timetabling, parent portal.'],
                    ['name' => 'Hospital Management System', 'client' => 'Global Health Rescue', 'url' => 'https://globalhealthrescue.com', 'tech' => 'Laravel, Flutter, EHR', 'desc' => 'End-to-end patient records, pharmacy, billing, insurance claims.'],
                    ['name' => 'Wildlife Offenders DB', 'client' => 'Uganda Wildlife Authority', 'url' => null, 'tech' => 'Laravel, Flutter, Biometrics', 'desc' => 'Wildlife crime tracking, offender database, enforcement analytics.'],
                    ['name' => 'National Seed Tracking System', 'client' => 'Ministry of Agriculture', 'url' => null, 'tech' => 'Laravel, QR/Barcode, API', 'desc' => 'Seed distribution monitoring with barcode/QR field verification.'],
                    ['name' => 'LugaFlix', 'client' => 'Personal Product', 'url' => 'https://lugaflix.store', 'tech' => 'Flutter, Laravel, Firebase', 'desc' => 'Luganda-language movie streaming app for Android TV, tablets & phones.'],
                ],
                'categories' => ProjectRequest::$categories,
            ],
        ]);
    }

    /**
     * POST /api/v2/developer/request
     * Public — submit a project request (no auth required).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required|string|max:120',
            'whatsapp_number'      => 'required|string|max:30',
            'country'              => 'required|string|max:80',
            'email'                => 'nullable|email|max:120',
            'category'             => 'required|string|max:100',
            'details'              => 'required|string|min:30|max:3000',
            'proposed_budget_ugx'  => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json([
                'code'    => 0,
                'message' => $errors[0],
                'errors'  => $errors,
            ], 422);
        }

        // Throttle: max 3 submissions per whatsapp/email per day
        $ipCount = ProjectRequest::where('created_at', '>=', now()->subDay())
            ->where(function ($q) use ($request) {
                $q->where('whatsapp_number', $request->input('whatsapp_number'))
                  ->orWhere('email', $request->input('email') ?: 'NOEMAIL_' . time());
            })
            ->count();

        if ($ipCount >= 3) {
            return response()->json([
                'code'    => 0,
                'message' => 'You have submitted too many requests today. Please wait 24 hours.',
            ], 429);
        }

        $userId = null;
        try {
            $user = Utils::get_user($request);
            $userId = $user?->id;
        } catch (\Throwable $_) {}

        $pr = ProjectRequest::create([
            'name'                => trim($request->input('name')),
            'whatsapp_number'     => trim($request->input('whatsapp_number')),
            'country'             => trim($request->input('country')),
            'email'               => trim($request->input('email', '')),
            'category'            => trim($request->input('category')),
            'details'             => trim($request->input('details')),
            'proposed_budget_ugx' => (int) $request->input('proposed_budget_ugx', 0),
            'status'              => 'Pending',
            'user_id'             => $userId,
        ]);

        return response()->json([
            'code'    => 1,
            'message' => 'Request submitted successfully! Muhindo will contact you via WhatsApp or email within 48 hours.',
            'data'    => ['id' => $pr->id, 'status' => $pr->status],
        ], 201);
    }

    /**
     * GET /api/v2/admin/project-requests
     * Admin — list all requests, newest first.
     */
    public function adminList(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $status = $request->input('status');
        $query  = ProjectRequest::orderByDesc('id');
        if ($status) $query->where('status', $status);

        $requests = $query->limit(100)->get();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => $requests->map(fn($r) => $this->format($r)),
        ]);
    }

    /**
     * GET /api/v2/admin/project-requests/{id}
     * Admin — get single request.
     */
    public function adminShow(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $r = ProjectRequest::find($id);
        if (!$r) return response()->json(['code' => 0, 'message' => 'Not found'], 404);

        return response()->json(['code' => 1, 'message' => 'ok', 'data' => $this->format($r)]);
    }

    /**
     * POST /api/v2/admin/project-requests/{id}/status
     * Admin — update status + admin notes.
     */
    public function adminUpdateStatus(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $r = ProjectRequest::find($id);
        if (!$r) return response()->json(['code' => 0, 'message' => 'Not found'], 404);

        $status = trim((string) $request->input('status', $r->status));
        if (!in_array($status, ProjectRequest::$statuses, true)) {
            return response()->json(['code' => 0, 'message' => 'Invalid status'], 422);
        }

        $r->status       = $status;
        $r->admin_notes  = trim((string) $request->input('admin_notes', $r->admin_notes ?? '')) ?: null;
        $r->reviewed_at  = $r->reviewed_at ?? now();
        $r->save();

        return response()->json([
            'code'    => 1,
            'message' => "Status updated to $status.",
            'data'    => $this->format($r),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function checkAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user() ?? Utils::get_user($request);
        if (!$user) return response()->json(['code' => 0, 'message' => 'Auth required'], 401);
        if ((int) $user->id !== 1) return response()->json(['code' => 0, 'message' => 'Admin only'], 403);
        return null;
    }

    private function format(ProjectRequest $r): array
    {
        return [
            'id'                   => $r->id,
            'name'                 => $r->name,
            'whatsapp_number'      => $r->whatsapp_number,
            'country'              => $r->country,
            'email'                => $r->email ?? '',
            'category'             => $r->category,
            'details'              => $r->details,
            'proposed_budget_ugx'  => $r->proposed_budget_ugx,
            'status'               => $r->status,
            'admin_notes'          => $r->admin_notes ?? '',
            'reviewed_at'          => (string) $r->reviewed_at,
            'created_at'           => (string) $r->created_at,
            'user_id'              => $r->user_id,
        ];
    }
}
