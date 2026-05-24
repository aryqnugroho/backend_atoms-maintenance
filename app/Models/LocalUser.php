<?php

namespace App\Models;

use App\Models\WorkOrder\WorkOrder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LocalUser extends Authenticatable
{
    use SoftDeletes, Notifiable;

    protected $table = 'local_users';

    protected $fillable = [
        'rostering_user_id',
        'name',
        'email',
        'role',
        'division',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    // ─── Role Helper Methods ───────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'Manager Teknik';
    }

    public function isGeneralManager(): bool
    {
        return $this->role === 'General Manager';
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, ['Supervisor CNSD', 'Supervisor TFP']);
    }

    public function isSupervisorCnsd(): bool
    {
        return $this->role === 'Supervisor CNSD';
    }

    public function isSupervisorTfp(): bool
    {
        return $this->role === 'Supervisor TFP';
    }

    public function isTeknisi(): bool
    {
        return in_array($this->role, ['Teknisi CNSD', 'Teknisi TFP']);
    }

    public function isTeknisiCnsd(): bool
    {
        return $this->role === 'Teknisi CNSD';
    }

    public function isTeknisiTfp(): bool
    {
        return $this->role === 'Teknisi TFP';
    }

    /**
     * Get the division this user's role is associated with.
     */
    public function getRoleDivision(): ?string
    {
        if (str_contains($this->role, 'CNSD')) {
            return 'CNSD';
        }
        if (str_contains($this->role, 'TFP')) {
            return 'TFP';
        }
        return null; // Admin, Manager Teknik, General Manager — all divisions
    }

    // ─── Relationships ─────────────────────────────────────────

    public function createdWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'created_by');
    }

    public function managedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'manager_id');
    }

    public function supervisedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'supervisor_id');
    }
}
