<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string|null $dni
 * @property string $name
 * @property string|null $paterno
 * @property string|null $materno
 * @property string $email
 * @property UserRole|string $role
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['dni', 'name', 'paterno', 'materno', 'email', 'role', 'phone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'role' => UserRole::class,
        ];
    }

    /**
     * Determine if user has administrator role.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin || $this->role === 'admin';
    }

    /**
     * Determine if user is docente/instructor.
     */
    public function isDocente(): bool
    {
        return $this->role === UserRole::Docente || $this->role === 'docente';
    }

    /**
     * Determine if user is participante.
     */
    public function isParticipante(): bool
    {
        return $this->role === UserRole::Participante || $this->role === 'participante';
    }

    /**
     * Matrículas del usuario como participante.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Cursos dictados por el usuario como docente.
     *
     * @return HasMany<Course, $this>
     */
    public function taughtCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Get full name including maternal and paternal surnames if present.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->name, $this->paterno, $this->materno]);

        return ! empty($parts) ? implode(' ', $parts) : $this->name;
    }
}
