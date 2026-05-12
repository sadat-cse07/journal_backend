<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new author
     */
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'affiliation' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'orcid_id' => 'nullable|string|max:20|unique:users',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'affiliation' => $request->affiliation,
            'department' => $request->department,
            'orcid_id' => $request->orcid_id,
            'status' => 'active',
        ]);

        // Assign author role
        $user->assignRole('author');

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     */
// In the login and register methods, make sure token is properly created:

public function login(Request $request)
{
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid email or password.'],
        ]);
    }

    if (!$user->isActive()) {
        return response()->json([
            'message' => 'Account is not active.',
        ], 403);
    }

    // Create new token with abilities
    $token = $user->createToken('auth-token', ['*'])->plainTextToken;
    
    $user->update(['last_login_at' => now()]);

    // Return user with roles
    $userData = [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'full_name' => $user->full_name,
        'email' => $user->email,
        'affiliation' => $user->affiliation,
        'status' => $user->status,
        'roles' => $user->getRoleNames()->toArray(),
        'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
    ];

    return response()->json([
        'message' => 'Login successful',
        'user' => $userData,
        'token' => $token,
    ]);
}

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUserResponse($request->user()),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'affiliation' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
        ]);

        $user->update($request->only([
            'first_name', 'last_name', 'affiliation',
            'department', 'phone', 'bio'
        ]));

        return response()->json([
            'message' => 'Profile updated',
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Format user response with roles and permissions
     */
    private function formatUserResponse($user)
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'affiliation' => $user->affiliation,
            'department' => $user->department,
            'orcid_id' => $user->orcid_id,
            'phone' => $user->phone,
            'bio' => $user->bio,
            'status' => $user->status,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'reviewer_profile' => $user->reviewerProfile,
            'last_login_at' => $user->last_login_at,
        ];
    }
}