<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityProgram;
use App\Models\Department;
use App\Models\InvestigationLine;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ResearchGroup;
use App\Models\Student;
use App\Models\ThematicArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresión del bug #6: un proyecto "Descartado" no debe bloquear al estudiante.
 *
 * Antes del fix, Student::hasActiveProject() solo ignoraba "Rechazado" y
 * "Devuelto para corrección", así que un estudiante cuyo único proyecto estaba
 * "Descartado" recibía "Ya tienes un proyecto activo asignado." al postularse.
 */
class DiscardedProjectDoesNotBlockStudentTest extends TestCase
{
    use RefreshDatabase;

    private CityProgram $cityProgram;

    private ThematicArea $thematicArea;

    private int $nextCardId = 30001;

    protected function setUp(): void
    {
        parent::setUp();

        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Bug 6', 'initials' => 'GB6', 'description' => 'desc']);
        $program = Program::create(['code' => 106006, 'name' => 'Ingeniería Bug 6', 'research_group_id' => $researchGroup->id]);
        $this->cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Bug 6', 'description' => 'desc', 'research_group_id' => $researchGroup->id]);
        $this->thematicArea = ThematicArea::create(['name' => 'Área Bug 6', 'description' => 'desc', 'investigation_line_id' => $line->id]);

        foreach ([
            'Pendiente de aprobacion',
            'Aprobado',
            'Asignado',
            'Rechazado',
            'Descartado',
            'Devuelto para correccion',
        ] as $name) {
            $status = new ProjectStatus();
            $status->name = $name;
            $status->description = $name;
            $status->save();
        }
    }

    public function test_student_without_projects_has_no_active_project(): void
    {
        $student = $this->createStudent();

        $this->assertFalse($student->hasActiveProject());
    }

    public function test_discarded_project_does_not_count_as_active(): void
    {
        $student = $this->createStudent();
        $this->attachProject($student, 'Descartado');

        $this->assertFalse($student->hasActiveProject());
    }

    public function test_rejected_project_does_not_count_as_active(): void
    {
        $student = $this->createStudent();
        $this->attachProject($student, 'Rechazado');

        $this->assertFalse($student->hasActiveProject());
    }

    public function test_returned_for_correction_project_does_not_count_as_active(): void
    {
        $student = $this->createStudent();
        $this->attachProject($student, 'Devuelto para correccion');

        $this->assertFalse($student->hasActiveProject());
    }

    public function test_assigned_approved_and_pending_projects_still_block(): void
    {
        foreach (['Asignado', 'Aprobado', 'Pendiente de aprobacion'] as $statusName) {
            $student = $this->createStudent();
            $this->attachProject($student, $statusName);

            $this->assertTrue($student->hasActiveProject(), "Un proyecto '{$statusName}' debe contar como activo.");
        }
    }

    public function test_discarded_plus_assigned_project_still_blocks(): void
    {
        $student = $this->createStudent();
        $this->attachProject($student, 'Descartado');
        $this->attachProject($student, 'Asignado');

        $this->assertTrue($student->hasActiveProject());
    }

    /**
     * Misma consulta que usan PostulationController::create(),
     * BankApprovedIdeasAssignController::select() y ProjectController
     * para listar compañeros disponibles.
     */
    public function test_student_with_discarded_project_is_listed_as_available_teammate(): void
    {
        $discarded = $this->createStudent();
        $this->attachProject($discarded, 'Descartado');

        $busy = $this->createStudent();
        $this->attachProject($busy, 'Asignado');

        $free = $this->createStudent();

        $availableIds = Student::query()
            ->whereDoesntHave('projects', function ($q) {
                $q->whereHas('projectStatus', fn ($s) => $s->whereNotIn('name', Student::INACTIVE_PROJECT_STATUSES));
            })
            ->pluck('id');

        $this->assertTrue($availableIds->contains($discarded->id));
        $this->assertTrue($availableIds->contains($free->id));
        $this->assertFalse($availableIds->contains($busy->id));
    }

    private function createStudent(): Student
    {
        $cardId = (string) $this->nextCardId++;

        $user = User::create([
            'email' => "estudiante{$cardId}@test.com",
            'password' => Hash::make('password123'),
            'role' => 'student',
            'state' => 1,
        ]);

        $student = new Student();
        $student->card_id = $cardId;
        $student->name = 'Ana';
        $student->last_name = 'Ruiz';
        $student->phone = '3000000001';
        $student->semester = 8;
        $student->city_program_id = $this->cityProgram->id;
        $student->user_id = $user->id;
        $student->save();

        return $student;
    }

    private function attachProject(Student $student, string $statusName): Project
    {
        $project = Project::create([
            'title' => "Proyecto {$statusName} {$student->card_id}",
            'thematic_area_id' => $this->thematicArea->id,
            'project_status_id' => ProjectStatus::where('name', $statusName)->value('id'),
        ]);

        $project->students()->attach($student->id);

        return $project;
    }
}
