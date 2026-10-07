<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $institution
 * @property string|null $description
 * @property int|null $instructor_id
 * @property string|null $instructor_name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $hours
 * @property int $total_sessions
 * @property int $min_attendance_percentage
 * @property int $capacity
 * @property CourseStatus|string $status
 * @property Carbon|null $acta_closed_at
 * @property int|null $acta_closed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $instructor
 * @property-read User|null $actaCloser
 * @property-read string $instructor_display_name
 */
#[Fillable(['code', 'title', 'institution', 'description', 'instructor_id', 'instructor_name', 'start_date', 'end_date', 'hours', 'total_sessions', 'min_attendance_percentage', 'capacity', 'status', 'acta_closed_at', 'acta_closed_by'])]
class Course extends Model
{
    use HasFactory;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['instructor_display_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'hours' => 'integer',
            'total_sessions' => 'integer',
            'min_attendance_percentage' => 'integer',
            'capacity' => 'integer',
            'status' => CourseStatus::class,
            'acta_closed_at' => 'datetime',
        ];
    }

    /**
     * Instructor assigned to the course.
     *
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Usuario que cerró oficialmente el acta.
     *
     * @return BelongsTo<User, $this>
     */
    public function actaCloser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acta_closed_by');
    }

    /**
     * Verifica si el acta oficial está cerrada.
     */
    public function isActaClosed(): bool
    {
        return ! is_null($this->acta_closed_at);
    }

    /**
     * Obtiene el nombre completo del ponente o docente a cargo.
     */
    public function getInstructorDisplayNameAttribute(): string
    {
        if ($this->instructor) {
            $parts = array_filter([$this->instructor->name, $this->instructor->paterno, $this->instructor->materno]);

            return implode(' ', $parts) ?: $this->instructor->name;
        }

        return $this->instructor_name ?: 'Ponente / Docente por asignar';
    }

    /**
     * Matrículas registradas en el curso.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Registros de asistencia por sesión.
     *
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Cantidad de vacantes restantes disponibles.
     */
    public function getAvailableSpotsAttribute(): int
    {
        $enrolled = $this->enrollments()->where('status', '!=', 'cancelado')->count();

        return max(0, $this->capacity - $enrolled);
    }

    /**
     * Determina si el curso aún tiene cupos disponibles.
     */
    public function hasAvailableSpots(): bool
    {
        return $this->available_spots > 0;
    }

    /**
     * Filter courses by search keyword and status.
     *
     * @param  Builder<Course>  $query
     * @param  array{search?: ?string, status?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function (Builder $query, string $search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        })->when($filters['status'] ?? null, function (Builder $query, string $status) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        });
    }
}
