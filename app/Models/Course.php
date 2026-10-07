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
 * @property string|null $description
 * @property int|null $instructor_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $hours
 * @property int $capacity
 * @property CourseStatus|string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $instructor
 */
#[Fillable(['code', 'title', 'institution', 'description', 'instructor_id', 'start_date', 'end_date', 'hours', 'capacity', 'status'])]
class Course extends Model
{
    use HasFactory;

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
            'capacity' => 'integer',
            'status' => CourseStatus::class,
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
     * Matrículas registradas en el curso.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
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
