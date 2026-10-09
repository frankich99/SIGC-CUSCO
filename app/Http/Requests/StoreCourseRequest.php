<?php

namespace App\Http\Requests;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->isDocente());
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        if (empty($this->code)) {
            $year = date('Y');
            $prefix = "SIGC-{$year}-";
            $latest = Course::where('code', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->value('code');

            if ($latest && preg_match('/SIGC-\d{4}-(\d+)/', $latest, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            } else {
                $nextNumber = (Course::max('id') ?: 0) + 1;
            }

            $this->merge([
                'code' => $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
            ]);
        }

        if (empty($this->instructor_name) && ! empty($this->instructor_id)) {
            $user = User::find($this->instructor_id);
            if ($user) {
                $this->merge([
                    'instructor_name' => trim("{$user->name} {$user->paterno} {$user->materno}") ?: $user->name,
                ]);
            }
        }

        if (! $this->has('total_sessions') || is_null($this->input('total_sessions'))) {
            $this->merge(['total_sessions' => 4]);
        }

        if (! $this->has('min_attendance_percentage') || is_null($this->input('min_attendance_percentage'))) {
            $this->merge(['min_attendance_percentage' => 75]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', 'unique:courses,code'],
            'title' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'instructor_id' => ['nullable'],
            'instructor_name' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'integer', 'min:1'],
            'total_sessions' => ['required', 'integer', 'min:1', 'max:200'],
            'min_attendance_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(CourseStatus::class)],
        ];
    }

    /**
     * Custom messages for validation errors in Spanish.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título de la capacitación es obligatorio.',
            'instructor_name.required' => 'Debe ingresar el nombre del ponente o docente responsable.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hours.required' => 'Las horas académicas son obligatorias.',
            'hours.min' => 'Las horas académicas deben ser mayores a 0.',
            'total_sessions.required' => 'El número de sesiones es obligatorio.',
            'total_sessions.min' => 'Debe haber al menos 1 sesión programada.',
            'min_attendance_percentage.required' => 'El porcentaje mínimo de asistencia es obligatorio.',
            'min_attendance_percentage.min' => 'El porcentaje de asistencia no puede ser menor a 0.',
            'min_attendance_percentage.max' => 'El porcentaje de asistencia no puede superar el 100%.',
            'capacity.required' => 'El límite de vacantes es obligatorio.',
            'capacity.min' => 'El límite de vacantes debe ser un número positivo mayor a 0.',
            'status.required' => 'El estado del curso es obligatorio.',
        ];
    }
}
