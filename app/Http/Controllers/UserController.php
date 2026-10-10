<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Directorio y Administración de Usuarios, Roles y Permisos.
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403, 'Acceso denegado. Se requieren privilegios de Administrador para gestionar roles y permisos.');
        }

        $search = trim((string) $request->input('search', ''));
        $selectedRole = (string) $request->input('role', 'all');

        $usersQuery = User::query()
            ->withCount(['enrollments', 'taughtCourses']);

        if ($search !== '') {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('paterno', 'like', "%{$search}%")
                    ->orWhere('materno', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        if ($selectedRole !== 'all' && in_array($selectedRole, ['admin', 'docente', 'participante'], true)) {
            $usersQuery->where('role', $selectedRole);
        }

        $users = $usersQuery->latest('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'docentes' => User::where('role', 'docente')->count(),
            'participantes' => User::where('role', 'participante')->count(),
        ];

        // Matriz descriptiva de capacidades y permisos por rol (SIGC-CUSCO)
        $permissionsMatrix = [
            [
                'modulo' => 'Capacitaciones y Cursos',
                'descripcion' => 'Creación, edición de contenidos, temarios y aforos de capacitaciones.',
                'admin' => 'Acceso Total (Crear, editar y eliminar cualquier curso)',
                'docente' => 'Gestionar solo los cursos asignados como instructor',
                'participante' => 'Ver catálogo público e informarse sobre temarios',
            ],
            [
                'modulo' => 'Matrícula y Padrón',
                'descripcion' => 'Inscripción de postulantes, control de vacantes y padrón oficial.',
                'admin' => 'Matricular, editar datos, retirar y gestionar participantes',
                'docente' => 'Visualizar y consultar el padrón de matriculados en sus cursos',
                'participante' => 'Inscribirse directamente con DNI en cursos abiertos',
            ],
            [
                'modulo' => 'Control de Asistencia QR',
                'descripcion' => 'Proyección de QR de sesión, marcación manual y sincronización offline.',
                'admin' => 'Supervisar y registrar asistencias en cualquier capacitación',
                'docente' => 'Proyectar QR dinámico y marcar asistencias en sus clases',
                'participante' => 'Presentar Credencial QR para ser escaneado por el docente',
            ],
            [
                'modulo' => 'Cierre de Actas y Notas',
                'descripcion' => 'Registro de notas vigesimales (0-20) y cierre definitivo según regla UNSAAC.',
                'admin' => 'Auditar, reaperturar y cerrar actas con regla del 75% de asistencias',
                'docente' => 'Ingresar notas finales y cerrar el acta oficial de su curso',
                'participante' => 'Visualizar su porcentaje de asistencia y nota obtenida',
            ],
            [
                'modulo' => 'Certificación Criptográfica',
                'descripcion' => 'Emisión de certificados digitales con código único y hash SHA-256.',
                'admin' => 'Emisión individual y en lote a aprobados de cualquier curso',
                'docente' => 'Emitir certificados a participantes aprobados de su curso',
                'participante' => 'Descargar su certificado en PDF y consultar validez por DNI',
            ],
            [
                'modulo' => 'Seguridad, Roles y Usuarios',
                'descripcion' => 'Control de cuentas de acceso, reasignación de roles y auditoría.',
                'admin' => 'Acceso Exclusivo: Modificar roles y supervisar usuarios',
                'docente' => 'Sin acceso a la configuración de usuarios ni roles',
                'participante' => 'Sin acceso a la configuración de usuarios ni roles',
            ],
        ];

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $selectedRole,
            ],
            'stats' => $stats,
            'permissionsMatrix' => $permissionsMatrix,
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrador', 'description' => 'Acceso y control total sobre el sistema, cursos, actas y roles.'],
                ['value' => 'docente', 'label' => 'Docente / Instructor', 'description' => 'Dicta cursos asignados, proyecta QR, registra asistencia y notas.'],
                ['value' => 'participante', 'label' => 'Participante / Alumno', 'description' => 'Se matricula, asiste con credencial QR y obtiene certificados.'],
            ],
        ]);
    }

    /**
     * Actualizar el rol institucional de un usuario registrado.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403, 'Acceso no autorizado.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,docente,participante'],
        ], [
            'role.required' => 'Debe seleccionar un rol válido.',
            'role.in' => 'El rol seleccionado no es válido en el sistema.',
        ]);

        $newRole = $validated['role'];

        // Protección anti auto-bloqueo: no despojar al único administrador
        if ($user->id === $currentUser->id && $newRole !== 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Operación denegada: Eres el único administrador del sistema. Asigna primero otro administrador antes de modificar tu rol.');
            }
        }

        $oldRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $user->role = $newRole;
        $user->save();

        $roleLabel = match ($newRole) {
            'admin' => 'Administrador',
            'docente' => 'Docente / Instructor',
            'participante' => 'Participante / Alumno',
            default => $newRole,
        };

        return back()->with('success', "El rol de {$user->name} fue actualizado exitosamente a '{$roleLabel}'.");
    }
}
