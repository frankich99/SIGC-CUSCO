<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * DNI escaneado en el kiosco que no pertenece a la sesión (lista «No inscritos», SC-32).
 *
 * @property int $id
 * @property int $course_id
 * @property int $session_number
 * @property string $dni
 * @property string $type
 * @property string $reason
 * @property Carbon $scanned_at
 * @property int|null $recorded_by
 * @property string|null $client_uuid UUID generado por el kiosco sin conexión (SC-51).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 */
class AttendanceUnenrolledScan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'session_number',
        'dni',
        'type',
        'reason',
        'scanned_at',
        'recorded_by',
        'client_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_number' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
