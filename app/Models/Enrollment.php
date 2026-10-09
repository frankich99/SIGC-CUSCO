<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int|null $user_id
 * @property string|null $credential_code
 * @property string $dni
 * @property string $nombres
 * @property string $paterno
 * @property string|null $materno
 * @property string $email
 * @property string|null $phone
 * @property string $status
 * @property int $attended_sessions
 * @property string|float|null $final_grade
 * @property string|null $certificate_code
 * @property string|null $certificate_hash
 * @property Carbon|null $certificate_issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 * @property-read User|null $user
 * @property-read string $full_name
 * @property-read float $attendance_percentage
 */
class Enrollment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'user_id',
        'credential_code',
        'dni',
        'nombres',
        'paterno',
        'materno',
        'email',
        'phone',
        'status',
        'attended_sessions',
        'final_grade',
        'certificate_code',
        'certificate_hash',
        'certificate_issued_at',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['full_name', 'attendance_percentage'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attended_sessions' => 'integer',
            'final_grade' => 'decimal:2',
            'certificate_issued_at' => 'datetime',
        ];
    }

    /**
     * Auto-generar código de credencial único si no existe al crear.
     */
    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment) {
            if (empty($enrollment->credential_code)) {
                $suffix = substr($enrollment->dni, -4) ?: rand(1000, 9999);
                $enrollment->credential_code = "INS-{$enrollment->course_id}-{$suffix}";
            }
        });
    }

    /**
     * Obtener código de credencial garantizando valor para registros antiguos.
     */
    public function getCredentialCodeAttribute(?string $value): string
    {
        if (! empty($value)) {
            return $value;
        }

        $suffix = substr($this->dni, -4) ?: '0000';

        return "INS-{$this->course_id}-{$suffix}";
    }

    /**
     * Curso al que corresponde la matrícula.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Usuario vinculado en la plataforma.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Historial detallado de asistencias por sesión.
     *
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Nombre completo del participante.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->nombres} {$this->paterno} {$this->materno}");
    }

    /**
     * Porcentaje de asistencia calculado respecto a las sesiones del curso.
     */
    public function getAttendancePercentageAttribute(): float
    {
        $totalSessions = $this->course ? $this->course->total_sessions : 4;
        if ($totalSessions <= 0) {
            return 0.0;
        }

        return round(($this->attended_sessions / $totalSessions) * 100, 1);
    }

    /**
     * Determina si el participante cumple los requisitos de aprobación UNSAAC:
     * Asistencia >= 75% Y Nota Final >= 11.00
     */
    public function meetsPassingCriteria(): bool
    {
        $minAttendance = $this->course ? $this->course->min_attendance_percentage : 75;
        $grade = ! is_null($this->final_grade) ? (float) $this->final_grade : 0.0;

        return $this->attendance_percentage >= $minAttendance && $grade >= 11.0;
    }
}
