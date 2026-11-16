<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marca de ingreso o salida de un participante en una sesión (SC-32).
 *
 * @property int $id
 * @property int $course_id
 * @property int $enrollment_id
 * @property int $session_number
 * @property string $type
 * @property string $origin
 * @property Carbon $marked_at
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 * @property-read Enrollment $enrollment
 */
class AttendanceMark extends Model
{
    public const TYPE_ENTRY = 'entrada';

    public const TYPE_EXIT = 'salida';

    public const ORIGIN_DNI_READER = 'dni_lector';

    public const ORIGIN_QR = 'qr';

    public const ORIGIN_MANUAL = 'manual';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'enrollment_id',
        'session_number',
        'type',
        'origin',
        'marked_at',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_number' => 'integer',
            'marked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
