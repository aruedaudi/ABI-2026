<?php

namespace App\Http\Controllers;

use App\Models\Postulation;
use App\Models\PostulationMember;
use App\Models\PostulationPriority;
use App\Models\Project;
use App\Models\Student;
use App\Services\AcademicCalendar\AcademicCalendarService;
use App\Services\Students\StudentAcademicProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PostulationController extends Controller
{
    public function __construct(private readonly StudentAcademicProgressService $academicProgress)
    {
    }

    public function index()
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $postulations = $student->applications()
            ->with(['project.thematicArea', 'project.professors', 'priorities' => function ($q) use ($student) {
                $q->where('student_id', $student->id);
            }])
            ->get();

        return view('postulations.student.index', compact('postulations', 'student'));
    }

    public function show(Postulation $postulation)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Verificar que el estudiante sea parte de la postulación
        $isMember = $postulation->members()->where('student_id', $student->id)->exists();

        if (!$isMember) {
            abort(403, 'No tienes permiso para ver esta postulación.');
        }

        $postulation->load(['project.thematicArea', 'project.professors', 'members.student', 'priorities' => function ($q) use ($student) {
            $q->where('student_id', $student->id);
        }]);

        return view('postulations.student.show', compact('postulation', 'student'));
    }

    public function create(Project $project)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $activePeriod = AcademicCalendarService::currentActivePeriod();

        if (! $this->academicProgress->canAccessIdeaBank($student, $activePeriod)) {
    return redirect('/students/projects/postulations')
        ->with('error', $this->academicProgress->blockedIdeaBankMessage($student, $activePeriod));
}

        if ($student->hasActiveProject()) {
            return redirect()->route('students.projects.approved.index')
                ->with('error', 'Ya tienes un proyecto activo asignado.');
        }

        if ($student->activeApplicationsCount() >= 3) {
            return redirect()->route('students.projects.approved.index')
                ->with('error', 'Ya has alcanzado el límite máximo de 3 postulaciones activas.');
        }

        $alreadyPostulated = Postulation::where('project_id', $project->id)
            ->whereHas('members', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPostulated) {
            return redirect()->route('students.postulations.index')
                ->with('error', 'Ya tienes una postulación pendiente para esta idea.');
        }

        $availableStudents = Student::query()
            ->where('city_program_id', $student->city_program_id)
            ->where('semester', $student->semester)
            ->where('id', '!=', $student->id)
            ->whereDoesntHave('projects', function ($q) {
                $q->whereHas('projectStatus', fn ($s) => $s->whereNotIn('name', ['Rechazado', 'Devuelto para correccion']));
            })
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();

        // Obtener las prioridades ya utilizadas por el estudiante
        $usedPriorities = PostulationPriority::where('student_id', $student->id)
            ->pluck('priority_order')
            ->toArray();

        $allPriorities = [1 => 'Prioridad 1 (Alta)', 2 => 'Prioridad 2 (Media)', 3 => 'Prioridad 3 (Baja)'];
        $availablePriorities = [];

        foreach ($allPriorities as $value => $label) {
            if (! in_array($value, $usedPriorities)) {
                $availablePriorities[$value] = $label;
            }
        }

        $maxStudents = $project->maxStudents();

        return view('postulations.student.create', compact('project', 'student', 'availableStudents', 'availablePriorities', 'maxStudents'));
    }

    public function store(Request $request)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $project = Project::findOrFail($request->project_id);

        // Si ya existe una postulación rechazada para este mismo proyecto y este estudiante,
        // la eliminamos ANTES de validar para liberar la prioridad y evitar conflictos UNIQUE
        Postulation::where('project_id', $project->id)
            ->where('lead_student_id', $student->id)
            ->where('status', 'rejected')
            ->delete();

        $maxStudents = $project->maxStudents();

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'justification' => 'required|string|min:50',
            'accepted_terms' => 'required|accepted',
            'grades_file' => 'required|file|mimes:pdf|max:2048',
            'modality' => 'required|in:individual,team',
            'lead_role' => 'required|string|max:255',
            'priority_order' => [
                'required',
                'integer',
                'in:1,2,3',
                Rule::unique('postulation_priorities', 'priority_order')->where('student_id', $student->id),
            ],
            'teammates' => ['required_if:modality,team', 'array', 'max:'.($maxStudents - 1)],
            'teammates.*.id' => [
                'nullable',
                Rule::exists('students', 'id')
                    ->where('city_program_id', $student->city_program_id)
                    ->where('semester', $student->semester),
            ],
            'teammates.*.role' => 'required_with:teammates.*.id|nullable|string|max:255',
        ]);

        if ($student->hasActiveProject()) {
            return back()->with('error', 'No puedes postularte porque ya tienes un proyecto activo.');
        }

        if (! empty($request->teammates)) {
            $teammateIds = collect($request->teammates)->pluck('id')->filter()->toArray();
            $hasActiveTeammates = Student::whereIn('id', $teammateIds)
                ->get()
                ->filter(fn ($s) => $s->hasActiveProject())
                ->isNotEmpty();

            if ($hasActiveTeammates) {
                return back()->with('error', 'Uno o más integrantes seleccionados ya tienen un proyecto activo.');
            }
        }

        if ($student->activeApplicationsCount() >= 3) {
            return back()->with('error', 'No puedes tener más de 3 postulaciones activas.');
        }

        DB::beginTransaction();
        try {
            $filePath = $request->file('grades_file')->store('postulations/grades', 'local');

            $postulation = Postulation::create([
                'project_id' => $project->id,
                'lead_student_id' => $student->id,
                'justification' => $request->justification,
                'accepted_terms' => true,
                'grades_file' => $filePath,
                'status' => 'pending',
            ]);

            // Agregar al estudiante líder (el que postula)
            PostulationMember::create([
                'postulation_id' => $postulation->postulation_id,
                'student_id' => $student->id,
                'role_description' => $request->lead_role,
                'is_lead' => true,
            ]);

            // Agregar compañeros si es en equipo
            if ($request->modality === 'team' && is_array($request->teammates)) {
                foreach ($request->teammates as $teammate) {
                    if (! empty($teammate['id'])) {
                        PostulationMember::create([
                            'postulation_id' => $postulation->postulation_id,
                            'student_id' => $teammate['id'],
                            'role_description' => $teammate['role'],
                            'is_lead' => false,
                        ]);
                    }
                }
            }

            // Asignar prioridad seleccionada
            PostulationPriority::create([
                'student_id' => $student->id,
                'postulation_id' => $postulation->postulation_id,
                'priority_order' => $request->priority_order,
            ]);

            DB::commit();

            return redirect()->route('students.postulations.index')
                ->with('success', 'Tu postulación ha sido enviada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return back()->with('error', 'Ocurrió un error al procesar tu postulación: '.$e->getMessage());
        }
    }

    public function destroy(Postulation $postulation)
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Solo el líder que creó la postulación puede cancelarla
        if ($postulation->lead_student_id !== $student->id) {
            abort(403, 'Solo el líder que creó la postulación puede cancelarla.');
        }

        if ($postulation->status !== 'pending') {
            return back()->with('error', 'Solo puedes cancelar postulaciones pendientes.');
        }

        DB::beginTransaction();
        try {
            // Reordenar prioridades después de la eliminación
            $priorityToDelete = PostulationPriority::where('student_id', $student->id)
                ->where('postulation_id', $postulation->postulation_id)
                ->first();

            if ($priorityToDelete) {
                $priorityOrder = $priorityToDelete->priority_order;
                $priorityToDelete->delete();

                // Liberar el espacio de prioridad para este estudiante
                PostulationPriority::where('student_id', $student->id)
                    ->where('priority_order', '>', $priorityOrder)
                    ->decrement('priority_order');
            }

            // Eliminar archivo de notas si existe
            if ($postulation->grades_file && Storage::disk('local')->exists($postulation->grades_file)) {
                Storage::disk('local')->delete($postulation->grades_file);
            }

            // Al eliminar la postulación, Eloquent se encarga de los miembros si hay cascade en la BD
            // o los eliminamos manualmente para mayor seguridad
            $postulation->delete();

            DB::commit();

            return back()->with('success', 'Postulación cancelada correctamente y prioridad liberada.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error al cancelar la postulación: ' . $e->getMessage());
        }
    }
}
