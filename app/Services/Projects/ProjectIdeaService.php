<?php

namespace App\Services\Projects;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Professor;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Version;
use App\Services\Projects\Exceptions\ProjectIdeaException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Encapsulates the persistence rules used when professors or students submit a project idea.
 */
final class ProjectIdeaService
{
    /**
     * Cache of content identifiers keyed by their human readable name.
     *
     * @var array<string, int>
     */
    private array $contentCache = [];

    /**
     * Cached identifier for the status that represents "waiting evaluation".
     */
    private ?int $waitingStatusId = null;

    public function __construct()
    {
    }

    /**
     * Persist a project idea coming from a professor or committee leader.
     */
    public function persistProfessorIdea(Request $request, Professor $professor, ?Project $project = null): ProjectIdeaResult
    {
        $assignedProgramId = optional($professor->cityProgram)->program_id;

        if (! $assignedProgramId) {
            throw new ProjectIdeaException('Debes tener un programa asignado antes de enviar proyectos.');
        }

        $request->merge(['program_id' => $assignedProgramId]);

        $rules = [
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

        $validated = $request->validate($rules);
        $isUpdate = $project !== null;
        $normalizedTitle = $this->normalizeTitle($validated['title']);

        $professorIds = $this->collectProfessorIds($validated['associated_professors'] ?? [], $professor->id);
        $sortedProfessorIds = $professorIds;
        sort($sortedProfessorIds);

        if ($this->hasDuplicateProfessorProject($project, $normalizedTitle, $sortedProfessorIds)) {
            throw new ProjectIdeaException('Ya existe un proyecto con el mismo título y el mismo equipo de docentes.');
        }

        DB::beginTransaction();

        try {
            $this->syncProfessorContactData($professor, $validated);

            $project = $this->persistProjectRecord(
                $project,
                $normalizedTitle,
                $validated['evaluation_criteria'],
                $validated['thematic_area_id']
            );

            $project->professors()->sync($professorIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $version = $project->versions()->create();
            $contentMap = $this->buildProfessorContentMap($project, $validated);
            $this->storeContentValues($version, $contentMap);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'La idea de proyecto fue actualizada y quedó pendiente de evaluación.'
            : 'La idea de proyecto fue registrada y quedó pendiente de evaluación.';

        return new ProjectIdeaResult($project, $message);
    }

    /**
     * Persist a project idea coming from a student.
     */
    public function persistStudentIdea(Request $request, Student $student, ?Project $project = null): ProjectIdeaResult
    {
        $rules = [
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

        $validated = $request->validate($rules);
        $isUpdate = $project !== null;

        if (! empty($validated['teammate_ids']) && $this->teammatesHaveDifferentProjects($validated['teammate_ids'], $project)) {
            throw new ProjectIdeaException('Uno o más compañeros ya tienen un proyecto registrado.');
        }

        $cityProgram = $student->cityProgram;
        if ($cityProgram && (int) $validated['city_id'] !== (int) $cityProgram->city_id) {
            throw new ProjectIdeaException('La ciudad seleccionada no coincide con la asignación de tu programa.');
        }

        $normalizedTitle = $this->normalizeTitle($validated['title']);
        $studentIds = $this->collectStudentIds($validated['teammate_ids'] ?? [], $student->id);
        $sortedStudentIds = $studentIds;
        sort($sortedStudentIds);

        if (count($studentIds) > 3) {
            throw new ProjectIdeaException('Un proyecto solo puede tener hasta 3 estudiantes participantes.');
        }

        if ($this->studentHasActiveIdea($student, $project)) {
            throw new ProjectIdeaException('Ya tienes una idea de proyecto pendiente de evaluación.');
        }

        if ($this->hasDuplicateStudentProject($project, $normalizedTitle, $sortedStudentIds)) {
            throw new ProjectIdeaException('Ya existe un proyecto con el mismo título y el mismo equipo de estudiantes.');
        }

        DB::beginTransaction();

        try {
            $this->syncStudentContactData($student, $validated);

            $project = $this->persistProjectRecord(
                $project,
                $normalizedTitle,
                null,
                $validated['thematic_area_id']
            );

            $project->students()->sync($studentIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $version = $project->versions()->create();
            $contentMap = $this->buildStudentContentMap($project, $validated);
            $this->storeContentValues($version, $contentMap);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'La idea de proyecto fue actualizada y quedó pendiente de evaluación.'
            : 'La idea de proyecto fue registrada y quedó pendiente de evaluación.';

        return new ProjectIdeaResult($project, $message);
    }

    /**
     * Attempts to resolve the professor profile associated with the provided user.
     */
    public function resolveProfessorProfile(?User $user): ?Professor
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
     * Retrieve the identifier for a content record, caching the lookup for the current request.
     */
    private function contentId(string $name): int
    {
        if (! array_key_exists($name, $this->contentCache)) {
            $content = Content::query()->where('name', $name)->first();
            if (! $content) {
                throw new \RuntimeException("Content '{$name}' not found in catalog.");
            }
            $this->contentCache[$name] = $content->id;
        }

        return $this->contentCache[$name];
    }

    /**
     * Stores the provided content values for the given project version.
     *
     * @param array<string, string> $contentMap
     */
    private function storeContentValues(Version $version, array $contentMap): void
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

    /**
     * Normalizes repeated whitespace and applies title casing to project titles.
     */
    private function normalizeTitle(string $title): string
    {
        return Str::of($title)->squish()->title()->toString();
    }

    /**
     * Returns the identifier for the "waiting evaluation" status reusing the last lookup.
     */
    private function waitingEvaluationStatusId(): int
    {
        if ($this->waitingStatusId !== null) {
            return $this->waitingStatusId;
        }

        $status = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobaci��n'])
            ->orderByRaw("CASE WHEN name = 'waiting evaluation' THEN 0 ELSE 1 END")
            ->first();

        if (! $status) {
            throw new \RuntimeException('El estado de pendiente de evaluación no existe en el catálogo.');
        }

        $this->waitingStatusId = $status->id;

        return $this->waitingStatusId;
    }

    /**
     * Collects professor identifiers, ensuring the authenticated professor is always present.
     *
     * @param array<int, int|string|null> $additionalIds
     * @return array<int, int>
     */
    private function collectProfessorIds(array $additionalIds, int $professorId): array
    {
        return collect($additionalIds)
            ->filter(static fn ($id) => $id !== null)
            ->push($professorId)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Collects student identifiers, including the authenticated student.
     *
     * @param array<int, int|string|null> $additionalIds
     * @return array<int, int>
     */
    private function collectStudentIds(array $additionalIds, int $studentId): array
    {
        return collect($additionalIds)
            ->filter(static fn ($id) => $id !== null)
            ->push($studentId)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Determines if a duplicate project already exists for the provided professor lineup.
     *
     * @param array<int, int> $sortedProfessorIds
     */
    private function hasDuplicateProfessorProject(?Project $project, string $normalizedTitle, array $sortedProfessorIds): bool
    {
        return Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedProfessorIds) {
                $existingProfessorIds = $existing->professors()->pluck('professors.id')->sort()->values()->all();

                return $existingProfessorIds === $sortedProfessorIds;
            }) !== null;
    }

    /**
     * Determines if a duplicate project already exists for the provided student lineup.
     *
     * @param array<int, int> $sortedStudentIds
     */
    private function hasDuplicateStudentProject(?Project $project, string $normalizedTitle, array $sortedStudentIds): bool
    {
        return Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedStudentIds) {
                $existingStudentIds = $existing->students()->pluck('students.id')->sort()->values()->all();

                return $existingStudentIds === $sortedStudentIds;
            }) !== null;
    }

    /**
     * Checks if any teammate already owns a different project idea that is still active.
     * Projects in Student::INACTIVE_PROJECT_STATUSES (e.g. "Descartado") do not count.
     *
     * @param array<int, int> $teammateIds
     */
    private function teammatesHaveDifferentProjects(array $teammateIds, ?Project $project): bool
    {
        return Student::query()
            ->whereIn('id', $teammateIds)
            ->whereHas('projects', function ($query) use ($project) {
                $query->where('project_id', '!=', $project?->id)
                    ->whereHas('projectStatus', fn ($status) => $status->whereNotIn('name', Student::INACTIVE_PROJECT_STATUSES));
            })
            ->exists();
    }

    /**
     * Ensures the student does not have another active project awaiting evaluation.
     */
    private function studentHasActiveIdea(Student $student, ?Project $project): bool
    {
        $activeStatusIds = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobaci��n'])
            ->pluck('id');

        return $student->projects()
            ->when($project, static fn ($query) => $query->where('projects.id', '!=', $project->id))
            ->whereIn('project_status_id', $activeStatusIds)
            ->exists();
    }

    /**
     * Synchronises the contact fields for the professor and their linked user.
     *
     * @param array<string, mixed> $validated
     */
    private function syncProfessorContactData(Professor $professor, array $validated): void
    {
        $professor->fill([
            'phone' => $validated['contact_phone'],
        ])->save();
    }

    /**
     * Synchronises the contact fields for the student and their linked user.
     *
     * @param array<string, mixed> $validated
     */
    private function syncStudentContactData(Student $student, array $validated): void
    {
        $student->fill([
            'phone' => $validated['student_phone'],
        ])->save();
    }

    /**
     * Inserts or updates a project record maintaining the "waiting evaluation" status.
     */
    private function persistProjectRecord(?Project $project, string $normalizedTitle, ?string $evaluationCriteria, int $thematicAreaId): Project
    {
        if ($project) {
            $project->fill([
                'title' => $normalizedTitle,
                'evaluation_criteria' => $evaluationCriteria,
                'thematic_area_id' => $thematicAreaId,
                'project_status_id' => $this->waitingEvaluationStatusId(),
            ])->save();

            return $project;
        }

        return Project::create([
            'title' => $normalizedTitle,
            'evaluation_criteria' => $evaluationCriteria,
            'thematic_area_id' => $thematicAreaId,
            'project_status_id' => $this->waitingEvaluationStatusId(),
        ]);
    }

    /**
     * Builds the content map stored alongside a professor submission.
     *
     * @param array<string, mixed> $validated
     * @return array<string, string>
     */
    private function buildProfessorContentMap(Project $project, array $validated): array
    {
        return [
            'T��tulo' => $project->title,
            'Cantidad de estudiantes' => (string) $validated['students_count'],
            'Tiempo de ejecuci��n' => $validated['execution_time'],
            'Viabilidad' => $validated['viability'],
            'Pertinencia con el grupo de investigaci��n y con el programa' => $validated['relevance'],
            'Disponibilidad de docentes para su direcci��n y calificaci��n' => $validated['teacher_availability'],
            'Calidad y correspondencia entre t��tulo y objetivo' => $validated['title_objectives_quality'],
            'Objetivo general del proyecto' => $validated['general_objective'],
            'Descripci��n del proyecto de investigaci��n' => $validated['description'],
        ];
    }

    /**
     * Builds the content map stored alongside a student submission.
     *
     * @param array<string, mixed> $validated
     * @return array<string, string>
     */
    private function buildStudentContentMap(Project $project, array $validated): array
    {
        return [
            'T��tulo' => $project->title,
            'Objetivo general del proyecto' => $validated['general_objective'],
            'Descripci��n del proyecto de investigaci��n' => $validated['description'],
        ];
    }
}
