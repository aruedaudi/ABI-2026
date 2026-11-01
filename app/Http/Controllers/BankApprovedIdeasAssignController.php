<?php

namespace App\Http\Controllers;

use App\Events\ProjectIdeaEvaluated;
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

        // VALIDACIÓN CORRECTA: Confía en el método nativo que revisa el programa del profesor
        if (! $project->isAvailableForStudent($student)) {
            return redirect()->route('students.projects.approved.index')
                ->with('error', 'Solo puedes seleccionar ideas aprobadas de tu mismo programa académico.');
        }

        // Dejamos la lista vacía porque quitamos la opción de compañeros
        $availableStudents = collect(); 

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

        // VALIDACIÓN CORRECTA
        if (! $project->isAvailableForStudent($student)) {
            return back()->with('error', 'No puedes asignarte a un proyecto que no pertenece a tu programa académico o no está disponible.');
        }

        $hasActiveUser = $student->projects()
            ->where('projects.id', '!=', $project->id)
            ->whereHas('projectStatus', fn ($status) => $status->whereNotIn('name', Student::INACTIVE_PROJECT_STATUSES))
            ->exists();

        if ($hasActiveUser) {
            return back()->with('error', 'No puedes asignarte a este proyecto porque ya tienes un proyecto activo.');
        }

        DB::beginTransaction();

        try {
            $assignedStatusId = ProjectStatus::where('name', 'Asignado')->firstOrFail()->id;
            $project->update([
                'project_status_id' => $assignedStatusId,
                'assignment_academic_period_id' => $activePeriod->id,
                'assigned_at' => now(),
            ]);

            // ASIGNACIÓN SEGURA: Solo asigna al estudiante líder (sin compañeros forzados)
            $project->students()->syncWithoutDetaching([$student->id]);

            AcademicCalendarService::recordProjectStage(
                $project,
                'assigned',
                $activePeriod,
                Auth::id(),
                'Proyecto asignado durante la ventana de selección.',
                ['selection_window_id' => $selectionWindow?->id, 'student_ids' => [$student->id]]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error al asignar el proyecto: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Error al asignar el proyecto. Intenta de nuevo.');
        }

        event(new ProjectIdeaEvaluated(
            $project->load(['students.user', 'professors.user']),
            'Asignado',
            'El proyecto ha sido seleccionado por un estudiante.'
        ));

        return redirect()->route('projects.index')->with('success', 'Proyecto asignado exitosamente. Ahora es tuyo para ejecutar.');
    }
}