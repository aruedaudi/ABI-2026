<?php

namespace App\Http\Controllers;

use App\Events\ProjectIdeaEvaluated;
use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Professor;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Services\AcademicCalendar\AcademicCalendarService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectEvaluationController extends Controller
{
    public function index(Request $request): View|Response
    {
        $committeeLeader = $this->resolveCommitteeLeader();
        $cityProgramId = $committeeLeader->city_program_id;

        $projects = Project::whereHas('projectStatus', function ($query) {
                $query->whereIn('name', ['Pendiente de aprobacion']);
            })
            ->where(function ($query) use ($cityProgramId) {
                $query->whereHas('students', function ($sub) use ($cityProgramId) {
                    $sub->where('city_program_id', $cityProgramId);
                })->orWhereHas('professors', function ($sub) use ($cityProgramId) {
                    $sub->where('city_program_id', $cityProgramId);
                });
            })
            ->with(['projectStatus', 'thematicArea.investigationLine', 'versions.contentVersions.content', 'contentFrameworkProjects.contentFramework.framework', 'students', 'professors'])
            ->get();

        $committeeLeader->loadMissing('cityProgram.program', 'cityProgram.city');
        $reportState = $this->buildCommitteeReportState($cityProgramId, $projects->count());

        if ($request->query('report_export') === 'pdf') {
            return $this->downloadCommitteeReportPdf($committeeLeader, $reportState);
        }

        return view('projects.evaluation.index', [
            'projects' => $projects,
            'committeeLeader' => $committeeLeader,
            'reportState' => $reportState,
        ]);
    }

    public function show(Project $project)
    {
        $committeeLeader = $this->resolveCommitteeLeader();
        $this->ensureProjectBelongsToCommitteeProgram($project, $committeeLeader->city_program_id);
        $project->load([
            'thematicArea.investigationLine',
            'projectStatus',
            'professors.user',
            'professors.cityProgram.program',
            'students',
            'contentFrameworks.framework',
            'versions' => static fn ($relation) => $relation->with(['contentVersions.content'])->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions()->latest('created_at')->first();
        $contentValues = [];
        if ($latestVersion) {
            foreach ($latestVersion->contentVersions as $cv) {
                $label = $cv->content->label ?? $cv->content->name ?? 'Campo';
                $contentValues[$label] = $cv->value ?? '-';
            }
        }
        $frameworksSelected = $project->contentFrameworks;

        return view('projects.evaluation.show', compact('project', 'latestVersion', 'contentValues', 'frameworksSelected'));
    }

    public function evaluate(Request $request, Project $project)
    {
        $committeeLeader = $this->resolveCommitteeLeader();
        $this->ensureProjectBelongsToCommitteeProgram($project, $committeeLeader->city_program_id);
        $validated = $request->validate([
            'status' => 'required|string|in:Aprobado,Rechazado,Devuelto para correccion,Devuelto para corrección',
            'comments' => 'nullable|string',
        ]);

        $comments = $this->normalizeComments($validated['comments'] ?? null);
        $requestedStatus = Str::of($validated['status'])->ascii()->squish()->toString();
        $statusName = $requestedStatus;
        $isProfessorProject = $project->professors()->exists();
        $isStudentProject = ! $isProfessorProject;

        // Si es proyecto de estudiante y se aprueba, normalmente se asigna.
        // Si estamos en una fase distinta, este comportamiento se puede ajustar.
        // Sin embargo, si el estudiante propuso la idea, ya es suya.
        if ($statusName === 'Aprobado' && $isStudentProject) {
            $statusName = 'Asignado';
        }

        $normalizedStatusName = Str::of($statusName)->ascii()->toString();
        $status = ProjectStatus::whereIn('name', array_unique([$statusName, $normalizedStatusName]))->first();
        if (! $status) {
            return back()->with('error', "No se encontro el estado '$statusName'.");
        }

        $project->update(['project_status_id' => $status->id]);

        if (in_array($statusName, ['Devuelto para correccion'], true)) {
            $latestVersion = $project->versions()->latest('created_at')->first();
            if ($latestVersion) {
                $commentContent = Content::where('name', 'Comentarios')->whereJsonContains('roles', 'committee_leader')->first();
                if ($commentContent) {
                    ContentVersion::create([
                        'version_id' => $latestVersion->id,
                        'content_id' => $commentContent->id,
                        'value' => $comments ?? 'Sin comentarios',
                    ]);
                }
            }
        }

        $activePeriod = \App\Models\AcademicPeriod::query()->active()->first() ?? $project->proposalAcademicPeriod;
        $normalizedRequestedStatus = Str::of($validated['status'])->ascii()->lower()->toString();
        $stage = match ($normalizedRequestedStatus) {
            'aprobado' => 'approved',
            'rechazado' => 'rejected',
            'devuelto para correccion' => 'returned_for_correction',
            default => 'evaluated',
        };

        AcademicCalendarService::recordProjectStage(
            $project,
            $stage,
            $activePeriod,
            Auth::id(),
            $comments,
            ['final_status_name' => $statusName]
        );

        // Disparar evento de notificacion
        event(new ProjectIdeaEvaluated(
            $project->load(['students.user', 'professors.user']),
            $statusName,
            $comments
        ));

        return redirect()
            ->route('projects.evaluation.index')
            ->with('success', "Evaluacion del proyecto '{$project->title}' enviada correctamente con estado: $statusName.");
    }

    protected function normalizeComments(?string $comments): ?string
    {
        if ($comments === null) {
            return null;
        }

        $comments = trim($comments);

        if ($comments === '' || strcasecmp($comments, 'null') === 0) {
            return null;
        }

        return $comments;
    }

    protected function resolveCommitteeLeader(): Professor
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'committee_leader') {
            abort(403, 'Solo el lider de comite puede consultar este modulo.');
        }

        $professor = Professor::query()
            ->where('user_id', $user->id)
            ->where('committee_leader', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $professor || ! $professor->city_program_id) {
            abort(403, 'No se pudo determinar el programa del lider de comite.');
        }

        return $professor;
    }

    protected function programProjectsQuery(int $cityProgramId): Builder
    {
        return Project::query()->where(function (Builder $query) use ($cityProgramId): void {
            $query
                ->whereHas('students', function (Builder $subQuery) use ($cityProgramId): void {
                    $subQuery->where('city_program_id', $cityProgramId);
                })
                ->orWhereHas('professors', function (Builder $subQuery) use ($cityProgramId): void {
                    $subQuery->where('city_program_id', $cityProgramId);
                });
        });
    }

    /**
     * @return array{
     *     totals: array{evaluated:int,pending:int,approved:int,rejected:int,returned:int},
     *     periods: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     statuses: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     rates: array<int, array{label:string,value:int,percentage:float,color:string,description:string}>,
     *     topPeriod:?string
     * }
     */
    protected function buildCommitteeReportState(int $cityProgramId, int $pendingProjects): array
    {
        $evaluationProjects = $this->programProjectsQuery($cityProgramId)
            ->whereHas('stageHistories', function (Builder $query): void {
                $query->whereIn('stage', ['approved', 'rejected', 'returned_for_correction']);
            })
            ->with([
                'projectStatus',
                'stageHistories' => function ($query) {
                    $query
                        ->whereIn('stage', ['approved', 'rejected', 'returned_for_correction'])
                        ->with('academicPeriod')
                        ->orderByDesc('event_at')
                        ->orderByDesc('id');
                },
            ])
            ->get();

        $latestEvaluations = $evaluationProjects
            ->map(function (Project $project): ?array {
                $latestHistory = $project->stageHistories->first();

                if (! $latestHistory) {
                    return null;
                }

                $period = $latestHistory->academicPeriod;

                return [
                    'period_label' => $period?->name ?? 'Sin periodo academico',
                    'period_sort' => $period?->start_date?->timestamp ?? PHP_INT_MAX,
                    'status_label' => $this->committeeStatusLabel(
                        $latestHistory->metadata['final_status_name'] ?? null,
                        $latestHistory->stage,
                        $project->projectStatus?->name
                    ),
                ];
            })
            ->filter()
            ->values();

        $totalEvaluated = $latestEvaluations->count();
        $periodPalette = [
            '#2563eb',
            '#0f766e',
            '#b45309',
            '#be123c',
            '#7c3aed',
            '#0891b2',
            '#4d7c0f',
            '#ea580c',
        ];

        $periods = $latestEvaluations
            ->groupBy('period_label')
            ->map(function ($items, $label) use ($totalEvaluated) {
                return [
                    'label' => (string) $label,
                    'value' => $items->count(),
                    'percentage' => $totalEvaluated > 0
                        ? round(($items->count() / $totalEvaluated) * 100, 2)
                        : 0.0,
                    'sort' => $items->min('period_sort'),
                ];
            })
            ->sortBy('sort')
            ->values()
            ->map(function (array $period, int $index) use ($periodPalette): array {
                unset($period['sort']);
                $period['color'] = $periodPalette[$index % count($periodPalette)];

                return $period;
            })
            ->all();

        $statusDefinitions = [
            'Aprobado' => '#16a34a',
            'Rechazado' => '#dc2626',
            'Devuelto para correccion' => '#d97706',
        ];

        $statuses = collect($statusDefinitions)
            ->map(function (string $color, string $label) use ($latestEvaluations, $totalEvaluated): array {
                $value = $latestEvaluations->where('status_label', $label)->count();

                return [
                    'label' => $label,
                    'value' => $value,
                    'percentage' => $totalEvaluated > 0 ? round(($value / $totalEvaluated) * 100, 2) : 0.0,
                    'color' => $color,
                ];
            })
            ->values()
            ->all();

        $rates = [
            [
                'label' => 'Aceptacion',
                'value' => $statuses[0]['value'] ?? 0,
                'percentage' => $statuses[0]['percentage'] ?? 0.0,
                'color' => $statuses[0]['color'] ?? '#16a34a',
                'description' => 'Ideas que terminaron con concepto favorable del comite.',
            ],
            [
                'label' => 'Rechazo',
                'value' => $statuses[1]['value'] ?? 0,
                'percentage' => $statuses[1]['percentage'] ?? 0.0,
                'color' => $statuses[1]['color'] ?? '#dc2626',
                'description' => 'Ideas descartadas de forma definitiva por el comite.',
            ],
            [
                'label' => 'Devolucion',
                'value' => $statuses[2]['value'] ?? 0,
                'percentage' => $statuses[2]['percentage'] ?? 0.0,
                'color' => $statuses[2]['color'] ?? '#d97706',
                'description' => 'Ideas que fueron devueltas para ajustes o correcciones.',
            ],
        ];

        return [
            'totals' => [
                'evaluated' => $totalEvaluated,
                'pending' => $pendingProjects,
                'approved' => $statuses[0]['value'] ?? 0,
                'rejected' => $statuses[1]['value'] ?? 0,
                'returned' => $statuses[2]['value'] ?? 0,
            ],
            'periods' => $periods,
            'statuses' => $statuses,
            'rates' => $rates,
            'topPeriod' => collect($periods)->sortByDesc('value')->first()['label'] ?? null,
        ];
    }

    protected function committeeStatusLabel(?string $finalStatusName, ?string $stage, ?string $fallbackStatusName): string
    {
        $normalized = Str::of((string) ($finalStatusName ?: $fallbackStatusName))
            ->ascii()
            ->lower()
            ->squish()
            ->toString();

        return match (true) {
            $normalized === 'aprobado',
            $stage === 'approved' => 'Aprobado',
            $normalized === 'rechazado',
            $stage === 'rejected' => 'Rechazado',
            $normalized === 'devuelto para correccion',
            $stage === 'returned_for_correction' => 'Devuelto para correccion',
            default => 'Sin estado final',
        };
    }

    protected function ensureProjectBelongsToCommitteeProgram(Project $project, int $cityProgramId): void
    {
        $belongsToProgram = $this->programProjectsQuery($cityProgramId)
            ->whereKey($project->getKey())
            ->exists();

        if (! $belongsToProgram) {
            abort(403, 'No tienes permiso para ver este proyecto.');
        }
    }

    /**
     * Render the committee report as a branded PDF document.
     */
    protected function downloadCommitteeReportPdf(Professor $committeeLeader, array $reportState): Response
    {
        $generatedAt = now();

        $pdf = Pdf::loadView('projects.evaluation.report-pdf', [
            'committeeLeader' => $committeeLeader,
            'reportGeneratedAt' => $generatedAt,
            'reportState' => $reportState,
            'reportInsights' => $this->committeeReportInsights($reportState),
            'reportVisuals' => $this->committeeReportVisuals($reportState),
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
            'reporte-comite-%s-%s.pdf',
            Str::slug((string) ($committeeLeader->cityProgram->program->name ?? 'programa')),
            $generatedAt->format('Ymd-His')
        );

        return $pdf->download($filename);
    }

    /**
     * @param  array{
     *     totals: array{evaluated:int,pending:int,approved:int,rejected:int,returned:int},
     *     periods: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     statuses: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     rates: array<int, array{label:string,value:int,percentage:float,color:string,description:string}>,
     *     topPeriod:?string
     * }  $reportState
     * @return array<int, array{label:string,value:string,caption:string}>
     */
    protected function committeeReportInsights(array $reportState): array
    {
        return [
            [
                'label' => 'Ideas evaluadas',
                'value' => (string) ($reportState['totals']['evaluated'] ?? 0),
                'caption' => 'Total historico con decision final registrada por el comite.',
            ],
            [
                'label' => 'Pendientes actuales',
                'value' => (string) ($reportState['totals']['pending'] ?? 0),
                'caption' => 'Proyectos del programa que siguen esperando revision.',
            ],
            [
                'label' => 'Ideas aprobadas',
                'value' => (string) ($reportState['totals']['approved'] ?? 0),
                'caption' => 'Cantidad de ideas con resultado favorable.',
            ],
            [
                'label' => 'Periodo mas activo',
                'value' => (string) ($reportState['topPeriod'] ?? 'Sin datos'),
                'caption' => 'Periodo academico con mayor numero de evaluaciones.',
            ],
        ];
    }

    /**
     * @param  array{
     *     totals: array{evaluated:int,pending:int,approved:int,rejected:int,returned:int},
     *     periods: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     statuses: array<int, array{label:string,value:int,percentage:float,color:string}>,
     *     rates: array<int, array{label:string,value:int,percentage:float,color:string,description:string}>,
     *     topPeriod:?string
     * }  $reportState
     * @return array<int, array{
     *     title:string,
     *     description:string,
     *     total_label:string,
     *     total_value:int,
     *     segments: array<int, array{label:string,value:int,percentage:float,color:string,description?:string}>
     * }>
     */
    protected function committeeReportVisuals(array $reportState): array
    {
        return [
            [
                'title' => 'Ideas evaluadas por periodo',
                'description' => 'Cantidad de ideas que alcanzaron una decision final dentro de cada periodo academico.',
                'total_label' => 'Ideas evaluadas',
                'total_value' => (int) ($reportState['totals']['evaluated'] ?? 0),
                'segments' => $reportState['periods'] ?? [],
            ],
            [
                'title' => 'Estado final de las ideas',
                'description' => 'Distribucion entre aprobacion, rechazo y devolucion con base en la ultima decision del comite.',
                'total_label' => 'Total evaluadas',
                'total_value' => (int) ($reportState['totals']['evaluated'] ?? 0),
                'segments' => $reportState['statuses'] ?? [],
            ],
            [
                'title' => 'Porcentajes de aceptacion, rechazo y devolucion',
                'description' => 'Comparativo de las tres salidas principales del proceso de evaluacion.',
                'total_label' => 'Ideas analizadas',
                'total_value' => (int) ($reportState['totals']['evaluated'] ?? 0),
                'segments' => $reportState['rates'] ?? [],
            ],
        ];
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
}
