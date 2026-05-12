<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\ReviewerProfile;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions (without group in the array)
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'change user status',
            
            // Paper Management
            'view own papers',
            'create papers',
            'edit own papers',
            'delete own papers',
            'submit papers',
            'withdraw own papers',
            'view all papers',
            
            // Review Management
            'view assigned reviews',
            'accept review invitation',
            'submit review',
            'view all reviews',
            'assign reviewers',
            'remove reviewers',
            
            // Editorial
            'view assigned papers',
            'desk review papers',
            'make editorial decisions',
            'request revisions',
            'accept papers',
            'reject papers',
            
            // Category
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
        ];

        // Create permissions with group information
        $permissionGroups = [
            'user-management' => [
                'view users', 'create users', 'edit users', 
                'delete users', 'change user status'
            ],
            'paper-management' => [
                'view own papers', 'create papers', 'edit own papers',
                'delete own papers', 'submit papers', 'withdraw own papers',
                'view all papers'
            ],
            'review-management' => [
                'view assigned reviews', 'accept review invitation',
                'submit review', 'view all reviews', 'assign reviewers',
                'remove reviewers'
            ],
            'editorial' => [
                'view assigned papers', 'desk review papers',
                'make editorial decisions', 'request revisions',
                'accept papers', 'reject papers'
            ],
            'category-management' => [
                'view categories', 'create categories',
                'edit categories', 'delete categories'
            ],
        ];

        // Create permissions with groups
        foreach ($permissionGroups as $group => $groupPermissions) {
            foreach ($groupPermissions as $permission) {
                Permission::firstOrCreate(
                    [
                        'name' => $permission,
                        'guard_name' => 'web',
                    ],
                    [
                        'group' => $group,
                    ]
                );
            }
        }

        echo "✓ Created " . Permission::count() . " permissions\n";

        // Create Admin Role
        $admin = Role::firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web',
            ],
            [
                'description' => 'System Administrator with full access'
            ]
        );
        $admin->givePermissionTo(Permission::all());
        echo "✓ Admin role created with all permissions\n";

        // Create Editorial Role
        $editorial = Role::firstOrCreate(
            [
                'name' => 'editorial',
                'guard_name' => 'web',
            ],
            [
                'description' => 'Editorial Board Member'
            ]
        );
        $editorial->givePermissionTo([
            'view users', 'create users',
            'view all papers',
            'view all reviews', 'assign reviewers', 'remove reviewers',
            'view assigned papers', 'desk review papers', 'make editorial decisions',
            'request revisions', 'accept papers', 'reject papers',
            'view categories', 'create categories', 'edit categories',
        ]);
        echo "✓ Editorial role created\n";

        // Create Reviewer Role
        $reviewer = Role::firstOrCreate(
            [
                'name' => 'reviewer',
                'guard_name' => 'web',
            ],
            [
                'description' => 'Paper Reviewer'
            ]
        );
        $reviewer->givePermissionTo([
            'view assigned reviews',
            'accept review invitation',
            'submit review',
        ]);
        echo "✓ Reviewer role created\n";

        // Create Author Role
        $author = Role::firstOrCreate(
            [
                'name' => 'author',
                'guard_name' => 'web',
            ],
            [
                'description' => 'Paper Author'
            ]
        );
        $author->givePermissionTo([
            'view own papers',
            'create papers',
            'edit own papers',
            'delete own papers',
            'submit papers',
            'withdraw own papers',
        ]);
        echo "✓ Author role created\n";

        // Create test users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@journal.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('admin');
        echo "✓ Admin user created (admin@journal.com / password)\n";

        $editorUser = User::firstOrCreate(
            ['email' => 'editor@journal.com'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $editorUser->assignRole('editorial');
        echo "✓ Editor user created (editor@journal.com / password)\n";

        $reviewerUser = User::firstOrCreate(
            ['email' => 'reviewer@journal.com'],
            [
                'first_name' => 'David',
                'last_name' => 'Lee',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $reviewerUser->assignRole('reviewer');
        ReviewerProfile::firstOrCreate(
            ['user_id' => $reviewerUser->id],
            [
                'expertise_keywords' => ['Machine Learning', 'AI', 'Data Science'],
                'availability_status' => 'available',
                'max_reviews' => 5,
            ]
        );
        echo "✓ Reviewer user created (reviewer@journal.com / password)\n";

        $authorUser = User::firstOrCreate(
            ['email' => 'author@journal.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'password' => bcrypt('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $authorUser->assignRole('author');
        echo "✓ Author user created (author@journal.com / password)\n";

        // Cache permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        echo "\n✅ Database seeding completed successfully!\n";
    }
}