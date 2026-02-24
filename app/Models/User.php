<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'organization',
        'warehouse_id',
        'facility_id',
        'password',
        'title',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permission_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Temporarily disabled to prevent memory issues
        // static::addGlobalScope('organization', function (Builder $builder) {
        //     // Only apply organization filter if user is authenticated and has organization
        //     if (Auth::check() && Auth::user() && Auth::user()->organization) {
        //         $builder->where('organization', Auth::user()->organization);
        //     }
        // });
    }

    /**
     * Get the warehouse that the user belongs to.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the trusted devices for the user.
     */
    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * Get the permissions for the user.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
                    ->withTimestamps();
    }

    /**
     * Get the roles assigned to the user (Spatie model_has_roles).
     */
    public function roles()
    {
        $table = config('permission.table_names.model_has_roles', 'model_has_roles');
        return $this->morphToMany(Role::class, 'model', $table);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission($permission)
    {
        // Admin users have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        // Manager System permission grants full access
        if ($this->permissions()->where('name', 'manager-system')->exists()) {
            return true;
        }

        // Regular permission check
        if (is_string($permission)) {
            return $this->permissions()->where('name', $permission)->exists();
        }

        if (is_object($permission)) {
            return $this->permissions()->where('id', $permission->id)->exists();
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission($permissions)
    {
        // Admin users have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        // Manager System permission grants full access
        if ($this->permissions()->where('name', 'manager-system')->exists()) {
            return true;
        }

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        return $this->permissions()->whereIn('name', $permissions)->exists();
    }

    /**
     * Assign a permission to the user.
     */
    public function givePermissionTo($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if ($permission && !$this->hasPermission($permission)) {
            $this->permissions()->attach($permission->id);
        }

        return $this;
    }

    /**
     * Remove a permission from the user.
     */
    public function revokePermissionTo($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if ($permission && $this->hasPermission($permission)) {
            $this->permissions()->detach($permission->id);
        }

        return $this;
    }

    /**
     * Get all permissions for the user.
     * This method provides compatibility with Spatie package.
     */
    public function getAllPermissions()
    {
        return $this->permissions;
    }

    /**
     * Check if user has a specific permission (Spatie compatibility).
     */
    public function hasPermissionTo($permission)
    {
        return $this->hasPermission($permission);
    }



    /**
     * Check if user is a system administrator.
     */
    public function isAdmin()
    {
        // Check if user has manage-system permission (highest authority)
        if ($this->permissions()->where('name', 'manage-system')->exists()) {
            return true;
        }
        
        // Check if user has admin-access permission
        if ($this->permissions()->where('name', 'admin-access')->exists()) {
            return true;
        }
        
        // Legacy check for admin username (can be removed if not needed)
        if (in_array($this->username, ['admin', 'administrator'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Scope: users who have the given permission or are admins (so they can receive "next action" emails).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $permissionName  e.g. 'purchase-order-review', 'purchase-order-approve'
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermission(Builder $query, string $permissionName): Builder
    {
        return $query->where(function (Builder $q) use ($permissionName) {
            $q->whereHas('permissions', fn (Builder $p) => $p->where('name', $permissionName))
                ->orWhereHas('permissions', fn (Builder $p) => $p->whereIn('name', ['manage-system', 'manager-system', 'admin-access']))
                ->orWhereIn('username', ['admin', 'administrator']);
        });
    }
}
