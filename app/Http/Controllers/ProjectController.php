<?php

namespace App\Http\Controllers;

use App\Helpers\AuthUserHelper;
use App\Models\AcademicPeriod;
use App\Models\AcademicProcessWindow;
use App\Models\City;
use App\Models\CityProgram;
use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Framework;
use App\Models\InvestigationLine;
use App\Models\Professor;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Student;
use App\Models\ThematicArea;
use App\Models\User;
use App\Models\Version;
use App\Services\AcademicCalendar\AcademicCalendarService;
use App\Services\Projects\ProjectAgeReviewService;
use App\Services\Projects\TeacherIdeaBalanceService;
use App\Services\Students\StudentAcademicProgressService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Cache of content identifiers keyed by normalized name.
     *
     * @var array<string, int>
     */
    protected array $contentCache = [];

    /**
     * Cached identifier for the "waiting evaluation" status.
     */
    protected ?int $waitingStatusId = null;

    /**
     * Display a paginated list of projects for the authenticated user.
     */
    public function index(Request $request): View|Response
    {
        $user = AuthUserHelper::fullUser();

        $query = Project::query()
            ->with([
                'thematicArea.investigationLine',
                'projectStatus',
                'professors' => static fn ($relation) => $relation
                    ->with(['user', 'cityProgram.program'])
                    ->orderBy('last_name')
                    ->orderBy('name'),
                'students' => static fn ($relation) => $relation
                    ->orderBy('last_name')
                    ->orderBy('name'),
            ])
            ->orderByDesc('created_at');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $statusFilter = $request->input('status_id');
        if ($statusFilter) {
            $query->where('project_status_id', $statusFilter);
        }

        $pendingReviewDueToAge = $request->boolean('pending_review_due_to_age');
        if ($user?->role === 'research_staff' && $pendingReviewDueToAge) {
            $query->pendingReviewDueToAge();
        }

        $cityPrograms = CityProgram::with(['program', 'city'])->get();
        $selectedCityProgram = $request->integer('city_program_id');

        if ($user?->role === 'research_staff' && $selectedCityProgram) {
            $query->whereHas('professors', function ($q) use ($selectedCityProgram) {
                $q->where('city_program_id', $selectedCityProgram);
            });
        }

        if (in_array($user?->role, ['professor', 'committee_leader'], true) && $user->professor) {
            $professorId = $user->professor->id;

            $query->whereHas('professors', static function ($relation) use ($professorId) {
                $relation->where('professors.id', $professorId);
            });
        } elseif ($user?->role === 'student' && $user->student) {
            $studentId = $user->student->id;

            $query->whereHas('students', static function ($relation) use ($studentId) {
                $relation->where('students.id', $studentId);
            });
        }

        $projects = $query->paginate(10)->withQueryString();
        $reportState = $this->emptyProjectReportState();

        if ($user?->role === 'research_staff') {
            $reportState = $this->buildProjectReportState($request, $user);

            if ($reportState['export'] === 'pdf') {
                return $this->downloadProjectReportPdf($reportState);
            }
        }

        $projectAgeReviewService = app(ProjectAgeReviewService::class);
        $projects->getCollection()->transform(function (Project $project) use ($projectAgeReviewService) {
            $project->setAttribute('pending_review_due_to_age', $projectAgeReviewService->shouldFlag($project));
            $project->setAttribute('elapsed_periods_since_proposal', $projectAgeReviewService->elapsedAcademicPeriods($project));

            return $project;
        });

        $programCatalog = collect();
        if ($user?->role === 'committee_leader') {
            $programCatalog = Program::query()->orderBy('name')->get();
        }

        $projectStatuses = ProjectStatus::orderBy('name')->get();

        $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $proposalWindowOpen = $proposalWindow !== null;
        $proposalWindowMessage = $proposalWindowOpen
            ? null
            : AcademicCalendarService::processWindowUnavailableMessage(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();
        $studentAcademicProgress = app(StudentAcademicProgressService::class);

        $enableButtonStudent = true;

        if ($user?->role === 'student' && $user->student) {
            $enableButtonStudent = $studentAcademicProgress->canCreateProposal($user->student, $activeAcademicPeriod);
        }

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
            'isProfessor' => in_array($user?->role, ['professor', 'committee_leader'], true),
            'isStudent' => $user?->role === 'student',
            'isCommitteeLeader' => $user?->role === 'committee_leader',
            'isResearchStaff' => $user?->role === 'research_staff',
            'programCatalog' => $programCatalog,
            'enableButtonStudent' => $enableButtonStudent,
            'projectStatuses' => $projectStatuses,
            'selectedStatus' => $statusFilter,
            'cityPrograms' => $cityPrograms,
            'selectedCityProgram' => $selectedCityProgram,
            'pendingReviewDueToAge' => $pendingReviewDueToAge,
            'proposalWindow' => $proposalWindow,
            'proposalWindowOpen' => $proposalWindowOpen,
            'proposalWindowMessage' => $proposalWindowMessage,
            'activeAcademicPeriod' => $activeAcademicPeriod,
            'canCreateProject' => (in_array($user?->role, ['professor', 'committee_leader'], true) || ($user?->role === 'student' && $enableButtonStudent)) && $proposalWindowOpen,
            'reportModules' => $this->projectReportModules(),
            'reportFilters' => $reportState['filters'],
            'reportData' => $reportState['reportData'],
            'reportSegments' => $reportState['segments'],
            'reportVisuals' => $reportState['visuals'],
            'reportInsights' => $reportState['insights'],
            'reportTable' => $reportState['table'],
            'activeReportKey' => $reportState['reportKey'],
            'reportProgramOptions' => Program::query()
                ->selectRaw('MIN(id) as id, name')
                ->groupBy('name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Build the report state rendered below the projects list.
     */
    protected function buildProjectReportState(Request $request, ?User $user): array
    {
        $filters = $request->validate([
            'report_key' => ['nullable', Rule::in(array_keys($this->projectReportModules()))],
            'report_from' => ['nullable', 'date'],
            'report_to' => ['nullable', 'date', 'after_or_equal:report_from'],
            'report_program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'report_export' => ['nullable', Rule::in(['pdf'])],
        ]);

        $reportKey = $filters['report_key'] ?? 'projects_by_status';
        $reportModules = $this->projectReportModules();
        $reportLabel = $reportModules[$reportKey]['label'] ?? $reportModules['projects_by_status']['label'];
        $normalizedFilters = [
            'report_key' => $reportKey,
            'report_from' => $filters['report_from'] ?? null,
            'report_to' => $filters['report_to'] ?? null,
            'report_program_id' => isset($filters['report_program_id']) ? (int) $filters['report_program_id'] : null,
        ];

        $reportState = $this->composeProjectReportState($reportKey, $normalizedFilters, $user);

        return [
            'filters' => $normalizedFilters,
            'reportKey' => $reportKey,
            'reportLabel' => $reportLabel,
            'reportData' => $reportState['reportData'],
            'segments' => $reportState['segments'],
            'visuals' => $reportState['visuals'],
            'insights' => $reportState['insights'],
            'table' => $reportState['table'],
            'exportLabel' => $reportState['exportLabel'],
            'export' => $filters['report_export'] ?? null,
        ];
    }

    protected function emptyProjectReportState(): array
    {
        return [
            'filters' => [
                'report_key' => 'projects_by_status',
                'report_from' => null,
                'report_to' => null,
                'report_program_id' => null,
            ],
            'reportKey' => 'projects_by_status',
            'reportLabel' => 'Proyectos por estado',
            'reportData' => [
                'categories' => [],
                'values' => [],
                'percentages' => [],
                'total' => 0,
            ],
            'segments' => [],
            'visuals' => [],
            'insights' => [],
            'table' => null,
            'exportLabel' => 'Proyectos por estado',
            'export' => null,
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    protected function projectReportModules(): array
    {
        return [
            'projects_by_status' => [
                'label' => 'Proyectos por estado',
                'description' => 'Compara la distribucion de proyectos segun su estado actual.',
            ],
            'projects_by_author_type' => [
                'label' => 'Proyectos por tipo de autor',
                'description' => 'Clasifica cada proyecto segun si participan estudiantes, docentes o ambos tipos de autores.',
            ],
            'projects_by_thematic_area' => [
                'label' => 'Proyectos por area tematica',
                'description' => 'Muestra que areas tematicas concentran mayor cantidad de proyectos.',
            ],
            'projects_by_investigation_line' => [
                'label' => 'Proyectos por linea de investigacion',
                'description' => 'Permite comparar los proyectos agrupados por linea de investigacion.',
            ],
            'projects_programs_and_lines' => [
                'label' => 'Programas y lineas',
                'description' => 'Relaciona programas con propuestas y destaca las lineas de investigacion mas usadas.',
            ],
            'projects_traceability' => [
                'label' => 'Trazabilidad basica',
                'description' => 'Resume los proyectos con mas versiones y muestra comentarios de correccion cuando existan.',
            ],
            'projects_old_bank_ideas' => [
                'label' => 'Ideas antiguas en el banco',
                'description' => 'Identifica ideas que siguen en el banco institucional y acumulan mayor antiguedad.',
            ],
            'projects_status_rotation' => [
                'label' => 'Ideas con mayor rotacion',
                'description' => 'Destaca las ideas con mas cambios de estado y resume la intensidad de sus movimientos.',
            ],
        ];
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     visuals: array<int, array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         total_label: string,
     *         data: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *         segments: array<int, array{label: string, value: int, percentage: float, color: string}>
     *     }>,
     *     insights: array<int, array{label: string, value: string, caption: string}>,
     *     table: ?array{
     *         title: string,
     *         description: string,
     *         columns: array<int, string>,
     *         rows: array<int, array<int, string>>
     *     },
     *     exportLabel: string
     * }
     */
    protected function composeProjectReportState(string $reportKey, array $filters, ?User $user): array
    {
        return match ($reportKey) {
            'projects_programs_and_lines' => $this->buildProgramsAndLinesReportState($filters, $user),
            'projects_traceability' => $this->buildTraceabilityReportState($filters, $user),
            'projects_old_bank_ideas' => $this->buildOldBankIdeasReportState($filters, $user),
            'projects_status_rotation' => $this->buildStatusRotationReportState($filters, $user),
            default => $this->buildStandardDistributionReportState($reportKey, $filters, $user),
        };
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     visuals: array<int, array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         total_label: string,
     *         data: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *         segments: array<int, array{label: string, value: int, percentage: float, color: string}>
     *     }>,
     *     insights: array<int, array{label: string, value: string, caption: string}>,
     *     table: null,
     *     exportLabel: string
     * }
     */
    protected function buildStandardDistributionReportState(string $reportKey, array $filters, ?User $user): array
    {
        $reportModules = $this->projectReportModules();
        $reportLabel = $reportModules[$reportKey]['label'] ?? 'Distribucion';
        $reportDescription = $reportModules[$reportKey]['description'] ?? 'Distribucion de proyectos.';
        $reportData = $this->generateProjectDistributionReport($reportKey, $filters, $user);
        $segments = $this->buildProjectReportSegments($reportData);
        $topSegment = collect($segments)->sortByDesc('value')->first();

        return [
            'reportData' => $reportData,
            'segments' => $segments,
            'visuals' => [
                $this->makeProjectReportVisual(
                    'primary-distribution',
                    $reportLabel,
                    $reportDescription,
                    $reportData,
                    'Total de proyectos'
                ),
            ],
            'insights' => [
                [
                    'label' => 'Total de registros',
                    'value' => (string) $reportData['total'],
                    'caption' => 'Proyectos incluidos con los filtros actuales.',
                ],
                [
                    'label' => 'Categorias detectadas',
                    'value' => (string) count($reportData['categories']),
                    'caption' => 'Cantidad de grupos comparados en el reporte.',
                ],
                [
                    'label' => 'Categoria principal',
                    'value' => (string) ($topSegment['label'] ?? 'Sin datos'),
                    'caption' => 'Grupo con mayor concentracion de proyectos.',
                ],
            ],
            'table' => null,
            'exportLabel' => $reportLabel,
        ];
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     visuals: array<int, array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         total_label: string,
     *         data: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *         segments: array<int, array{label: string, value: int, percentage: float, color: string}>
     *     }>,
     *     insights: array<int, array{label: string, value: string, caption: string}>,
     *     table: null,
     *     exportLabel: string
     * }
     */
    protected function buildProgramsAndLinesReportState(array $filters, ?User $user): array
    {
        $projects = $this->programsAndLinesProjectsQuery($filters, $user)->get();

        $programCounts = $projects
            ->flatMap(function (Project $project) {
                return collect($project->professors
                    ->map(fn (Professor $professor) => $professor->cityProgram?->program?->name)
                    ->all())
                    ->concat($project->students->map(fn (Student $student) => $student->cityProgram?->program?->name)->all())
                    ->filter()
                    ->map(fn (string $programName) => trim($programName))
                    ->unique()
                    ->values();
            })
            ->countBy()
            ->sortDesc();

        $lineCounts = $projects
            ->map(function (Project $project): string {
                return $project->thematicArea?->investigationLine?->name ?? 'Sin linea de investigacion';
            })
            ->countBy()
            ->sortDesc();

        $programReportData = $this->reportDataFromLabelCounts($programCounts->all());
        $leastProgramReportData = $this->reportDataFromLabelCounts(
            $programCounts
                ->sort()
                ->take(6)
                ->all()
        );
        $lineReportData = $this->reportDataFromLabelCounts(
            $lineCounts
                ->take(8)
                ->all()
        );

        $primaryVisual = $this->makeProjectReportVisual(
            'program-proposals',
            'Programas con propuestas registradas',
            'Cuenta las propuestas asociadas a cada programa academico.',
            $programReportData,
            'Propuestas contabilizadas'
        );

        $topProgram = $programCounts->keys()->first();
        $topProgramValue = $topProgram !== null ? (int) $programCounts->get($topProgram, 0) : 0;
        $bottomProgram = $programCounts->sort()->keys()->first();
        $bottomProgramValue = $bottomProgram !== null ? (int) $programCounts->get($bottomProgram, 0) : 0;
        $topLine = $lineCounts->keys()->first();
        $topLineValue = $topLine !== null ? (int) $lineCounts->get($topLine, 0) : 0;

        return [
            'reportData' => $primaryVisual['data'],
            'segments' => $primaryVisual['segments'],
            'visuals' => [
                $primaryVisual,
                $this->makeProjectReportVisual(
                    'program-least-proposals',
                    'Programas con menos propuestas',
                    'Resalta los programas con menor numero de propuestas dentro del filtro actual.',
                    $leastProgramReportData,
                    'Propuestas contabilizadas'
                ),
                $this->makeProjectReportVisual(
                    'investigation-lines-most-used',
                    'Lineas de investigacion mas usadas',
                    'Mide que lineas concentran mas proyectos propuestos.',
                    $lineReportData,
                    'Proyectos asociados'
                ),
            ],
            'insights' => [
                [
                    'label' => 'Proyectos analizados',
                    'value' => (string) $projects->count(),
                    'caption' => 'Base total usada para comparar programas y lineas.',
                ],
                [
                    'label' => 'Programa con mas propuestas',
                    'value' => $topProgram ? "{$topProgram} ({$topProgramValue})" : 'Sin datos',
                    'caption' => 'Mayor concentracion de propuestas en el rango filtrado.',
                ],
                [
                    'label' => 'Programa con menos propuestas',
                    'value' => $bottomProgram ? "{$bottomProgram} ({$bottomProgramValue})" : 'Sin datos',
                    'caption' => 'Programa con menor participacion dentro del reporte.',
                ],
                [
                    'label' => 'Linea mas usada',
                    'value' => $topLine ? "{$topLine} ({$topLineValue})" : 'Sin datos',
                    'caption' => 'Linea de investigacion con mayor uso en proyectos.',
                ],
            ],
            'table' => null,
            'exportLabel' => 'Programas con propuestas registradas',
        ];
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     visuals: array<int, array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         total_label: string,
     *         data: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *         segments: array<int, array{label: string, value: int, percentage: float, color: string}>
     *     }>,
     *     insights: array<int, array{label: string, value: string, caption: string}>,
     *     table: array{
     *         title: string,
     *         description: string,
     *         columns: array<int, string>,
     *         rows: array<int, array<int, string>>
     *     },
     *     exportLabel: string
     * }
     */
    protected function buildTraceabilityReportState(array $filters, ?User $user): array
    {
        $commentContentIds = $this->commentContentIds();
        $query = $this->traceabilityProjectsQuery($filters, $user, $commentContentIds);

        $projects = (clone $query)
            ->withCount('versions')
            ->orderByDesc('versions_count')
            ->orderBy('title')
            ->get(['projects.id', 'projects.title']);

        $totalProjects = $projects->count();
        $totalVersions = (int) $projects->sum('versions_count');
        $projectsWithComments = (clone $query)
            ->where(function (Builder $builder) use ($commentContentIds): void {
                $this->applyCorrectionCommentsPresenceFilter($builder, $commentContentIds);
            })
            ->distinct()
            ->count('projects.id');

        $topProjects = $projects
            ->take(8)
            ->mapWithKeys(function (Project $project) {
                return [$project->title => (int) $project->versions_count];
            });

        $versionBuckets = $projects
            ->map(function (Project $project) {
                $versionsCount = (int) $project->versions_count;

                return match (true) {
                    $versionsCount <= 1 => '1 version',
                    $versionsCount === 2 => '2 versiones',
                    $versionsCount === 3 => '3 versiones',
                    default => '4 o mas versiones',
                };
            })
            ->countBy();

        $commentsPresenceData = $this->reportDataFromLabelCounts([
            'Con comentarios de correccion' => $projectsWithComments,
            'Sin comentarios registrados' => max($totalProjects - $projectsWithComments, 0),
        ]);

        $topVersionsData = $this->reportDataFromLabelCounts($topProjects->all());
        $versionBucketData = $this->reportDataFromLabelCounts([
            '1 version' => $versionBuckets['1 version'] ?? 0,
            '2 versiones' => $versionBuckets['2 versiones'] ?? 0,
            '3 versiones' => $versionBuckets['3 versiones'] ?? 0,
            '4 o mas versiones' => $versionBuckets['4 o mas versiones'] ?? 0,
        ]);

        $topProjectIds = $projects->take(12)->pluck('id');
        $projectDetails = Project::query()
            ->with([
                'versions' => static fn ($relation) => $relation
                    ->with(['contentVersions.content'])
                    ->orderByDesc('created_at'),
                'stageHistories' => static fn ($relation) => $relation
                    ->where('stage', 'returned_for_correction')
                    ->orderByDesc('event_at')
                    ->orderByDesc('id'),
            ])
            ->whereIn('id', $topProjectIds)
            ->get()
            ->keyBy('id');

        $tableRows = $projects
            ->take(12)
            ->map(function (Project $project) use ($projectDetails): array {
                /** @var Project|null $detail */
                $detail = $projectDetails->get($project->id);
                $comments = $detail ? $this->projectCorrectionComments($detail) : collect();
                $latestComment = $comments->first() ?? 'Sin comentarios registrados';

                return [
                    $project->title,
                    (string) $project->versions_count,
                    (string) $comments->count(),
                    (string) Str::limit($latestComment, 160),
                ];
            })
            ->all();

        $topProject = $projects->first();
        $topProjectLabel = $topProject
            ? "{$topProject->title} ({$topProject->versions_count})"
            : 'Sin datos';

        $primaryVisual = $this->makeProjectReportVisual(
            'projects-most-versions',
            'Proyectos con mas versiones',
            'Ordena los proyectos segun la cantidad de versiones acumuladas.',
            $topVersionsData,
            'Versiones registradas'
        );

        return [
            'reportData' => $primaryVisual['data'],
            'segments' => $primaryVisual['segments'],
            'visuals' => [
                $primaryVisual,
                $this->makeProjectReportVisual(
                    'projects-version-distribution',
                    'Distribucion por numero de versiones',
                    'Agrupa los proyectos segun cuantas versiones han generado.',
                    $versionBucketData,
                    'Proyectos clasificados'
                ),
                $this->makeProjectReportVisual(
                    'projects-correction-comments',
                    'Cobertura de comentarios de correccion',
                    'Indica cuantos proyectos ya registran motivos o comentarios de correccion.',
                    $commentsPresenceData,
                    'Proyectos revisados'
                ),
            ],
            'insights' => [
                [
                    'label' => 'Proyectos analizados',
                    'value' => (string) $totalProjects,
                    'caption' => 'Proyectos considerados en la trazabilidad filtrada.',
                ],
                [
                    'label' => 'Versiones registradas',
                    'value' => (string) $totalVersions,
                    'caption' => 'Suma de versiones creadas entre todos los proyectos.',
                ],
                [
                    'label' => 'Proyecto con mas versiones',
                    'value' => $topProjectLabel,
                    'caption' => 'Proyecto que mas iteraciones acumula actualmente.',
                ],
                [
                    'label' => 'Con comentarios de correccion',
                    'value' => (string) $projectsWithComments,
                    'caption' => 'Proyectos que ya tienen motivos o comentarios visibles.',
                ],
            ],
            'table' => [
                'title' => 'Detalle de trazabilidad',
                'description' => 'Lista priorizada de proyectos con mas versiones y su comentario de correccion mas reciente cuando existe.',
                'columns' => ['Proyecto', 'Versiones', 'Correcciones registradas', 'Ultimo comentario'],
                'rows' => $tableRows,
            ],
            'exportLabel' => 'Proyectos con mas versiones',
        ];
    }

    protected function buildOldBankIdeasReportState(array $filters, ?User $user): array
    {
        $ageReviewService = app(ProjectAgeReviewService::class);
        $projects = $this->oldBankIdeasProjectsQuery($filters, $user)->get();
        $referenceDate = now()->startOfDay();
        $referencePeriod = $ageReviewService->referenceAcademicPeriod();
        $thresholdPeriods = $ageReviewService->thresholdPeriods();

        $ideas = $projects
            ->map(function (Project $project) use ($ageReviewService, $referenceDate, $referencePeriod, $thresholdPeriods): array {
                $enteredBankAt = $project->proposed_at ?? $project->created_at;
                $daysInBank = $enteredBankAt
                    ? max((int) $enteredBankAt->copy()->startOfDay()->diffInDays($referenceDate, false), 0)
                    : 0;
                $elapsedPeriods = $ageReviewService->elapsedAcademicPeriods($project, $referencePeriod);

                return [
                    'project_id' => (int) $project->id,
                    'title' => $project->title,
                    'status' => $project->projectStatus?->name ?? 'Sin estado',
                    'proposal_period' => $project->proposalAcademicPeriod?->name ?? 'Sin periodo academico',
                    'entered_bank_at' => $enteredBankAt?->format('d/m/Y') ?? 'Sin fecha',
                    'days_in_bank' => $daysInBank,
                    'elapsed_periods' => $elapsedPeriods,
                    'pending_review_due_to_age' => $elapsedPeriods !== null && $elapsedPeriods >= $thresholdPeriods,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [
                    $right['days_in_bank'],
                    $right['elapsed_periods'] ?? -1,
                    $right['project_id'],
                ] <=> [
                    $left['days_in_bank'],
                    $left['elapsed_periods'] ?? -1,
                    $left['project_id'],
                ];
            })
            ->values();

        $totalIdeas = $ideas->count();
        $agedIdeasCount = $ideas->where('pending_review_due_to_age', true)->count();
        $averageDays = $totalIdeas > 0 ? round((float) $ideas->avg('days_in_bank'), 1) : 0.0;
        $oldestIdea = $ideas->first();

        $topOldestData = $this->reportDataFromLabelCounts(
            $ideas
                ->take(8)
                ->mapWithKeys(static function (array $idea): array {
                    return [$idea['title'] => $idea['days_in_bank']];
                })
                ->all()
        );

        $periodBuckets = $ideas->countBy(static function (array $idea): string {
            return match (true) {
                $idea['elapsed_periods'] === null => 'Sin periodo academico',
                $idea['elapsed_periods'] <= 1 => '0 a 1 periodo',
                $idea['elapsed_periods'] === 2 => '2 periodos',
                $idea['elapsed_periods'] === 3 => '3 periodos',
                default => '4 o mas periodos',
            };
        });

        $statusCounts = $ideas
            ->pluck('status')
            ->countBy()
            ->sortDesc();

        $periodBucketData = $this->reportDataFromLabelCounts([
            '0 a 1 periodo' => (int) ($periodBuckets['0 a 1 periodo'] ?? 0),
            '2 periodos' => (int) ($periodBuckets['2 periodos'] ?? 0),
            '3 periodos' => (int) ($periodBuckets['3 periodos'] ?? 0),
            '4 o mas periodos' => (int) ($periodBuckets['4 o mas periodos'] ?? 0),
            'Sin periodo academico' => (int) ($periodBuckets['Sin periodo academico'] ?? 0),
        ]);

        $statusDistributionData = $this->reportDataFromLabelCounts($statusCounts->all());
        $ageAlertData = $this->reportDataFromLabelCounts([
            'Pendientes por antiguedad' => $agedIdeasCount,
            'Dentro del rango esperado' => max($totalIdeas - $agedIdeasCount, 0),
        ]);

        return [
            'reportData' => $topOldestData,
            'segments' => $this->buildProjectReportSegments($topOldestData),
            'visuals' => [
                $this->makeProjectReportVisual(
                    'old-bank-top-ideas',
                    'Ideas con mayor permanencia en el banco',
                    'Ordena las ideas segun los dias acumulados en el banco institucional.',
                    $topOldestData,
                    'Dias acumulados',
                    'dias'
                ),
                $this->makeProjectReportVisual(
                    'old-bank-period-buckets',
                    'Antiguedad por periodos academicos',
                    'Agrupa las ideas del banco segun el tiempo transcurrido desde su propuesta.',
                    $periodBucketData,
                    'Ideas clasificadas'
                ),
                $this->makeProjectReportVisual(
                    'old-bank-status-distribution',
                    'Estado actual de las ideas antiguas',
                    'Compara en que estado permanecen las ideas que siguen en el banco.',
                    $statusDistributionData,
                    'Ideas en banco'
                ),
                $this->makeProjectReportVisual(
                    'old-bank-age-alerts',
                    'Alertas por antiguedad',
                    'Diferencia las ideas que ya superaron el umbral de revision frente al resto del banco.',
                    $ageAlertData,
                    'Ideas monitoreadas'
                ),
            ],
            'insights' => [
                [
                    'label' => 'Ideas analizadas',
                    'value' => (string) $totalIdeas,
                    'caption' => 'Ideas que siguen disponibles o activas dentro del banco con los filtros actuales.',
                ],
                [
                    'label' => 'Idea mas antigua',
                    'value' => $oldestIdea
                        ? "{$oldestIdea['title']} ({$oldestIdea['days_in_bank']} dias)"
                        : 'Sin datos',
                    'caption' => 'Idea con mayor permanencia acumulada en el banco institucional.',
                ],
                [
                    'label' => 'Permanencia promedio',
                    'value' => number_format($averageDays, 1) . ' dias',
                    'caption' => 'Promedio de permanencia considerando todas las ideas del reporte.',
                ],
                [
                    'label' => 'Pendientes por antiguedad',
                    'value' => (string) $agedIdeasCount,
                    'caption' => 'Ideas que ya superan el umbral automatico de revision por antiguedad.',
                ],
            ],
            'table' => [
                'title' => 'Detalle de ideas antiguas en el banco',
                'description' => 'Lista priorizada de ideas con mayor permanencia, incluyendo su estado actual y el periodo academico asociado.',
                'columns' => ['Idea', 'Estado actual', 'Periodo de propuesta', 'Ingreso al banco', 'Dias en banco', 'Periodos transcurridos'],
                'rows' => $ideas
                    ->take(12)
                    ->map(static function (array $idea): array {
                        return [
                            $idea['title'],
                            $idea['status'],
                            $idea['proposal_period'],
                            $idea['entered_bank_at'],
                            (string) $idea['days_in_bank'],
                            $idea['elapsed_periods'] !== null ? (string) $idea['elapsed_periods'] : 'Sin dato',
                        ];
                    })
                    ->all(),
            ],
            'exportLabel' => 'Ideas antiguas en el banco',
        ];
    }

    protected function buildStatusRotationReportState(array $filters, ?User $user): array
    {
        $trackedStages = $this->rotationTrackedStages();
        $projects = $this->statusRotationProjectsQuery($filters, $user, $trackedStages)->get();

        $ideas = $projects
            ->map(function (Project $project): array {
                $stageLabels = $project->stageHistories
                    ->map(function ($history): string {
                        return $this->projectStageLabel(
                            $history->stage,
                            is_array($history->metadata) ? $history->metadata : null
                        );
                    })
                    ->values();

                return [
                    'project_id' => (int) $project->id,
                    'title' => $project->title,
                    'current_status' => $project->projectStatus?->name ?? 'Sin estado',
                    'state_changes_count' => (int) ($project->state_changes_count ?? $stageLabels->count()),
                    'last_movement_at' => optional($project->stageHistories->first()?->event_at)->format('d/m/Y H:i') ?? 'Sin movimientos',
                    'last_stage_label' => $stageLabels->first() ?? 'Sin movimientos registrados',
                    'recent_sequence' => $stageLabels->isNotEmpty()
                        ? $stageLabels->take(4)->implode(' -> ')
                        : 'Sin movimientos registrados',
                    'stage_labels' => $stageLabels,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [
                    $right['state_changes_count'],
                    $right['project_id'],
                ] <=> [
                    $left['state_changes_count'],
                    $left['project_id'],
                ];
            })
            ->values();

        $totalIdeas = $ideas->count();
        $totalChanges = (int) $ideas->sum('state_changes_count');
        $ideasWithMovement = $ideas->filter(static fn (array $idea): bool => $idea['state_changes_count'] > 0);
        $averageChanges = $totalIdeas > 0 ? round($totalChanges / $totalIdeas, 1) : 0.0;
        $topIdea = $ideasWithMovement->first() ?? $ideas->first();

        $topRotationRows = $ideasWithMovement->isNotEmpty()
            ? $ideasWithMovement->take(8)
            : $ideas->take(8);

        $topRotationData = $this->reportDataFromLabelCounts(
            $topRotationRows
                ->mapWithKeys(static function (array $idea): array {
                    return [$idea['title'] => $idea['state_changes_count']];
                })
                ->all()
        );

        $changeBuckets = $ideas->countBy(static function (array $idea): string {
            return match (true) {
                $idea['state_changes_count'] === 0 => 'Sin cambios',
                $idea['state_changes_count'] === 1 => '1 cambio',
                $idea['state_changes_count'] === 2 => '2 cambios',
                $idea['state_changes_count'] === 3 => '3 cambios',
                default => '4 o mas cambios',
            };
        });

        $changeBucketData = $this->reportDataFromLabelCounts([
            'Sin cambios' => (int) ($changeBuckets['Sin cambios'] ?? 0),
            '1 cambio' => (int) ($changeBuckets['1 cambio'] ?? 0),
            '2 cambios' => (int) ($changeBuckets['2 cambios'] ?? 0),
            '3 cambios' => (int) ($changeBuckets['3 cambios'] ?? 0),
            '4 o mas cambios' => (int) ($changeBuckets['4 o mas cambios'] ?? 0),
        ]);

        $stageTypeData = $this->reportDataFromLabelCounts(
            $ideas
                ->flatMap(static fn (array $idea) => $idea['stage_labels'])
                ->countBy()
                ->sortDesc()
                ->all()
        );

        $currentStatusData = $this->reportDataFromLabelCounts(
            $ideasWithMovement
                ->pluck('current_status')
                ->countBy()
                ->sortDesc()
                ->all()
        );

        return [
            'reportData' => $topRotationData,
            'segments' => $this->buildProjectReportSegments($topRotationData),
            'visuals' => [
                $this->makeProjectReportVisual(
                    'rotation-top-ideas',
                    'Ideas con mayor rotacion',
                    'Ordena las ideas segun la cantidad de cambios de estado registrados.',
                    $topRotationData,
                    'Cambios registrados',
                    'cambios'
                ),
                $this->makeProjectReportVisual(
                    'rotation-change-buckets',
                    'Distribucion por cantidad de cambios',
                    'Agrupa las ideas segun cuantas transiciones de estado han vivido.',
                    $changeBucketData,
                    'Ideas clasificadas'
                ),
                $this->makeProjectReportVisual(
                    'rotation-stage-types',
                    'Tipos de movimiento registrados',
                    'Resume cuales cambios de estado aparecen con mayor frecuencia en el conjunto filtrado.',
                    $stageTypeData,
                    'Movimientos contabilizados'
                ),
                $this->makeProjectReportVisual(
                    'rotation-current-statuses',
                    'Estado actual de las ideas con movimiento',
                    'Muestra en que estado terminaron las ideas que ya registran al menos un cambio.',
                    $currentStatusData,
                    'Ideas con cambios'
                ),
            ],
            'insights' => [
                [
                    'label' => 'Ideas analizadas',
                    'value' => (string) $totalIdeas,
                    'caption' => 'Ideas consideradas para medir rotacion y cambios de estado.',
                ],
                [
                    'label' => 'Cambios registrados',
                    'value' => (string) $totalChanges,
                    'caption' => 'Suma total de movimientos de estado encontrados en el reporte.',
                ],
                [
                    'label' => 'Idea con mayor rotacion',
                    'value' => $topIdea
                        ? "{$topIdea['title']} ({$topIdea['state_changes_count']})"
                        : 'Sin datos',
                    'caption' => 'Idea que acumula mas transiciones dentro del historial disponible.',
                ],
                [
                    'label' => 'Promedio de cambios',
                    'value' => number_format($averageChanges, 1),
                    'caption' => 'Promedio de cambios de estado por idea con los filtros actuales.',
                ],
            ],
            'table' => [
                'title' => 'Detalle de rotacion por idea',
                'description' => 'Lista las ideas con mayor numero de cambios, su estado actual y la secuencia reciente de movimientos.',
                'columns' => ['Idea', 'Cambios de estado', 'Estado actual', 'Ultimo movimiento', 'Secuencia reciente'],
                'rows' => $ideas
                    ->take(12)
                    ->map(static function (array $idea): array {
                        return [
                            $idea['title'],
                            (string) $idea['state_changes_count'],
                            $idea['current_status'],
                            $idea['last_movement_at'],
                            (string) Str::limit($idea['recent_sequence'], 180),
                        ];
                    })
                    ->all(),
            ],
            'exportLabel' => 'Ideas con mayor rotacion',
        ];
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}
     */
    protected function generateProjectDistributionReport(string $reportKey, array $filters, ?User $user): array
    {
        $query = $this->baseProjectReportQuery($user);

        match ($reportKey) {
            'projects_by_author_type' => $this->applyAuthorTypeDistribution($query),
            'projects_by_thematic_area' => $this->applyThematicAreaDistribution($query),
            'projects_by_investigation_line' => $this->applyInvestigationLineDistribution($query),
            default => $this->applyStatusDistribution($query),
        };

        $this->applyProjectReportFilters($query, $filters, $reportKey);

        $rows = $query->get();
        $categories = [];
        $values = [];

        foreach ($rows as $row) {
            $categories[] = (string) $row->category;
            $values[] = (int) $row->total;
        }

        $total = array_sum($values);
        $percentages = array_map(
            static fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0.0,
            $values
        );

        return [
            'categories' => $categories,
            'values' => $values,
            'percentages' => $percentages,
            'total' => $total,
        ];
    }

    /**
     * Build a report visual ready for the Blade charts.
     *
     * @param  array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}  $reportData
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     total_label: string,
     *     value_label: string,
     *     data: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>
     * }
     */
    protected function makeProjectReportVisual(
        string $key,
        string $title,
        string $description,
        array $reportData,
        string $totalLabel,
        string $valueLabel = 'registros'
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'total_label' => $totalLabel,
            'value_label' => $valueLabel,
            'data' => $reportData,
            'segments' => $this->buildProjectReportSegments($reportData),
        ];
    }

    /**
     * @param  iterable<string, int>  $labelCounts
     * @return array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}
     */
    protected function reportDataFromLabelCounts(iterable $labelCounts): array
    {
        $categories = [];
        $values = [];

        foreach ($labelCounts as $label => $value) {
            $categories[] = (string) $label;
            $values[] = (int) $value;
        }

        $total = array_sum($values);
        $percentages = array_map(
            static fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0.0,
            $values
        );

        return [
            'categories' => $categories,
            'values' => $values,
            'percentages' => $percentages,
            'total' => $total,
        ];
    }

    protected function baseProjectReportQuery(?User $user): Builder
    {
        $query = Project::query()->from('projects');

        if (in_array($user?->role, ['professor', 'committee_leader'], true) && $user?->professor) {
            $professorId = $user->professor->id;

            $query->whereHas('professors', static function (Builder $relation) use ($professorId) {
                $relation->where('professors.id', $professorId);
            });
        } elseif ($user?->role === 'student' && $user->student) {
            $studentId = $user->student->id;

            $query->whereHas('students', static function (Builder $relation) use ($studentId) {
                $relation->where('students.id', $studentId);
            });
        }

        return $query;
    }

    /**
     * Build the query used by the programs and lines report.
     */
    protected function programsAndLinesProjectsQuery(array $filters, ?User $user): Builder
    {
        $query = $this->baseProjectReportQuery($user)
            ->with([
                'thematicArea.investigationLine',
                'professors.cityProgram.program',
                'students.cityProgram.program',
            ])
            ->select('projects.*');

        $this->applyGenericProjectReportFilters($query, $filters);

        return $query;
    }

    /**
     * Build the base query used by the traceability report.
     *
     * @param  array<int, int>  $commentContentIds
     */
    protected function traceabilityProjectsQuery(array $filters, ?User $user, array $commentContentIds): Builder
    {
        $query = $this->baseProjectReportQuery($user)->select('projects.*');

        $this->applyGenericProjectReportFilters($query, $filters);

        return $query;
    }

    protected function oldBankIdeasProjectsQuery(array $filters, ?User $user): Builder
    {
        $query = $this->baseProjectReportQuery($user)
            ->with([
                'projectStatus',
                'proposalAcademicPeriod',
            ])
            ->select('projects.*');

        $this->applyGenericProjectReportFilters($query, $filters);
        $this->applyBankIdeasScope($query);

        return $query;
    }

    protected function statusRotationProjectsQuery(array $filters, ?User $user, array $trackedStages): Builder
    {
        $query = $this->baseProjectReportQuery($user)
            ->with([
                'projectStatus',
                'stageHistories' => static function ($relation) use ($trackedStages): void {
                    $relation
                        ->whereIn('stage', $trackedStages)
                        ->orderByDesc('event_at')
                        ->orderByDesc('id');
                },
            ])
            ->withCount([
                'stageHistories as state_changes_count' => static function ($relation) use ($trackedStages): void {
                    $relation->whereIn('stage', $trackedStages);
                },
            ])
            ->select('projects.*');

        $this->applyGenericProjectReportFilters($query, $filters);

        return $query;
    }

    protected function applyBankIdeasScope(Builder $query): void
    {
        $query
            ->whereNull('projects.assignment_academic_period_id')
            ->whereNull('projects.assigned_at')
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereNull('projects.project_status_id')
                    ->orWhereHas('projectStatus', static function (Builder $relation): void {
                        $relation->whereNotIn('name', ['Asignado', 'Rechazado', 'Descartado']);
                    });
            });
    }

    protected function applyStatusDistribution(Builder $query): void
    {
        $query
            ->leftJoin('project_statuses', 'project_statuses.id', '=', 'projects.project_status_id')
            ->selectRaw("COALESCE(project_statuses.name, 'Sin estado') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    protected function applyThematicAreaDistribution(Builder $query): void
    {
        $query
            ->leftJoin('thematic_areas', 'thematic_areas.id', '=', 'projects.thematic_area_id')
            ->selectRaw("COALESCE(thematic_areas.name, 'Sin area tematica') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    protected function applyAuthorTypeDistribution(Builder $query): void
    {
        $categoryExpression = $this->projectAuthorTypeCategoryExpression();

        $query
            ->selectRaw("{$categoryExpression} as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByRaw(
                "FIELD(category, 'Estudiante', 'Docente', 'Mixto', 'Sin autores')"
            );
    }

    protected function projectAuthorTypeCategoryExpression(): string
    {
        return <<<SQL
CASE
    WHEN EXISTS (
        SELECT 1
        FROM student_project
        WHERE student_project.project_id = projects.id
    ) AND EXISTS (
        SELECT 1
        FROM professor_project
        WHERE professor_project.project_id = projects.id
    ) THEN 'Mixto'
    WHEN EXISTS (
        SELECT 1
        FROM student_project
        WHERE student_project.project_id = projects.id
    ) THEN 'Estudiante'
    WHEN EXISTS (
        SELECT 1
        FROM professor_project
        WHERE professor_project.project_id = projects.id
    ) THEN 'Docente'
    ELSE 'Sin autores'
END
SQL;
    }

    protected function applyInvestigationLineDistribution(Builder $query): void
    {
        $query
            ->leftJoin('thematic_areas', 'thematic_areas.id', '=', 'projects.thematic_area_id')
            ->leftJoin('investigation_lines', 'investigation_lines.id', '=', 'thematic_areas.investigation_line_id')
            ->selectRaw("COALESCE(investigation_lines.name, 'Sin linea de investigacion') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    /**
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     */
    protected function applyProjectReportFilters(Builder $query, array $filters, string $reportKey): void
    {
        $this->applyGenericProjectReportFilters($query, $filters);
    }

    /**
     * Apply program and date constraints shared by the report builders.
     *
     * @param  array{report_key:string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     */
    protected function applyGenericProjectReportFilters(Builder $query, array $filters): void
    {
        if ($filters['report_program_id']) {
            $programId = $filters['report_program_id'];

            $query->where(function (Builder $builder) use ($programId): void {
                $builder
                    ->whereHas('professors.cityProgram', static function (Builder $relation) use ($programId): void {
                        $relation->where('program_id', $programId);
                    })
                    ->orWhereHas('students.cityProgram', static function (Builder $relation) use ($programId): void {
                        $relation->where('program_id', $programId);
                    });
            });
        }

        if ($filters['report_from']) {
            $from = $filters['report_from'];

            $query->where(static function (Builder $builder) use ($from): void {
                $builder
                    ->whereDate('projects.proposed_at', '>=', $from)
                    ->orWhere(static function (Builder $fallback) use ($from): void {
                        $fallback
                            ->whereNull('projects.proposed_at')
                            ->whereDate('projects.created_at', '>=', $from);
                    });
            });
        }

        if ($filters['report_to']) {
            $to = $filters['report_to'];

            $query->where(static function (Builder $builder) use ($to): void {
                $builder
                    ->whereDate('projects.proposed_at', '<=', $to)
                    ->orWhere(static function (Builder $fallback) use ($to): void {
                        $fallback
                            ->whereNull('projects.proposed_at')
                            ->whereDate('projects.created_at', '<=', $to);
                    });
            });
        }
    }

    protected function applyProgramsAndLinesSearchFilter(Builder $query, string $search): void
    {
        $term = '%' . $search . '%';

        $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('projects.title', 'like', $term)
                ->orWhereHas('thematicArea', static function (Builder $relation) use ($term): void {
                    $relation
                        ->where('name', 'like', $term)
                        ->orWhereHas('investigationLine', static function (Builder $lineQuery) use ($term): void {
                            $lineQuery->where('name', 'like', $term);
                        });
                })
                ->orWhereHas('professors.cityProgram.program', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                })
                ->orWhereHas('students.cityProgram.program', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                });
        });
    }

    protected function applyOldBankIdeasSearchFilter(Builder $query, string $search): void
    {
        $term = '%' . $search . '%';

        $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('projects.title', 'like', $term)
                ->orWhereHas('projectStatus', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                })
                ->orWhereHas('proposalAcademicPeriod', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                })
                ->orWhereHas('thematicArea', static function (Builder $relation) use ($term): void {
                    $relation
                        ->where('name', 'like', $term)
                        ->orWhereHas('investigationLine', static function (Builder $lineQuery) use ($term): void {
                            $lineQuery->where('name', 'like', $term);
                        });
                })
                ->orWhereHas('professors.cityProgram.program', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                })
                ->orWhereHas('students.cityProgram.program', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                });
        });
    }

    protected function applyStatusRotationSearchFilter(Builder $query, string $search, array $trackedStages): void
    {
        $term = '%' . $search . '%';

        $query->where(function (Builder $builder) use ($term, $trackedStages): void {
            $builder
                ->where('projects.title', 'like', $term)
                ->orWhereHas('projectStatus', static function (Builder $relation) use ($term): void {
                    $relation->where('name', 'like', $term);
                })
                ->orWhereHas('stageHistories', static function (Builder $relation) use ($term, $trackedStages): void {
                    $relation
                        ->whereIn('stage', $trackedStages)
                        ->where('notes', 'like', $term);
                });
        });
    }

    /**
     * @param  array<int, int>  $commentContentIds
     */
    protected function applyTraceabilitySearchFilter(Builder $query, string $search, array $commentContentIds): void
    {
        $term = '%' . $search . '%';

        $query->where(function (Builder $builder) use ($term, $commentContentIds): void {
            $builder
                ->where('projects.title', 'like', $term)
                ->orWhereHas('versions.contentVersions', static function (Builder $relation) use ($term, $commentContentIds): void {
                    if ($commentContentIds !== []) {
                        $relation->whereIn('content_id', $commentContentIds);
                    }

                    $relation->where('value', 'like', $term);
                })
                ->orWhereHas('stageHistories', static function (Builder $relation) use ($term): void {
                    $relation
                        ->where('stage', 'returned_for_correction')
                        ->where('notes', 'like', $term);
                });
        });
    }

    /**
     * Add the conditions that determine whether a project has visible correction comments.
     *
     * @param  array<int, int>  $commentContentIds
     */
    protected function applyCorrectionCommentsPresenceFilter(Builder $query, array $commentContentIds): void
    {
        $query
            ->whereHas('versions.contentVersions', static function (Builder $relation) use ($commentContentIds): void {
                if ($commentContentIds !== []) {
                    $relation->whereIn('content_id', $commentContentIds);
                }

                $relation->whereNotNull('value')->where('value', '!=', '');
            })
            ->orWhereHas('stageHistories', static function (Builder $relation): void {
                $relation
                    ->where('stage', 'returned_for_correction')
                    ->whereNotNull('notes')
                    ->where('notes', '!=', '');
            });
    }

    /**
     * @return array<int, string>
     */
    protected function rotationTrackedStages(): array
    {
        return ['approved', 'rejected', 'returned_for_correction', 'assigned', 'evaluated'];
    }

    protected function projectStageLabel(?string $stage, ?array $metadata = null, ?string $fallbackStatusName = null): string
    {
        $normalizedStatus = $this->normalizeStatusName(
            (string) ($metadata['final_status_name'] ?? $fallbackStatusName ?? '')
        );

        return match (true) {
            $normalizedStatus === 'asignado',
            $stage === 'assigned' => 'Asignado',
            $normalizedStatus === 'aprobado',
            $stage === 'approved' => 'Aprobado',
            $normalizedStatus === 'rechazado',
            $stage === 'rejected' => 'Rechazado',
            $normalizedStatus === 'devuelto para correccion',
            $stage === 'returned_for_correction' => 'Devuelto para correccion',
            $normalizedStatus === 'descartado' => 'Descartado',
            $stage === 'evaluated' => 'Evaluado',
            $stage === 'proposal_created' => 'Propuesta creada',
            default => 'Movimiento registrado',
        };
    }

    /**
     * Resolve the catalog ids used to store committee correction comments.
     *
     * @return array<int, int>
     */
    protected function commentContentIds(): array
    {
        return Content::query()
            ->get(['id', 'name'])
            ->filter(function (Content $content): bool {
                return $this->normalizeContentName($content->name) === 'comentarios';
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Extract correction comments from version content and fallback stage notes.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function projectCorrectionComments(Project $project)
    {
        $versionComments = $project->versions
            ->flatMap(function (Version $version) {
                return $version->contentVersions
                    ->filter(function (ContentVersion $contentVersion): bool {
                        return $this->normalizeContentName($contentVersion->content->name ?? '') === 'comentarios'
                            && trim((string) $contentVersion->value) !== '';
                    })
                    ->sortByDesc('created_at')
                    ->pluck('value');
            })
            ->map(static fn ($value): string => trim((string) $value))
            ->filter()
            ->values();

        if ($versionComments->isNotEmpty()) {
            return $versionComments;
        }

        return $project->stageHistories
            ->pluck('notes')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter()
            ->values();
    }

    /**
     * @param  array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}  $reportData
     * @return array<int, array{label: string, value: int, percentage: float, color: string}>
     */
    protected function buildProjectReportSegments(array $reportData): array
    {
        $palette = [
            '#0f766e',
            '#1d4ed8',
            '#b45309',
            '#be123c',
            '#7c3aed',
            '#0891b2',
            '#4d7c0f',
            '#c2410c',
        ];

        $segments = [];

        foreach ($reportData['categories'] as $index => $category) {
            $segments[] = [
                'label' => $category,
                'value' => $reportData['values'][$index] ?? 0,
                'percentage' => $reportData['percentages'][$index] ?? 0.0,
                'color' => $palette[$index % count($palette)],
            ];
        }

        return $segments;
    }

    /**
     * Render the selected report as a branded PDF document.
     */
    protected function downloadProjectReportPdf(array $reportState): Response
    {
        $reportModules = $this->projectReportModules();
        $reportKey = (string) $reportState['reportKey'];
        $reportLabel = (string) $reportState['reportLabel'];
        $generatedAt = now();

        $pdf = Pdf::loadView('projects.report-pdf', [
            'reportTitle' => $reportLabel,
            'reportDescription' => $reportModules[$reportKey]['description'] ?? 'Distribucion de proyectos.',
            'reportKey' => $reportKey,
            'reportGeneratedAt' => $generatedAt,
            'reportInsights' => $reportState['insights'] ?? [],
            'reportVisuals' => $reportState['visuals'] ?? [],
            'reportTable' => $reportState['table'] ?? null,
            'reportFiltersSummary' => $this->projectReportFiltersSummary($reportState['filters'] ?? []),
            'logoDataUri' => $this->publicAssetDataUri('assets/tablar-logo.png'),
        ])
            ->setOptions([
                'debugLayout' => false,
                'debugLayoutLines' => false,
                'debugLayoutBlocks' => false,
                'debugLayoutInline' => false,
                'debugLayoutPaddingBox' => false,
                'debugCss' => false,
                'debugKeepTemp' => false,
                'debugText' => false,
            ])
            ->setPaper('a4', 'landscape');

        $filename = sprintf(
            'reporte-%s-%s.pdf',
            Str::slug($reportKey),
            $generatedAt->format('Ymd-His')
        );

        return $pdf->download($filename);
    }

    /**
     * Summarize the report filters for CSV/PDF consumers.
     *
     * @param  array{report_key?:string,report_from?:?string,report_to?:?string,report_program_id?:?int}  $filters
     * @return array<int, string>
     */
    protected function projectReportFiltersSummary(array $filters): array
    {
        $summary = [];

        if (! empty($filters['report_from'])) {
            $summary[] = 'Desde: ' . Carbon::parse((string) $filters['report_from'])->format('d/m/Y');
        }

        if (! empty($filters['report_to'])) {
            $summary[] = 'Hasta: ' . Carbon::parse((string) $filters['report_to'])->format('d/m/Y');
        }

        if (! empty($filters['report_program_id'])) {
            $programName = Program::query()->whereKey((int) $filters['report_program_id'])->value('name');

            if ($programName) {
                $summary[] = 'Programa: ' . $programName;
            }
        }

        if ($summary === []) {
            $summary[] = 'Sin filtros adicionales.';
        }

        return $summary;
    }

    /**
     * Convert a public asset into a base64 data URI so Dompdf can embed it reliably.
     */
    protected function publicAssetDataUri(string $relativePath): ?string
    {
        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $mimeType = mime_content_type($absolutePath) ?: 'image/png';
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    /**
     * Ensure the current user is allowed to interact with the projects module.
     *
     * @return array{0:\App\Models\User|null,1:bool,2:bool,3:bool,4:bool}
     */
    protected function ensureRoleAccess(bool $allowResearchStaff = false): array
    {
        $user = AuthUserHelper::fullUser();
        $isProfessor = in_array($user?->role, ['professor', 'committee_leader'], true);
        $isStudent = $user?->role === 'student';
        $isResearchStaff = $user?->role === 'research_staff';
        $isCommitteeLeader = $user?->role === 'committee_leader';

        if (! $isProfessor && ! $isStudent && ! ($allowResearchStaff && $isResearchStaff)) {
            abort(403, 'Esta acción solo está disponible para docentes, líderes de comité o estudiantes.');
        }

        return [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader];
    }

    /**
     * Show the form used to create a new project idea.
     */
    public function create(): View|RedirectResponse
    {
        [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader] = $this->ensureRoleAccess(true);
        $activeProfessor = $this->resolveProfessorProfile($user);

        if ($isResearchStaff) {
            abort(403, 'El personal de investigación no puede crear ideas de proyecto.');
        }

        if (! AcademicCalendarService::isProcessWindowOpen(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
            );
        }

        $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();

        if (! $proposalWindow || ! $activeAcademicPeriod) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
            );
        }

        if ($isProfessor) {
            $researchGroupId = $activeProfessor?->cityProgram?->program?->research_group_id;
        } else {
            $student = $user->student;
            $studentAcademicProgress = app(StudentAcademicProgressService::class);

            if (! $studentAcademicProgress->canCreateProposal($student, $activeAcademicPeriod)) {
                return redirect()
                    ->route('projects.index')
                    ->with('warning', $studentAcademicProgress->blockedProposalMessage($student, $activeAcademicPeriod));
            }

            $researchGroupId = $student?->cityProgram?->program?->research_group_id;
        }

        $cities = City::query()->orderBy('name')->get();
        $programs = Program::query()->with('researchGroup')->orderBy('name')->get();
        $investigationLines = InvestigationLine::where('research_group_id', $researchGroupId)
            ->whereNull('deleted_at')
            ->get();
        $thematicAreas = ThematicArea::query()->orderBy('name')->get();

        $year = now()->year;

        $frameworks = Framework::with('contentFrameworks')
            ->where('start_year', '<=', $year)
            ->where('end_year', '>=', $year)
            ->orderBy('name')
            ->get();

        $prefill = [
            'delivery_date' => Carbon::now()->format('Y-m-d'),
        ];

        $availableStudents = collect();
        $availableProfessors = collect();
        $ideaBalanceRecommendations = null;

        if ($isProfessor) {
            $professor = $activeProfessor;
            if (! $professor) {
                abort(403, 'Se requiere un perfil docente para enviar propuestas.');
            }

            $prefill = array_merge($prefill, [
                'first_name' => $professor->name,
                'last_name' => $professor->last_name,
                'email' => $professor->mail ?? $user->email,
                'phone' => $professor->phone,
                'city_id' => optional($professor->cityProgram)->city_id,
                'program_id' => optional($professor->cityProgram)->program_id,
            ]);

            $availableProfessors = $this->participantQuery($professor->id)
                ->get()
                ->map(fn (Professor $participant) => $this->presentParticipant($participant));

            $ideaBalanceRecommendations = app(TeacherIdeaBalanceService::class)->recommendationsForUser($user);
        } else {
            $student = $user->student;
            if (! $student) {
                abort(403, 'Se requiere un perfil de estudiante para enviar propuestas.');
            }

            $cityProgram = $student->cityProgram;
            $program = $cityProgram?->program;
            $researchGroup = $program?->researchGroup;

            $prefill = array_merge($prefill, [
                'first_name' => $student->name,
                'last_name' => $student->last_name,
                'card_id' => $student->card_id,
                'email' => $user->email,
                'phone' => $student->phone,
                'city_id' => $cityProgram?->city_id,
                'program_id' => $program?->id,
                'research_group' => $researchGroup?->name,
            ]);

            $availableStudents = Student::query()
                ->where('city_program_id', $student->city_program_id)
                ->where('id', '!=', $student->id)
                ->whereDoesntHave('projects', function ($q) {
                    $q->whereHas('projectStatus', fn ($s) => $s->whereNotIn('name', Student::INACTIVE_PROJECT_STATUSES));
                })
                ->orderBy('last_name')
                ->orderBy('name')
                ->get();
        }

        return view('projects.create', [
            'cities' => $cities,
            'programs' => $programs,
            'investigationLines' => $investigationLines,
            'thematicAreas' => $thematicAreas,
            'frameworks' => $frameworks,
            'prefill' => $prefill,
            'isProfessor' => $isProfessor,
            'isStudent' => $isStudent,
            'isCommitteeLeader' => $isCommitteeLeader,
            'availableStudents' => $availableStudents,
            'availableProfessors' => $availableProfessors,
            'activeAcademicPeriod' => $activeAcademicPeriod,
            'proposalWindow' => $proposalWindow,
            'ideaBalanceRecommendations' => $ideaBalanceRecommendations,
        ]);
    }

    /**
     * Persist a new project idea following the role specific business rules.
     */
    public function store(Request $request): View|RedirectResponse
    {
        [$user, $isProfessor, $isStudent, $isResearchStaff] = $this->ensureRoleAccess(true);

        try {
            if ($isResearchStaff) {
                abort(403, 'El personal de investigación no puede crear ideas de proyecto.');
            }

            if (! AcademicCalendarService::isProcessWindowOpen(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)) {
                return view(
                    'academic-calendar.unavailable',
                    AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
                );
            }

            $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();
            $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);

            if (! $activeAcademicPeriod || ! $proposalWindow) {
                return view(
                    'academic-calendar.unavailable',
                    AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
                );
            }

            if ($isProfessor) {
                $professorProfile = $this->resolveProfessorProfile($user);

                return $this->persistProfessorProject($request, $professorProfile, null, $activeAcademicPeriod, $proposalWindow);
            }

            $studentAcademicProgress = app(StudentAcademicProgressService::class);

            if (! $studentAcademicProgress->canCreateProposal($user->student, $activeAcademicPeriod)) {
                return redirect()
                    ->route('projects.index')
                    ->with('warning', $studentAcademicProgress->blockedProposalMessage($user->student, $activeAcademicPeriod));
            }

            return $this->persistStudentProject($request, $user->student, null, $activeAcademicPeriod, $proposalWindow);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Failed to register project idea.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', app()->environment('local')
                    ? $exception->getMessage()
                    : 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.');
        }
    }

    /**
     * Display the details of a project, including its latest version.
     */
    public function show(Project $project): View
    {
        $project->load([
            'thematicArea.investigationLine',
            'projectStatus',
            'professors.user',
            'professors.cityProgram.program',
            'students',
            'contentFrameworks.framework',
            'versions' => static fn ($relation) => $relation
                ->with(['contentVersions.content'])
                ->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions->first();
        $contentValues = $this->mapContentValues($latestVersion);

        $normalizedStatus = $this->normalizeStatusName($project->projectStatus->name ?? '');
        $reviewComment = null;

        if ($normalizedStatus === 'devuelto para correccion' && $latestVersion) {
            $reviewContent = $latestVersion->contentVersions
                ->first(static function (ContentVersion $contentVersion): bool {
                    return Str::lower($contentVersion->content->name ?? '') === 'comentarios';
                });

            $reviewComment = $reviewContent?->value;
        }

        $user = AuthUserHelper::fullUser();
        $statusName = $project->projectStatus->name ?? 'Sin estado';
        $canEdit = $this->isReturnedForCorrection($project);

        return view('projects.show', [
            'project' => $project,
            'latestVersion' => $latestVersion,
            'contentValues' => $contentValues,
            'frameworksSelected' => $project->contentFrameworks,
            'isProfessor' => in_array($user?->role, ['professor', 'committee_leader'], true),
            'isStudent' => $user?->role === 'student',
            'isCommitteeLeader' => $user?->role === 'committee_leader',
            'isResearchStaff' => $user?->role === 'research_staff',
            'reviewComment' => $reviewComment,
            'canEdit' => $canEdit,
            'statusName' => $statusName,
            'canViewVersionHistory' => $this->canViewVersionHistory($project, $user),
        ]);
    }

    /**
     * Provide an AJAX friendly list of professors and committee leaders to associate with a project.
     */
    public function participants(Request $request): JsonResponse
    {
        [$user, $isProfessor] = $this->ensureRoleAccess();

        if (! $isProfessor) {
            abort(403, 'Solo los docentes y líderes de comité pueden consultar participantes.');
        }

        $requestedIds = collect($request->input('ids', []))
            ->filter(static fn ($id) => is_numeric($id))
            ->map(static fn ($id) => (int) $id)
            ->unique();

        if ($requestedIds->isNotEmpty()) {
            $prefetched = $this->participantQuery(null)
                ->whereIn('professors.id', $requestedIds)
                ->get();

            return response()->json([
                'data' => $prefetched
                    ->map(fn (Professor $professor) => $this->presentParticipant($professor))
                    ->values(),
                'meta' => null,
            ]);
        }

        $activeProfessor = $this->resolveProfessorProfile($user);
        $excludeId = $activeProfessor?->id;
        $term = trim((string) $request->input('q', ''));

        $query = $this->participantQuery($excludeId);

        $programFilter = $request->input('program_id');
        if ($programFilter !== null && $programFilter !== '') {
            $programId = (int) $programFilter;

            $query->whereHas('cityProgram', static function (Builder $builder) use ($programId) {
                $builder->where('program_id', $programId);
            });
        }

        if ($term !== '') {
            $normalizedTerm = mb_strtolower($term);

            $query->where(static function (Builder $builder) use ($normalizedTerm, $term) {
                $builder->whereRaw('LOWER(professors.name) like ?', ["%{$normalizedTerm}%"])
                    ->orWhereRaw('LOWER(professors.last_name) like ?', ["%{$normalizedTerm}%"])
                    ->orWhere('professors.card_id', 'like', "%{$term}%")
                    ->orWhereRaw('LOWER(professors.mail) like ?', ["%{$normalizedTerm}%"])
                    ->orWhereHas('user', static function (Builder $userQuery) use ($normalizedTerm) {
                        $userQuery->whereRaw('LOWER(email) like ?', ["%{$normalizedTerm}%"]);
                    });
            });
        }

        $participants = $query->get();

        return response()->json([
            'data' => $participants
                ->map(fn (Professor $professor) => $this->presentParticipant($professor))
                ->values(),
            'meta' => null,
        ]);
    }

    /**
     * Render a simple view that consumes the JSON endpoint to list participants.
     */
    public function participantsPage(): View
    {
        [, $isProfessor] = $this->ensureRoleAccess();

        if (! $isProfessor) {
            abort(403);
        }

        $programs = Program::orderBy('name')->get();

        return view('participants.index', [
            'programs' => $programs,
        ]);
    }

    /**
     * Build the base query for participants, optionally excluding the authenticated profile.
     */
    protected function participantQuery(?int $excludeProfessorId = null): Builder
    {
        return Professor::query()
            ->select('professors.*')
            ->with(['user', 'cityProgram.program', 'cityProgram.city'])
            ->whereHas('user', static function (Builder $builder) {
                $builder->whereIn('role', ['professor', 'committee_leader', 'committe_leader']);
            })
            ->whereNull('professors.deleted_at')
            ->when($excludeProfessorId, static function (Builder $builder, int $exclude) {
                $builder->where('professors.id', '!=', $exclude);
            })
            ->orderBy('professors.last_name')
            ->orderBy('professors.name');
    }

    /**
     * Normalize the participant payload so the Blade and JS layers consume the same shape.
     */
    protected function presentParticipant(Professor $professor): array
    {
        return [
            'id' => $professor->id,
            'name' => trim(($professor->name ?? '') . ' ' . ($professor->last_name ?? '')),
            'document' => $professor->card_id,
            'email' => $professor->mail ?? $professor->user?->email,
            'program' => optional($professor->cityProgram?->program)->name,
            'program_id' => $professor->cityProgram?->program_id,
            'program_city' => optional($professor->cityProgram?->city)->name,
        ];
    }

    /**
     * Resolve the professor profile associated with the authenticated user.
     */
    protected function resolveProfessorProfile(?User $user): ?Professor
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('professor') || array_key_exists('professor', $user->getRelations())) {
            if ($user->professor) {
                return $user->professor;
            }
        }

        return Professor::query()->where('user_id', $user->id)->first();
    }

    /**
     * Display the edit form with the existing project information.
     */
    public function edit(Project $project): View
    {
        $statusName = $this->normalizeStatusName($project->projectStatus->name ?? '');

        if ($statusName === 'pendiente de aprobacion') {
            abort(403, 'Los proyectos pendientes de aprobación no se pueden editar.');
        }

        if (! $this->isReturnedForCorrection($project)) {
            abort(403, 'Solo los proyectos devueltos para correccion pueden ser editados.');
        }

        [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader] = $this->ensureRoleAccess(true);
        $activeProfessor = $this->resolveProfessorProfile($user);
        $this->authorizeProjectAccess($project, $user->id, $isProfessor, $isStudent, $isResearchStaff);

        if ($isResearchStaff) {
            abort(403, 'El personal de investigaciones no puede editar proyectos.');
        }

        if ($isProfessor) {
            $researchGroupId = $activeProfessor?->cityProgram?->program?->research_group_id;
        } else {
            $researchGroupId = $user->student?->cityProgram?->program?->research_group_id;
        }

        $project->load([
            'thematicArea',
            'professors',
            'students',
            'versions' => static fn ($relation) => $relation
                ->with(['contentVersions.content'])
                ->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions->first();
        $contentValues = $this->mapContentValues($latestVersion);

        $versionComment = null;
        if ($latestVersion) {
            $commentContent = $latestVersion->contentVersions->first(function ($cv) {
                return $this->normalizeContentName($cv->content->name ?? '') === 'comentarios';
            });

            $versionComment = $commentContent->value ?? null;
        }

        $cities = City::query()->orderBy('name')->get();
        $programs = Program::query()->with('researchGroup')->orderBy('name')->get();
        $investigationLines = InvestigationLine::where('research_group_id', $researchGroupId)
            ->whereNull('deleted_at')
            ->get();
        $thematicAreas = ThematicArea::query()->orderBy('name')->get();
        $selectedInvestigationLineId = $project->thematicArea->investigation_line_id ?? null;
        $selectedThematicAreaId = $project->thematic_area_id ?? null;

        $prefill = [
            'delivery_date' => Carbon::now()->format('Y-m-d'),
        ];

        $availableStudents = collect();
        $availableProfessors = collect();
        $frameworks = collect();
        $selectedContentFrameworkIds = [];

        $hasProfessorParticipants = $project->professors->isNotEmpty();
        $hasStudentParticipants = $project->students->isNotEmpty();

        $useProfessorForm = $isProfessor || ($isResearchStaff && $hasProfessorParticipants);
        $useStudentForm = $isStudent || ($isResearchStaff && ! $hasProfessorParticipants && $hasStudentParticipants);

        if ($useProfessorForm) {
            $contextProfessor = $isProfessor ? $activeProfessor : $project->professors->first();
            if (! $contextProfessor) {
                abort(403, 'Se requiere un perfil docente para editar propuestas.');
            }

            $prefill = array_merge($prefill, [
                'first_name' => $contextProfessor->name,
                'last_name' => $contextProfessor->last_name,
                'email' => $contextProfessor->mail ?? $contextProfessor->user?->email,
                'phone' => $contextProfessor->phone,
                'city_id' => optional($contextProfessor->cityProgram)->city_id,
                'program_id' => optional($contextProfessor->cityProgram)->program_id,
            ]);

            $frameworks = Framework::with('contentFrameworks')
                ->where('end_year', '>=', now()->year)
                ->orderBy('name')
                ->get();

            $selectedContentFrameworkIds = $project
                ->contentFrameworkProjects()
                ->pluck('content_framework_id')
                ->toArray();

            $availableProfessors = $this->participantQuery(optional($contextProfessor)->id)
                ->get()
                ->map(fn (Professor $participant) => $this->presentParticipant($participant));
        } elseif ($useStudentForm) {
            $contextStudent = $isStudent ? $user->student : $project->students->first();
            if (! $contextStudent) {
                abort(403, 'Se requiere un perfil de estudiante para editar propuestas.');
            }

            $cityProgram = $contextStudent->cityProgram;
            $program = $cityProgram?->program;
            $researchGroup = $program?->researchGroup;

            $frameworks = Framework::with('contentFrameworks')
                ->where('end_year', '>=', now()->year)
                ->orderBy('name')
                ->get();

            $selectedContentFrameworkIds = $project
                ->contentFrameworkProjects()
                ->pluck('content_framework_id')
                ->toArray();

            $prefill = array_merge($prefill, [
                'first_name' => $contextStudent->name,
                'last_name' => $contextStudent->last_name,
                'card_id' => $contextStudent->card_id,
                'email' => $contextStudent->user?->email,
                'phone' => $contextStudent->phone,
                'city_id' => $cityProgram?->city_id,
                'program_id' => $program?->id,
                'research_group' => $researchGroup?->name,
            ]);

            $availableStudents = Student::query()
                ->whereHas('projects', function ($query) use ($project) {
                    $query->where('project_id', $project->id);
                })
                ->where('id', '!=', $contextStudent->id)
                ->orderBy('last_name')
                ->orderBy('name')
                ->get();
        } else {
            abort(403, 'Se requiere ser participante del proyecto para editar esta propuesta.');
        }

        return view('projects.edit', [
            'project' => $project,
            'cities' => $cities,
            'programs' => $programs,
            'investigationLines' => $investigationLines,
            'thematicAreas' => $thematicAreas,
            'prefill' => $prefill,
            'contentValues' => $contentValues,
            'isProfessor' => $useProfessorForm,
            'isStudent' => $useStudentForm,
            'isCommitteeLeader' => $isCommitteeLeader,
            'isResearchStaff' => $isResearchStaff,
            'availableStudents' => $availableStudents,
            'availableProfessors' => $availableProfessors,
            'frameworks' => $frameworks,
            'selectedContentFrameworkIds' => $selectedContentFrameworkIds,
            'selectedInvestigationLineId' => $selectedInvestigationLineId,
            'selectedThematicAreaId' => $selectedThematicAreaId,
            'versionComment' => $versionComment,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the project information by creating a new version with the submitted content.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $statusName = $this->normalizeStatusName($project->projectStatus->name ?? '');

        if ($statusName === 'pendiente de aprobacion') {
            abort(403, 'Los proyectos pendientes de aprobación no se pueden editar.');
        }

        if (! $this->isReturnedForCorrection($project)) {
            abort(403, 'Solo los proyectos devueltos para correccion pueden ser editados.');
        }

        [$user, $isProfessor, $isStudent, $isResearchStaff] = $this->ensureRoleAccess(true);
        $this->authorizeProjectAccess($project, $user->id, $isProfessor, $isStudent, $isResearchStaff);

        $project->loadMissing(['professors', 'students']);

        try {
            if ($isProfessor) {
                return $this->persistProfessorProject($request, $this->resolveProfessorProfile($user), $project);
            }

            if ($isStudent) {
                return $this->persistStudentProject($request, $user->student, $project);
            }

            if ($isResearchStaff) {
                abort(403, 'Pidele al creador del proyecto que lo edite y envie a revision de nuevo.');
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Failed to update project idea.', [
                'project_id' => $project->id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', app()->environment('local')
                    ? $exception->getMessage()
                    : 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.');
        }
    }

    /**
     * Guard access to edit/update operations ensuring the user participates in the project.
     */
    protected function authorizeProjectAccess(Project $project, int $userId, bool $isProfessor, bool $isStudent, bool $isResearchStaff): void
    {
        if ($isResearchStaff) {
            return;
        }

        if ($isProfessor) {
            $user = AuthUserHelper::fullUser();
            $professor = $this->resolveProfessorProfile($user);

            if (! $professor || ! $project->professors->contains('id', $professor->id)) {
                abort(403, 'No estás asignado a este proyecto.');
            }
        } elseif ($isStudent) {
            $user = AuthUserHelper::fullUser();
            $student = $user->student;

            if (! $student || ! $project->students->contains('id', $student->id)) {
                abort(403, 'No estás asignado a este proyecto.');
            }
        } else {
            abort(403, 'Acceso no autorizado.');
        }
    }

    /**
     * Normalize a project title using the same rules as the Project model mutator.
     */
    protected function normalizeTitle(string $title): string
    {
        return Str::of($title)->squish()->title()->toString();
    }

    /**
     * Spanish validation messages for project idea forms.
     */
    protected function projectValidationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'email' => 'El campo :attribute debe ser un correo electrónico válido.',
            'array' => 'El campo :attribute debe ser una lista válida.',
            'exists' => 'El valor seleccionado en :attribute no es válido.',
            'in' => 'El valor seleccionado en :attribute no es válido.',
            'max' => 'El campo :attribute no debe ser mayor que :max.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'unique' => 'El valor ingresado en :attribute ya está registrado.',
            'associated_professors.*.exists' => 'El profesor seleccionado no existe o está inactivo.',
            'teammate_ids.*.exists' => 'El compañero seleccionado no es válido para este proyecto.',
            'content_frameworks.*.required' => 'Debes seleccionar al menos un contenido del marco.',
            'content_frameworks.*.exists' => 'El contenido del marco seleccionado no es válido.',
        ];
    }

    /**
     * Human-readable Spanish names for project idea form fields.
     */
    protected function projectValidationAttributes(): array
    {
        return [
            'city_id' => 'ciudad',
            'program_id' => 'programa académico',
            'investigation_line_id' => 'línea de investigación',
            'thematic_area_id' => 'área temática',
            'title' => 'título del proyecto',
            'evaluation_criteria' => 'criterios de evaluación',
            'students_count' => 'cantidad de estudiantes',
            'execution_time' => 'tiempo de ejecución',
            'viability' => 'viabilidad',
            'relevance' => 'pertinencia',
            'teacher_availability' => 'disponibilidad de docentes',
            'title_objectives_quality' => 'calidad entre título y objetivo',
            'general_objective' => 'objetivo general',
            'description' => 'descripción',
            'contact_first_name' => 'nombres del contacto',
            'contact_last_name' => 'apellidos del contacto',
            'contact_email' => 'correo del contacto',
            'contact_phone' => 'teléfono del contacto',
            'associated_professors' => 'profesores asociados',
            'associated_professors.*' => 'profesor asociado',
            'content_frameworks' => 'contenidos del marco',
            'content_frameworks.*' => 'contenido del marco',
            'teammate_ids' => 'compañeros',
            'teammate_ids.*' => 'compañero',
            'student_first_name' => 'nombres del estudiante',
            'student_last_name' => 'apellidos del estudiante',
            'student_card_id' => 'documento del estudiante',
            'student_email' => 'correo del estudiante',
            'student_phone' => 'teléfono del estudiante',
        ];
    }

    /**
     * Retrieve the content identifier by name and cache the lookup.
     */
    protected function contentId(string $name): int
    {
        $normalizedName = $this->normalizeContentName($name);

        if (empty($this->contentCache)) {
            $this->contentCache = Content::query()
                ->get(['id', 'name'])
                ->mapWithKeys(function (Content $content) {
                    return [$this->normalizeContentName($content->name) => $content->id];
                })
                ->toArray();
        }

        if (! array_key_exists($normalizedName, $this->contentCache)) {
            throw new \RuntimeException("Content '{$name}' not found in catalog.");
        }

        return $this->contentCache[$normalizedName];
    }

    /**
     * Resolve the identifier for the status representing "waiting evaluation".
     */
    protected function waitingEvaluationStatusId(): int
    {
        if ($this->waitingStatusId !== null) {
            return $this->waitingStatusId;
        }

        $status = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobacion'])
            ->orderByRaw("CASE WHEN name = 'waiting evaluation' THEN 0 ELSE 1 END")
            ->first();

        if (! $status) {
            throw new \RuntimeException('El estado de pendiente de evaluación no existe en el catálogo.');
        }

        $this->waitingStatusId = $status->id;

        return $this->waitingStatusId;
    }

    /**
     * Map the content values for the provided version into a keyed collection.
     *
     * @return array<string, string>
     */
    protected function mapContentValues(?Version $version): array
    {
        if (! $version) {
            return [];
        }

        return $version->contentVersions
            ->filter(static fn (ContentVersion $contentVersion) => $contentVersion->content !== null)
            ->mapWithKeys(function (ContentVersion $contentVersion) {
                return [$this->contentDisplayName($contentVersion->content->name) => $contentVersion->value];
            })
            ->toArray();
    }

    /**
     * Persist the project data for a professor either creating or updating a record.
     */
    protected function persistProfessorProject(
        Request $request,
        ?Professor $professor,
        ?Project $project = null,
        ?AcademicPeriod $activeAcademicPeriod = null,
        ?AcademicProcessWindow $proposalWindow = null
    ): RedirectResponse
    {
        if (! $professor) {
            abort(403, 'Se requiere un perfil docente para completar esta acción.');
        }

        $assignedProgramId = optional($professor->cityProgram)->program_id;

        if (! $assignedProgramId) {
            abort(403, 'Debes tener un programa asignado antes de enviar proyectos.');
        }

        $request->merge(['program_id' => $assignedProgramId]);

        $baseRules = [
            'city_id' => ['required', 'exists:cities,id'],
            'program_id' => ['required', 'integer', Rule::in([$assignedProgramId])],
            'investigation_line_id' => ['required', 'exists:investigation_lines,id'],
            'thematic_area_id' => [
                'required',
                Rule::exists('thematic_areas', 'id')->where(fn ($query) => $query->where('investigation_line_id', $request->integer('investigation_line_id'))),
            ],
            'title' => ['required', 'string', 'max:255'],
            'evaluation_criteria' => ['required', 'string'],
            'students_count' => ['required', 'integer', 'min:1', 'max:3'],
            'execution_time' => ['required', 'string', 'max:255'],
            'viability' => ['required', 'string'],
            'relevance' => ['required', 'string'],
            'teacher_availability' => ['required', 'string'],
            'title_objectives_quality' => ['required', 'string'],
            'general_objective' => ['required', 'string'],
            'description' => ['required', 'string'],
            'contact_first_name' => ['required', 'string', 'max:50'],
            'contact_last_name' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'associated_professors' => ['nullable', 'array'],
            'associated_professors.*' => ['integer', Rule::exists('professors', 'id')->whereNull('deleted_at')],
            'content_frameworks' => ['required', 'array'],
            'content_frameworks.*' => ['required', Rule::exists('content_frameworks', 'id')],
        ];

        $validated = $request->validate(
            $baseRules,
            $this->projectValidationMessages(),
            $this->projectValidationAttributes()
        );
        $isUpdate = $project !== null;
        $normalizedTitle = $this->normalizeTitle($validated['title']);

        $professorIds = collect($validated['associated_professors'] ?? [])
            ->filter(static fn ($id) => $id !== null)
            ->push($professor->id)
            ->unique()
            ->values()
            ->all();

        $sortedProfessorIds = $professorIds;
        sort($sortedProfessorIds);

        $duplicateProject = Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedProfessorIds) {
                $existingProfessorIds = $existing->professors()->pluck('professors.id')->sort()->values()->all();

                return $existingProfessorIds === $sortedProfessorIds;
            });

        if ($duplicateProject) {
            return back()
                ->withInput()
                ->with('error', 'Ya existe un proyecto con el mismo título y el mismo equipo de docentes.');
        }

        $activeAcademicPeriod ??= AcademicCalendarService::currentActivePeriodOrFail();
        $proposalWindow ??= AcademicCalendarService::ensureProcessWindowOpenOrFail(
            AcademicProcessWindow::PROCESS_IDEA_PROPOSAL
        );

        DB::beginTransaction();

        try {
            $professor->fill([
                'phone' => $validated['contact_phone'],
            ])->save();

            if ($project) {
                $project->fill([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => $validated['evaluation_criteria'],
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                ])->save();
            } else {
                $project = Project::create([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => $validated['evaluation_criteria'],
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                    'proposal_academic_period_id' => $activeAcademicPeriod->id,
                    'proposed_at' => now(),
                ]);
            }

            $project->professors()->sync($professorIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $contentMap = [
                'Titulo' => $project->title,
                'Cantidad de estudiantes' => (string) $validated['students_count'],
                'Tiempo de ejecucion' => $validated['execution_time'],
                'Viabilidad' => $validated['viability'],
                'Pertinencia con el grupo de investigacion y con el programa' => $validated['relevance'],
                'Disponibilidad de docentes para su direccion y calificacion' => $validated['teacher_availability'],
                'Calidad y correspondencia entre titulo y objetivo' => $validated['title_objectives_quality'],
                'Objetivo general del proyecto' => $validated['general_objective'],
                'Descripcion del proyecto de investigacion' => $validated['description'],
            ];

            $this->storeProjectVersion($project, $contentMap, $professor->user_id);

            if (! $isUpdate) {
                AcademicCalendarService::recordProjectStage(
                    $project,
                    'proposal_created',
                    $activeAcademicPeriod,
                    $professor->user_id,
                    'Proyecto propuesto por profesor.',
                    ['proposal_window_id' => $proposalWindow->id]
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'La idea de proyecto fue actualizada y quedó pendiente de evaluación.'
            : 'La idea de proyecto fue registrada y quedó pendiente de evaluación.';

        return redirect()
            ->route('projects.index')
            ->with('success', $message);
    }

    /**
     * Persist the project data for a student either creating or updating a record.
     */
    protected function persistStudentProject(
        Request $request,
        ?Student $student,
        ?Project $project = null,
        ?AcademicPeriod $activeAcademicPeriod = null,
        ?AcademicProcessWindow $proposalWindow = null
    ): RedirectResponse
    {
        if (! $student) {
            abort(403, 'Se requiere un perfil de estudiante para completar esta acción.');
        }

        $baseRules = [
            'city_id' => ['required', 'exists:cities,id'],
            'investigation_line_id' => ['required', 'exists:investigation_lines,id'],
            'thematic_area_id' => [
                'required',
                Rule::exists('thematic_areas', 'id')->where(fn ($query) => $query->where('investigation_line_id', $request->integer('investigation_line_id'))),
            ],
            'title' => ['required', 'string', 'max:255'],
            'general_objective' => ['required', 'string'],
            'description' => ['required', 'string'],
            'teammate_ids' => ['nullable', 'array', 'max:2'],
            'teammate_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where(static function ($query) use ($student) {
                    $query->where('city_program_id', $student->city_program_id);
                }),
            ],
            'student_first_name' => ['required', 'string', 'max:50'],
            'student_last_name' => ['required', 'string', 'max:50'],
            'student_card_id' => [
                'required',
                'string',
                'max:25',
                Rule::unique('students', 'card_id')->ignore($student->id),
            ],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['nullable', 'string', 'max:20'],
            'content_frameworks' => ['required', 'array'],
            'content_frameworks.*' => ['required', Rule::exists('content_frameworks', 'id')],
        ];

        $validated = $request->validate($baseRules, [
            'investigation_line_id.required' => 'Debes seleccionar una línea de investigación.',
            'thematic_area_id.required' => 'Debes seleccionar un área temática.',
            'title.required' => 'El título del proyecto es obligatorio.',
            'general_objective.required' => 'El objetivo general es obligatorio.',
            'description.required' => 'La descripción del proyecto es obligatoria.',
            'content_frameworks.required' => 'Debes completar la selección de marcos.',
            'content_frameworks.*.required' => 'Debes seleccionar una opción en cada marco.',
        ]);
        $isUpdate = $project !== null;

        if (! empty($validated['teammate_ids'])) {
            $hasOtherProjects = Student::query()
                ->whereIn('id', $validated['teammate_ids'])
                ->whereHas('projects', function ($query) use ($project) {
                    $query->where('project_id', '!=', $project?->id)
                        ->whereHas('projectStatus', function ($statusQuery) {
                            $statusQuery->whereNotIn('name', Student::INACTIVE_PROJECT_STATUSES);
                        });
                })
                ->exists();

            if ($hasOtherProjects) {
                return back()
                    ->withInput()
                    ->with('error', 'Uno o mas companeros seleccionados ya tienen un proyecto registrado.');
            }
        }

        $cityProgram = $student->cityProgram;
        if ($cityProgram && (int) $validated['city_id'] !== (int) $cityProgram->city_id) {
            return back()
                ->withInput()
                ->with('error', 'The selected city does not match your program assignment.');
        }

        $normalizedTitle = $this->normalizeTitle($validated['title']);
        $studentIds = collect($validated['teammate_ids'] ?? [])
            ->push($student->id)
            ->unique()
            ->values()
            ->all();

        $sortedStudentIds = $studentIds;
        sort($sortedStudentIds);

        if (count($studentIds) > 3) {
            return back()
                ->withInput()
                ->with('error', 'A project can only have up to 3 participating students.');
        }

        $activeStatusIds = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobacion'])
            ->pluck('id');

        $hasActive = $student->projects()
            ->when($project, static fn ($query) => $query->where('projects.id', '!=', $project->id))
            ->whereIn('project_status_id', $activeStatusIds)
            ->exists();

        if ($hasActive) {
            return back()
                ->withInput()
                ->with('error', 'You already have a project idea waiting evaluation.');
        }

        $duplicateProject = Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedStudentIds) {
                $existingStudentIds = $existing->students()->pluck('students.id')->sort()->values()->all();

                return $existingStudentIds === $sortedStudentIds;
            });

        if ($duplicateProject) {
            return back()
                ->withInput()
                ->with('error', 'A project with the same title and student team already exists.');
        }

        $activeAcademicPeriod ??= AcademicCalendarService::currentActivePeriodOrFail();
        $proposalWindow ??= AcademicCalendarService::ensureProcessWindowOpenOrFail(
            AcademicProcessWindow::PROCESS_IDEA_PROPOSAL
        );

        DB::beginTransaction();

        try {
            $student->fill([
                'phone' => $validated['student_phone'],
            ])->save();

            if ($project) {
                $project->fill([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => null,
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                ])->save();
            } else {
                $project = Project::create([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => null,
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                    'proposal_academic_period_id' => $activeAcademicPeriod->id,
                    'proposed_at' => now(),
                ]);
            }

            $project->students()->sync($studentIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $contentMap = [
                'Titulo' => $project->title,
                'Objetivo general del proyecto' => $validated['general_objective'],
                'Descripcion del proyecto de investigacion' => $validated['description'],
            ];

            $this->storeProjectVersion($project, $contentMap, $student->user_id);

            if (! $isUpdate) {
                AcademicCalendarService::recordProjectStage(
                    $project,
                    'proposal_created',
                    $activeAcademicPeriod,
                    $student->user_id,
                    'Proyecto propuesto por estudiante.',
                    ['proposal_window_id' => $proposalWindow->id]
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'La idea de proyecto fue actualizada y quedó pendiente de evaluación.'
            : 'La idea de proyecto fue registrada y quedó pendiente de evaluación.';

        return redirect()
            ->route('projects.index')
            ->with('success', $message);
    }

    /**
     * Determine whether the authenticated user can consult the version history.
     */
    protected function canViewVersionHistory(Project $project, ?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Normalize project status names so comparisons survive accent and casing differences.
     */
    protected function normalizeStatusName(?string $name): string
    {
        return Str::of((string) $name)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    /**
     * Determine whether the project is currently pending approval.
     */
    protected function isPendingApproval(Project $project): bool
    {
        return $this->normalizeStatusName($project->projectStatus->name ?? '') === 'pendiente de aprobacion';
    }

    /**
     * Determine whether the project can be corrected and resubmitted.
     */
    protected function isReturnedForCorrection(Project $project): bool
    {
        return $this->normalizeStatusName($project->projectStatus->name ?? '') === 'devuelto para correccion';
    }

    /**
     * Normalize content names so the code works with accented and plain-text catalog values.
     */
    protected function normalizeContentName(?string $name): string
    {
        return Str::of((string) $name)
            ->ascii()
            ->lower()
            ->replace('-', ' ')
            ->squish()
            ->toString();
    }

    /**
     * Convert catalog names into the labels expected by the project forms and history screens.
     */
    protected function contentDisplayName(?string $name): string
    {
        $normalizedName = $this->normalizeContentName($name);

        return [
            'titulo' => 'Titulo',
            'cantidad de estudiantes' => 'Cantidad de estudiantes',
            'tiempo de ejecucion' => 'Tiempo de ejecucion',
            'viabilidad' => 'Viabilidad',
            'pertinencia con el grupo de investigacion y con el programa' => 'Pertinencia con el grupo de investigacion y con el programa',
            'disponibilidad de docentes para su direccion y calificacion' => 'Disponibilidad de docentes para su direccion y calificacion',
            'calidad y correspondencia entre titulo y objetivo' => 'Calidad y correspondencia entre titulo y objetivo',
            'objetivo general del proyecto' => 'Objetivo general del proyecto',
            'descripcion del proyecto de investigacion' => 'Descripcion del proyecto de investigacion',
            'comentarios' => 'Comentarios',
        ][$normalizedName] ?? (string) $name;
    }

    /**
     * Create a version record that captures the current project snapshot.
     */
    protected function storeProjectVersion(Project $project, array $contentMap, ?int $createdByUserId): Version
    {
        $project->load([
            'projectStatus',
            'thematicArea.investigationLine',
            'contentFrameworks.framework',
            'professors.user',
            'students.user',
        ]);

        $version = $project->versions()->create([
            'created_by_user_id' => $createdByUserId,
            'snapshot' => $this->sanitizeSnapshot($this->buildProjectVersionSnapshot($project, $contentMap)),
        ]);

        $this->storeContentValues($version, $contentMap);

        return $version;
    }

    /**
     * Build a portable snapshot so each version preserves the project state of that moment.
     */
    protected function buildProjectVersionSnapshot(Project $project, array $contentMap): array
    {
        return [
            'title' => $project->title,
            'evaluation_criteria' => $project->evaluation_criteria,
            'project_status' => [
                'id' => $project->projectStatus?->id,
                'name' => $project->projectStatus?->name,
            ],
            'thematic_area' => [
                'id' => $project->thematicArea?->id,
                'name' => $project->thematicArea?->name,
            ],
            'investigation_line' => [
                'id' => $project->thematicArea?->investigationLine?->id,
                'name' => $project->thematicArea?->investigationLine?->name,
            ],
            'contents' => collect($contentMap)
                ->mapWithKeys(function ($value, $label) {
                    return [$this->contentDisplayName($label) => (string) $value];
                })
                ->toArray(),
            'frameworks' => $project->contentFrameworks
                ->map(function ($contentFramework) {
                    return [
                        'id' => $contentFramework->id,
                        'name' => $contentFramework->name,
                        'framework' => [
                            'id' => $contentFramework->framework?->id,
                            'name' => $contentFramework->framework?->name,
                        ],
                    ];
                })
                ->values()
                ->all(),
            'participants' => [
                'professors' => $project->professors
                    ->map(function (Professor $professor) {
                        return [
                            'id' => $professor->id,
                            'name' => trim(($professor->name ?? '') . ' ' . ($professor->last_name ?? '')),
                            'email' => $professor->mail ?? $professor->user?->email,
                            'phone' => $professor->phone,
                        ];
                    })
                    ->values()
                    ->all(),
                'students' => $project->students
                    ->map(function (Student $student) {
                        return [
                            'id' => $student->id,
                            'name' => trim(($student->name ?? '') . ' ' . ($student->last_name ?? '')),
                            'card_id' => $student->card_id,
                            'phone' => $student->phone,
                        ];
                    })
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Sanitize the snapshot payload so it can always be stored as valid UTF-8 JSON.
     */
    protected function sanitizeSnapshot(array $snapshot): array
    {
        return $this->sanitizeSnapshotValue($snapshot);
    }

    /**
     * Recursively normalize keys and values before JSON encoding them.
     */
    protected function sanitizeSnapshotValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitizedKey = is_string($key)
                    ? $this->sanitizeSnapshotString($key)
                    : $key;

                $sanitized[$sanitizedKey] = $this->sanitizeSnapshotValue($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeSnapshotString($value);
        }

        return $value;
    }

    /**
     * Normalize strings that may contain mixed encodings before storing JSON snapshots.
     */
    protected function sanitizeSnapshotString(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $sanitized = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        if ($sanitized !== false && $sanitized !== '') {
            return $sanitized;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Persist each content value in the content_version table.
     */
    protected function storeContentValues(Version $version, array $contentMap): void
    {
        foreach ($contentMap as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            ContentVersion::create([
                'content_id' => $this->contentId($name),
                'version_id' => $version->id,
                'value' => (string) $value,
            ]);
        }
    }
}
