<?php

namespace App\Http\Controllers;

use App\Models\AcademicProcessWindow;
use App\Models\Project;
use App\Models\Student;
use App\Models\ThematicArea;
use App\Models\Postulation;
use App\Services\AcademicCalendar\AcademicCalendarService;
use App\Services\Students\StudentAcademicProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankApprovedIdeasForStudentsController extends Controller
{
    public function __construct(private readonly StudentAcademicProgressService $academicProgress)
    {
    }

    /**
     * Muestra los proyectos aprobados relacionados con el estudiante autenticado.
     */
    public function index(Request $request)
    {
        $student = Student::where('user_id', Auth::id())
            ->whereNull('deleted_at')
            ->first();

        if (! $student || ! $student->city_program_id) {
            abort(403, 'No se pudo determinar el programa academico del estudiante.');
        }

        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();

        if (! $this->academicProgress->canAccessIdeaBank($student, $activeAcademicPeriod)) {
    return redirect('/students/projects/postulations')
        ->with('error', $this->academicProgress->blockedIdeaBankMessage($student, $activeAcademicPeriod));
}

        $perPage = $request->input('per_page', 10);
        $thematicAreaId = $request->input('thematic_area_id');

        $program = $student->cityProgram?->program;
        $researchGroupId = $program?->research_group_id;

        if (! $researchGroupId) {
            abort(403, 'Tu programa academico no tiene un grupo de investigacion asociado.');
        }

        $thematicAreas = ThematicArea::whereHas('investigationLine', function ($q) use ($researchGroupId) {
            $q->where('research_group_id', $researchGroupId);
        })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $projectsQuery = Project::availableForStudent($student);

        if (! empty($thematicAreaId)) {
            $projectsQuery->where('thematic_area_id', $thematicAreaId);
        }

        $projects = $projectsQuery
            ->with([
                'projectStatus',
                'thematicArea.investigationLine',
                'versions.contentVersions.content',
                'contentFrameworkProjects.contentFramework.framework',
                'professors',
                'students',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $selectionWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_SELECTION);

        if (! $selectionWindow) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_SELECTION)
            );
        }

        return view('projects.student.approved', [
            'projects' => $projects,
            'thematicAreas' => $thematicAreas,
            'thematicAreaId' => $thematicAreaId,
            'perPage' => $perPage,
            'selectionWindow' => $selectionWindow,
            'selectionWindowOpen' => true,
            'selectionWindowMessage' => null,
            'activeAcademicPeriod' => $activeAcademicPeriod,
        ]);
    }

    public function show(Project $project)
    {
        $student = Student::where('user_id', Auth::id())
            ->whereNull('deleted_at')
            ->firstOrFail();

        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();
if (! $this->academicProgress->canAccessIdeaBank($student, $activeAcademicPeriod)) {
    return redirect('/students/projects/postulations')
        ->with('error', $this->academicProgress->blockedIdeaBankMessage($student, $activeAcademicPeriod));
}

        // Ideas aprobadas de su programa (banco) o proyectos en los que participa.
        $canView = $project->isAvailableForStudent($student)
            || $project->students()->where('students.id', $student->id)->exists();

        if (! $canView) {
            abort(403, 'No tienes permiso para ver este proyecto.');
        }

        $project->load([
            'projectStatus',
            'thematicArea.investigationLine',
            'versions.contentVersions.content',
            'contentFrameworkProjects.contentFramework.framework',
            'students',
            'professors',
        ]);

        $latestVersion = $project->versions()->latest('created_at')->first();

        $contentValues = [];
        if ($latestVersion) {
            $contentValues = $latestVersion->contentVersions
                ->mapWithKeys(fn ($cv) => [$cv->content->name => $cv->value])
                ->toArray();
        }

        $frameworksSelected = $project->contentFrameworkProjects()
            ->with('contentFramework.framework')
            ->get()
            ->map(fn ($item) => $item->contentFramework);

        $selectionWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_SELECTION);

        if (! $selectionWindow) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_SELECTION)
            );
        }

        $canSelectProject = true;
        
        // Check if already assigned, or the idea is no longer in the student's bank
        if ($student->hasActiveProject() || ! $project->isAvailableForStudent($student)) {
            $canSelectProject = false;
        }

        // Check if already postulated to THIS project
        $existingPostulation = Postulation::where('project_id', $project->id)
            ->whereHas('members', function($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->where('status', 'pending')
            ->first();

        $selectionWindowOpen = true;
        $selectionWindowMessage = null;

        return view('projects.student.show', compact(
            'project',
            'latestVersion',
            'contentValues',
            'frameworksSelected',
            'canSelectProject',
            'selectionWindow',
            'selectionWindowOpen',
            'selectionWindowMessage',
            'activeAcademicPeriod',
            'existingPostulation'
        ));
    }
}
