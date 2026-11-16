<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $enrollment_id
 * @property int $session_number
 * @property string $status
 * @property string $method
 * @property Carbon|null $recorded_at
 * @property int|null $minutes_present
 * @property int|null $recorded_by
 * @property string|null $justification
 * @property int|null $justified_by
 * @property Carbon|null $justified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 * @property-read Enrollment $enrollment
 * @property-read User|null $recorder
 */
class AttendanceRecord extends Model
{
    /**
     * Estados que suman asistencia (los demás no cuentan para el porcentaje).
     *
     * @var list<string>
     */
    public const COUNTED_STATUSES = ['presente', 'tardanza'];

    /**
     * Todos los estados válidos del registro de asistencia.
     * «parcial» y «sin_salida» provienen del control de permanencia (SC-32).
     *
     * @var list<string>
     */
    public const STATUSES = ['presente', 'tardanza', 'falta', 'parcial', 'sin_salida'];

    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'enrollment_id',
        'session_number',
        'status',
        'method',
        'minutes_present',
        'recorded_at',
        'recorded_by',
        'justification',
        'justified_by',
        'justified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_number' => 'integer',
            'minutes_present' => 'integer',
            'recorded_at' => 'datetime',
            'justified_at' => 'datetime',
        ];
    }

    /**
     * Capacitación a la que pertenece la sesión.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Matrícula del participante.
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Usuario (docente o admin) que registró la asistencia.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
