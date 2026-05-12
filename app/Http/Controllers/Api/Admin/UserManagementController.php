<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ReviewerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * List all users
     */
    public function index(Request $request)
    {
        $users = User::with(['roles', 'reviewerProfile'])
            ->when($request->role, function($q) use ($request) {
                $q->role($request->role);
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('first_name', 'like', "%{$request->search}%")
                          ->orWhere('last_name', 'like', "%{$request->search}%")
                          ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'message' => 'Users retrieved',
            'users' => $users,
        ]);
    }

    /**
     * Create new user
     */
public function store(Request $request)
{
    $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8',
        'role' => ['required', Rule::in(['editorial', 'reviewer', 'admin'])],
        'affiliation' => 'nullable|string|max:255',
        'department' => 'nullable|string|max:255',
        'expertise_keywords' => 'nullable|array|required_if:role,reviewer',
    ]);

    try {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'affiliation' => $request->affiliation,
            'department' => $request->department,
            'status' => 'active',
            'created_by' => $request->user()->id,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($request->role);

        if ($request->role === 'reviewer') {
            ReviewerProfile::create([
                'user_id' => $user->id,
                'expertise_keywords' => $request->expertise_keywords ?? [],
                'availability_status' => 'available',
            ]);
        }

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->fresh()->load(['roles', 'reviewerProfile']),
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error creating user',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Show user details
     */
    public function show($id)
    {
        $user = User::with(['roles', 'reviewerProfile', 'createdBy'])->findOrFail($id);

        return response()->json([
            'message' => 'User details retrieved',
            'user' => $user,
        ]);
    }

    /**
     * Update user
     */
public function update(Request $request, $id)
{
    $request->validate([
        'first_name' => 'sometimes|string|max:100',
        'last_name' => 'sometimes|string|max:100',
        'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($id)],
        'affiliation' => 'nullable|string|max:255',
        'department' => 'nullable|string|max:255',
        'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        'role' => ['sometimes', Rule::in(['author', 'editorial', 'reviewer', 'admin'])],
        'expertise_keywords' => 'nullable|array',
    ]);

    $user = User::findOrFail($id);

    try {
        $user->update($request->only([
            'first_name', 'last_name', 'email',
            'affiliation', 'department', 'status'
        ]));

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
            
            if ($request->role === 'reviewer') {
                ReviewerProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['expertise_keywords' => $request->expertise_keywords ?? []]
                );
            } else {
                $user->reviewerProfile()->delete();
            }
        }

        return response()->json([
            'message' => 'User updated',
            'user' => $user->fresh()->load(['roles', 'reviewerProfile']),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error updating user',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Delete user
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->id == $id) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User deleted',
        ]);
    }

    /**
     * Get system stats
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_papers' => \App\Models\Paper::count(),
            'papers_under_review' => \App\Models\Paper::where('status', 'under_review')->count(),
            'papers_accepted' => \App\Models\Paper::where('status', 'accepted')->count(),
            'total_reviews' => \App\Models\Review::where('status', 'completed')->count(),
            'users_by_role' => [
                'authors' => User::role('author')->count(),
                'editorials' => User::role('editorial')->count(),
                'reviewers' => User::role('reviewer')->count(),
                'admins' => User::role('admin')->count(),
            ],
        ];

        return response()->json([
            'message' => 'Dashboard stats',
            'stats' => $stats,
        ]);
    }

    /**
     * Get all roles
     */
    public function roles()
    {
        $roles = Role::all();
        
        return response()->json([
            'roles' => $roles,
        ]);
    }
}