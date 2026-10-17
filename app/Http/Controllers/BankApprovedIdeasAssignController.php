<?php

namespace App\Http\Controllers;

use App\Events\ProjectIdeaEvaluated; // We can reuse this or create IdeaStatusChanged
use App\Models\AcademicProcessWindow;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Student;
use App\Services\AcademicCalendar\AcademicCalendarService;
use App\Services\Students\StudentAcademicProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankApprovedIdeasAssignController extends Controller
{
    public function __construct(private readonly StudentAcademicProgressService $academicProgress)
    {
    }

    public function select(Project $project): View|RedirectResponse
    {
        $selectionWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_SELECTION);
        
        if (! $selectionWindow) {
            return $this->academicProcessUnavailableView(AcademicProcessWindow::PROCESS_IDEA_SELECTION);
        }

        // Si la ventana requiere evaluación, NO permitimos entrar a la vista de selección directa
        if ($selectionWindow->requires_evaluation) {
            return redirect()->route('students.postulations.create', $project);
        }

        $student = Student::where('user_id', Auth::id())
            ->whereNull('deleted_at')
            ->firstOrFail();

        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();

        if (! $this->academicProgress->canAccessIdeaBank($student, $activeAcademicPeriod)) {
    return redirect('/students/projects/postulations')
        ->with('error', $this->academicProgress->blockedIdeaBankMessage($student, $activeAcademicPeriod));
}

        if ($project->projectStatus?->name !== 'Aprobado') {
            abort(403, 'Solo puedes seleccionar ideas que esten en estado Aprobado.');
        }

        $availableStudents = Student::query()
            ->where('city_program_id', $student->city_program_id)
            ->where('id', '!=', $student->id)
            ->where(function ($q) {
                $q->whereDoesntHave('projects')
                    ->orWhere(function ($q2) {
                        $q2->whereHas('projects', fn ($p) => $p->whereHas('projectStatus', fn ($s) => $s->where('name', 'Rechazado')))
                            ->whereDoesntHave('projects', fn ($p) => $p->whereHas('projectStatus', fn ($s) => $s->whereNot('name', 'Rechazado')));
                    });
            })
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        $selectionWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_SELECTION);

        return view('projects.student.select', compact('project', 'availableStudents', 'selectionWindow', 'activeAcademicPeriod'));
    }

    public function assign(Request $request, Project $project): View|RedirectResponse
    {
        $selectionWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_SELECTION);

        if (! $selectionWindow) {
            return $this->academicProcessUnavailableView(AcademicProcessWindow::PROCESS_IDEA_SELECTION);
        }

        if ($selectionWindow->requires_evaluation === true || $selectionWindow->requires_evaluation == 1) {
            return redirect()->route('students.postulations.create', $project);
        }

        $activePeriod = AcademicCalendarService::currentActivePeriodOrFail();

        $student = Student::where('user_id', Auth::id())
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (! $this->academicProgress->canAccessIdeaBank($student, $activePeriod)) {
            return back()->with('error', $this->academicProgress->blockedIdeaBankMessage($student, $activePeriod));
        }

        $validated = $request->validate([
            'teammate_ids' => ['nullable', 'array', 'max:2'],
            'teammate_ids.*' => ['integer', Rule::exists('students', 'id')->where(fn ($q) => $q->where('city_program_id', $student->city_program_id))],
        ]);

        if ($project->projectStatus?->name !== 'Aprobado') {
            abort(403, 'Este proyecto ya no esta disponible para asignacion.');
        }

        $allowedStatuses = ['Rechazado', 'Devuelto para correccion'];

        $hasActiveUser = $student->projects()
            ->where('projects.id', '!=', $project->id)
            ->whereHas('projectStatus', fn ($status) => $status->whereNotIn('name', $allowedStatuses))
            ->exists();

        if ($hasActiveUser) {
            return back()->with('error', 'No puedes asignarte a este proyecto porque ya tienes un proyecto activo.');
        }

        if (! empty($validated['teammate_ids'])) {
            $hasActiveTeammates = Student::query()
                ->whereIn('id', $validated['teammate_ids'])
                ->whereHas('projects', fn ($query) => $query->where('projects.id', '!=', $project->id)->whereHas('projectStatus', fn ($status) => $status->whereNotIn('name', $allowedStatuses)))
                ->exists();

            if ($hasActiveTeammates) {
                return back()->with('error', 'Uno o mas companeros seleccionados ya tienen un proyecto activo.');
            }
        }

        DB::beginTransaction();

        try {
            $assignedStatusId = ProjectStatus::where('name', 'Asignado')->firstOrFail()->id;
            $project->update([
                'project_status_id' => $assignedStatusId,
                'assignment_academic_period_id' => $activePeriod->id,
                'assigned_at' => now(),
            ]);

            $studentIds = collect($validated['teammate_ids'] ?? [])->push($student->id)->unique()->values()->all();
            $project->students()->syncWithoutDetaching($studentIds);

            AcademicCalendarService::recordProjectStage(
                $project,
                'assigned',
                $activePeriod,
                Auth::id(),
                'Proyecto asignado durante la ventana de seleccion.',
                ['selection_window_id' => $selectionWindow?->id, 'student_ids' => $studentIds]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Error al asignar el proyecto. Intenta de nuevo.');
        }

        // Disparar evento de notificación después del commit
        event(new ProjectIdeaEvaluated(
            $project->load(['students.user', 'professors.user']),
            'Asignado',
            'El proyecto ha sido seleccionado por un estudiante.'
        ));

        return redirect()->route('projects.index')->with('success', 'Proyecto asignado exitosamente. Ahora es tuyo para ejecutar.');
    }
}
