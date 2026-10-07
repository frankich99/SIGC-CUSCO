<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'user_id',
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attended_sessions' => 'integer',
            'final_grade' => 'decimal:2',
        ];
    }

    /**
     * Curso al que corresponde la matrícula.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Usuario vinculado en la plataforma.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nombre completo del participante.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->nombres} {$this->paterno} {$this->materno}");
    }
}
