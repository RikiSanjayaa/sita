<?php

namespace App\Models;

use App\Enums\AppRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_active_role',
        'browser_notifications_enabled',
        'notification_preferences',
    ];

    public const NOTIFICATION_PREFERENCE_KEYS = [
        'pesanBaru',
        'statusTugasAkhir',
        'jadwalBimbingan',
        'feedbackDokumen',
        'reminderDeadline',
        'pengumumanSistem',
        'konfirmasiBimbingan',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            'browser_notifications_enabled' => 'bool',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function resolvedNotificationPreferences(): array
    {
        $storedPreferences = is_array($this->notification_preferences)
            ? $this->notification_preferences
            : [];

        $preferences = [];

        foreach (self::NOTIFICATION_PREFERENCE_KEYS as $key) {
            $preferences[$key] = true;
        }

        foreach ($storedPreferences as $key => $value) {
            if (in_array($key, self::NOTIFICATION_PREFERENCE_KEYS, true)) {
                $preferences[$key] = (bool) $value;
            }
        }

        return $preferences;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function mahasiswaProfile(): HasOne
    {
        return $this->hasOne(MahasiswaProfile::class);
    }

    public function dosenProfile(): HasOne
    {
        return $this->hasOne(DosenProfile::class);
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * Returns the admin's program studi ID for scoping.
     * Returns null for super_admin (sees all data).
     */
    public function adminProgramStudiId(): ?int
    {
        if ($this->hasRole(AppRole::SuperAdmin)) {
            return null;
        }

        return $this->adminProfile?->program_studi_id;
    }

    public function mentorshipAssignmentsAsStudent(): HasMany
    {
        return $this->hasMany(MentorshipAssignment::class, 'student_user_id');
    }

    public function mentorshipAssignmentsAsLecturer(): HasMany
    {
        return $this->hasMany(MentorshipAssignment::class, 'lecturer_user_id');
    }

    public function mentorshipSchedulesAsStudent(): HasMany
    {
        return $this->hasMany(MentorshipSchedule::class, 'student_user_id');
    }

    public function mentorshipSchedulesAsLecturer(): HasMany
    {
        return $this->hasMany(MentorshipSchedule::class, 'lecturer_user_id');
    }

    public function mentorshipDocumentsAsStudent(): HasMany
    {
        return $this->hasMany(MentorshipDocument::class, 'student_user_id');
    }

    public function mentorshipDocumentsAsLecturer(): HasMany
    {
        return $this->hasMany(MentorshipDocument::class, 'lecturer_user_id');
    }

    public function mentorshipChatThreadAsStudent(): HasOne
    {
        return $this->hasOne(MentorshipChatThread::class, 'student_user_id');
    }

    public function mentorshipChatMessages(): HasMany
    {
        return $this->hasMany(MentorshipChatMessage::class, 'sender_user_id');
    }

    public function thesisSubmissions(): HasMany
    {
        return $this->hasMany(ThesisSubmission::class, 'student_user_id');
    }

    public function approvedThesisSubmissions(): HasMany
    {
        return $this->hasMany(ThesisSubmission::class, 'approved_by');
    }

    public function semproApprovals(): HasMany
    {
        return $this->hasMany(Sempro::class, 'approved_by');
    }

    public function createdSempros(): HasMany
    {
        return $this->hasMany(Sempro::class, 'created_by');
    }

    public function semproExaminerAssignments(): HasMany
    {
        return $this->hasMany(SemproExaminer::class, 'examiner_user_id');
    }

    public function semproRevisionRequests(): HasMany
    {
        return $this->hasMany(SemproRevision::class, 'requested_by_user_id');
    }

    public function resolvedSemproRevisions(): HasMany
    {
        return $this->hasMany(SemproRevision::class, 'resolved_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->all()
            : $this->roles()->pluck('name')->all();

        if (count($roles) > 0) {
            return array_values(array_unique($roles));
        }

        return [AppRole::Mahasiswa->value];
    }

    public function hasRole(AppRole|string $role): bool
    {
        $roleValue = $role instanceof AppRole ? $role->value : $role;

        return in_array($roleValue, $this->roleNames(), true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function availableRoles(): array
    {
        return array_values(array_intersect(
            $this->roleNames(),
            AppRole::uiValues(),
        ));
    }

    public function resolveActiveRole(?string $requestedRole = null): string
    {
        $availableRoles = $this->availableRoles();

        if (count($availableRoles) === 0) {
            return AppRole::Mahasiswa->value;
        }

        if ($requestedRole !== null && in_array($requestedRole, $availableRoles, true)) {
            return $requestedRole;
        }

        if ($this->last_active_role !== null && in_array($this->last_active_role, $availableRoles, true)) {
            return $this->last_active_role;
        }

        return $availableRoles[0];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(AppRole::Admin) || $this->hasRole(AppRole::SuperAdmin);
    }
}
