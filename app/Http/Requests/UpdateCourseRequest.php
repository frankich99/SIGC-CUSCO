<?php

namespace App\Http\Requests;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Course|null $course */
        $course = $this->route('course');

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($course?->id)],
            'title' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'instructor_id' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'integer', 'min:1'],
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
            'instructor_id.required' => 'Debe asignar un docente o instructor responsable.',
            'instructor_id.exists' => 'El docente seleccionado no existe en el sistema.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hours.required' => 'Las horas académicas son obligatorias.',
            'hours.min' => 'Las horas académicas deben ser mayores a 0.',
            'capacity.required' => 'El límite de vacantes es obligatorio.',
            'capacity.min' => 'El límite de vacantes debe ser un número positivo mayor a 0.',
            'status.required' => 'El estado del curso es obligatorio.',
        ];
    }
}
