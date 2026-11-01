<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\City;
use App\Models\CityProgram;
use App\Models\Department;
use App\Models\InvestigationLine;
use App\Models\Professor;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ResearchGroup;
use App\Models\Student;
use App\Models\ThematicArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProjectEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_student_proposed_idea_assigns_project_with_period_and_timestamp(): void
    {
        // 1. Catálogo base
        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Test', 'initials' => 'GT', 'description' => 'Grupo test desc']);
        $program = Program::create(['code' => 105324, 'name' => 'Ingeniería Test', 'research_group_id' => $researchGroup->id]);
        $cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Test', 'description' => 'Línea test desc', 'research_group_id' => $researchGroup->id]);
        $thematicArea = ThematicArea::create(['name' => 'Área Test', 'description' => 'Área test desc', 'investigation_line_id' => $line->id]);

        $pendingStatus = $this->createStatus('Pendiente de aprobacion', 'Pendiente');
        $approvedStatus = $this->createStatus('Aprobado', 'Aprobado');
        $assignedStatus = $this->createStatus('Asignado', 'Asignado');

        // 2. Periodo académico activo
        $activePeriod = AcademicPeriod::create([
            'code' => '2026-1',
            'name' => 'Periodo Académico 2026-1',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->addMonths(5)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        // 3. Líder de comité
        $leaderUser = User::create([
            'email' => 'leader@test.com',
            'password' => Hash::make('password123'),
            'role' => 'committee_leader',
            'state' => 1,
        ]);
        $leaderProfessor = new Professor();
        $leaderProfessor->card_id = '10001';
        $leaderProfessor->name = 'Natalia';
        $leaderProfessor->last_name = 'Duarte';
        $leaderProfessor->phone = '3000000001';
        $leaderProfessor->committee_leader = true;
        $leaderProfessor->city_program_id = $cityProgram->id;
        $leaderProfessor->user_id = $leaderUser->id;
        $leaderProfessor->save();

        // 4. Estudiante que propone su propia idea
        $studentUser = User::create([
            'email' => 'borja@test.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'state' => 1,
        ]);
        $student = new Student();
        $student->card_id = '20001';
        $student->name = 'Borja';
        $student->last_name = 'Campos';
        $student->phone = '3000000002';
        $student->semester = 7;
        $student->city_program_id = $cityProgram->id;
        $student->user_id = $studentUser->id;
        $student->save();

        // 5. Proyecto propuesto únicamente por el estudiante (sin profesores)
        $project = Project::create([
            'title' => 'Implementacion de gemelos digitales',
            'thematic_area_id' => $thematicArea->id,
            'project_status_id' => $pendingStatus->id,
            'proposal_academic_period_id' => $activePeriod->id,
            'proposed_at' => Carbon::now()->subDays(2),
        ]);
        $project->students()->sync([$student->id]);

        // 6. El líder de comité aprueba la idea
        $response = $this->actingAs($leaderUser)
            ->post(route('projects.evaluation.evaluate', $project), [
                'status' => 'Aprobado',
                'comments' => 'Idea viable y pertinente para el programa.',
            ]);

        $response->assertRedirect(route('projects.evaluation.index'));

        // 7. Verificaciones en la base de datos
        $project->refresh();

        $this->assertEquals($assignedStatus->id, $project->project_status_id);
        $this->assertEquals('Asignado', $project->projectStatus->name);

        // Estas aserciones deben fallar ANTES de corregir el bug 2:
        $this->assertNotNull($project->assignment_academic_period_id, 'assignment_academic_period_id no debe ser null');
        $this->assertEquals($activePeriod->id, $project->assignment_academic_period_id);
        $this->assertNotNull($project->assigned_at, 'assigned_at no debe ser null');
    }

    public function test_approving_professor_proposed_idea_does_not_assign_project(): void
    {
        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Test 2', 'initials' => 'GT2', 'description' => 'desc']);
        $program = Program::create(['code' => 105325, 'name' => 'Ingeniería Test 2', 'research_group_id' => $researchGroup->id]);
        $cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Test 2', 'description' => 'desc', 'research_group_id' => $researchGroup->id]);
        $thematicArea = ThematicArea::create(['name' => 'Área Test 2', 'description' => 'desc', 'investigation_line_id' => $line->id]);

        $pendingStatus = $this->createStatus('Pendiente de aprobacion', 'Pendiente');
        $approvedStatus = $this->createStatus('Aprobado', 'Aprobado');
        $this->createStatus('Asignado', 'Asignado');

        $activePeriod = AcademicPeriod::create([
            'code' => '2026-1',
            'name' => 'Periodo Académico 2026-1',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->addMonths(5)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $leaderUser = User::create([
            'email' => 'leader2@test.com',
            'password' => Hash::make('password123'),
            'role' => 'committee_leader',
            'state' => 1,
        ]);
        $leaderProfessor = new Professor();
        $leaderProfessor->card_id = '10002';
        $leaderProfessor->name = 'Natalia';
        $leaderProfessor->last_name = 'Duarte';
        $leaderProfessor->phone = '3000000003';
        $leaderProfessor->committee_leader = true;
        $leaderProfessor->city_program_id = $cityProgram->id;
        $leaderProfessor->user_id = $leaderUser->id;
        $leaderProfessor->save();

        $teacherUser = User::create([
            'email' => 'teacher@test.com',
            'password' => Hash::make('password123'),
            'role' => 'professor',
            'state' => 1,
        ]);
        $teacher = new Professor();
        $teacher->card_id = '10003';
        $teacher->name = 'Samuel';
        $teacher->last_name = 'Suarez';
        $teacher->phone = '3000000004';
        $teacher->committee_leader = false;
        $teacher->city_program_id = $cityProgram->id;
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $project = Project::create([
            'title' => 'Idea de docente',
            'thematic_area_id' => $thematicArea->id,
            'project_status_id' => $pendingStatus->id,
            'proposal_academic_period_id' => $activePeriod->id,
            'proposed_at' => Carbon::now()->subDays(2),
        ]);
        $project->professors()->sync([$teacher->id]);

        $response = $this->actingAs($leaderUser)
            ->post(route('projects.evaluation.evaluate', $project), [
                'status' => 'Aprobado',
                'comments' => 'Idea de docente aprobada.',
            ]);

        $response->assertRedirect(route('projects.evaluation.index'));

        $project->refresh();
        $this->assertEquals($approvedStatus->id, $project->project_status_id);
        $this->assertEquals('Aprobado', $project->projectStatus->name);
        $this->assertNull($project->assignment_academic_period_id);
        $this->assertNull($project->assigned_at);
    }

    public function test_rejecting_student_idea_does_not_set_assignment(): void
    {
        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Test 3', 'initials' => 'GT3', 'description' => 'desc']);
        $program = Program::create(['code' => 105326, 'name' => 'Ingeniería Test 3', 'research_group_id' => $researchGroup->id]);
        $cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Test 3', 'description' => 'desc', 'research_group_id' => $researchGroup->id]);
        $thematicArea = ThematicArea::create(['name' => 'Área Test 3', 'description' => 'desc', 'investigation_line_id' => $line->id]);

        $pendingStatus = $this->createStatus('Pendiente de aprobacion', 'Pendiente');
        $this->createStatus('Aprobado', 'Aprobado');
        $rejectedStatus = $this->createStatus('Rechazado', 'Rechazado');

        $activePeriod = AcademicPeriod::create([
            'code' => '2026-1',
            'name' => 'Periodo Académico 2026-1',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->addMonths(5)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $leaderUser = User::create([
            'email' => 'leader3@test.com',
            'password' => Hash::make('password123'),
            'role' => 'committee_leader',
            'state' => 1,
        ]);
        $leaderProfessor = new Professor();
        $leaderProfessor->card_id = '10004';
        $leaderProfessor->name = 'Natalia';
        $leaderProfessor->last_name = 'Duarte';
        $leaderProfessor->phone = '3000000005';
        $leaderProfessor->committee_leader = true;
        $leaderProfessor->city_program_id = $cityProgram->id;
        $leaderProfessor->user_id = $leaderUser->id;
        $leaderProfessor->save();

        $studentUser = User::create([
            'email' => 'student3@test.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'state' => 1,
        ]);
        $student = new Student();
        $student->card_id = '20002';
        $student->name = 'Carlos';
        $student->last_name = 'Gomez';
        $student->phone = '3000000006';
        $student->semester = 8;
        $student->city_program_id = $cityProgram->id;
        $student->user_id = $studentUser->id;
        $student->save();

        $project = Project::create([
            'title' => 'Idea de estudiante a rechazar',
            'thematic_area_id' => $thematicArea->id,
            'project_status_id' => $pendingStatus->id,
            'proposal_academic_period_id' => $activePeriod->id,
            'proposed_at' => Carbon::now()->subDays(2),
        ]);
        $project->students()->sync([$student->id]);

        $response = $this->actingAs($leaderUser)
            ->post(route('projects.evaluation.evaluate', $project), [
                'status' => 'Rechazado',
                'comments' => 'No cumple criterios.',
            ]);

        $response->assertRedirect(route('projects.evaluation.index'));

        $project->refresh();
        $this->assertEquals($rejectedStatus->id, $project->project_status_id);
        $this->assertEquals('Rechazado', $project->projectStatus->name);
        $this->assertNull($project->assignment_academic_period_id);
        $this->assertNull($project->assigned_at);
    }

    public function test_approving_student_idea_aborts_when_no_active_period_exists(): void
    {
        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Test 4', 'initials' => 'GT4', 'description' => 'desc']);
        $program = Program::create(['code' => 105327, 'name' => 'Ingeniería Test 4', 'research_group_id' => $researchGroup->id]);
        $cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Test 4', 'description' => 'desc', 'research_group_id' => $researchGroup->id]);
        $thematicArea = ThematicArea::create(['name' => 'Área Test 4', 'description' => 'desc', 'investigation_line_id' => $line->id]);

        $pendingStatus = $this->createStatus('Pendiente de aprobacion', 'Pendiente');
        $this->createStatus('Aprobado', 'Aprobado');
        $this->createStatus('Asignado', 'Asignado');

        // SIN periodos activos
        AcademicPeriod::query()->delete();

        $leaderUser = User::create([
            'email' => 'leader4@test.com',
            'password' => Hash::make('password123'),
            'role' => 'committee_leader',
            'state' => 1,
        ]);
        $leaderProfessor = new Professor();
        $leaderProfessor->card_id = '10005';
        $leaderProfessor->name = 'Natalia';
        $leaderProfessor->last_name = 'Duarte';
        $leaderProfessor->phone = '3000000007';
        $leaderProfessor->committee_leader = true;
        $leaderProfessor->city_program_id = $cityProgram->id;
        $leaderProfessor->user_id = $leaderUser->id;
        $leaderProfessor->save();

        $studentUser = User::create([
            'email' => 'student4@test.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'state' => 1,
        ]);
        $student = new Student();
        $student->card_id = '20003';
        $student->name = 'Daniel';
        $student->last_name = 'Lopez';
        $student->phone = '3000000008';
        $student->semester = 8;
        $student->city_program_id = $cityProgram->id;
        $student->user_id = $studentUser->id;
        $student->save();

        $project = Project::create([
            'title' => 'Idea sin periodo',
            'thematic_area_id' => $thematicArea->id,
            'project_status_id' => $pendingStatus->id,
            'proposed_at' => Carbon::now()->subDays(2),
        ]);
        $project->students()->sync([$student->id]);

        $response = $this->actingAs($leaderUser)
            ->post(route('projects.evaluation.evaluate', $project), [
                'status' => 'Aprobado',
                'comments' => 'Aprobado sin periodo activo.',
            ]);

        // Debe abortar con 403, igual que la postulación
        $response->assertStatus(403);
    }

    public function test_committee_cannot_reevaluate_assigned_project(): void
    {
        $department = Department::create(['name' => 'Santander']);
        $city = City::create(['name' => 'Bucaramanga', 'department_id' => $department->id]);
        $researchGroup = ResearchGroup::create(['name' => 'Grupo Test 5', 'initials' => 'GT5', 'description' => 'desc']);
        $program = Program::create(['code' => 105328, 'name' => 'Ingeniería Test 5', 'research_group_id' => $researchGroup->id]);
        $cityProgram = CityProgram::create(['city_id' => $city->id, 'program_id' => $program->id]);
        $line = InvestigationLine::create(['name' => 'Línea Test 5', 'description' => 'desc', 'research_group_id' => $researchGroup->id]);
        $thematicArea = ThematicArea::create(['name' => 'Área Test 5', 'description' => 'desc', 'investigation_line_id' => $line->id]);

        $this->createStatus('Pendiente de aprobacion', 'Pendiente');
        $this->createStatus('Rechazado', 'Rechazado');
        $assignedStatus = $this->createStatus('Asignado', 'Asignado');

        $activePeriod = AcademicPeriod::create([
            'code' => '2026-1',
            'name' => 'Periodo Académico 2026-1',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->addMonths(5)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $leaderUser = User::create([
            'email' => 'leader5@test.com',
            'password' => Hash::make('password123'),
            'role' => 'committee_leader',
            'state' => 1,
        ]);
        $leaderProfessor = new Professor();
        $leaderProfessor->card_id = '10006';
        $leaderProfessor->name = 'Natalia';
        $leaderProfessor->last_name = 'Duarte';
        $leaderProfessor->phone = '3000000009';
        $leaderProfessor->committee_leader = true;
        $leaderProfessor->city_program_id = $cityProgram->id;
        $leaderProfessor->user_id = $leaderUser->id;
        $leaderProfessor->save();

        $studentUser = User::create([
            'email' => 'student5@test.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'state' => 1,
        ]);
        $student = new Student();
        $student->card_id = '20004';
        $student->name = 'Laura';
        $student->last_name = 'Rojas';
        $student->phone = '3000000010';
        $student->semester = 8;
        $student->city_program_id = $cityProgram->id;
        $student->user_id = $studentUser->id;
        $student->save();

        $assignedAt = Carbon::now()->subDay();
        $project = Project::create([
            'title' => 'Idea ya asignada',
            'thematic_area_id' => $thematicArea->id,
            'project_status_id' => $assignedStatus->id,
            'proposal_academic_period_id' => $activePeriod->id,
            'assignment_academic_period_id' => $activePeriod->id,
            'assigned_at' => $assignedAt,
            'proposed_at' => Carbon::now()->subDays(5),
        ]);
        $project->students()->sync([$student->id]);

        $this->actingAs($leaderUser)
            ->get(route('projects.evaluation.show', $project))
            ->assertRedirect(route('projects.evaluation.index'))
            ->assertSessionHas('error');

        $response = $this->actingAs($leaderUser)
            ->post(route('projects.evaluation.evaluate', $project), [
                'status' => 'Rechazado',
                'comments' => 'Intento de reevaluacion.',
            ]);

        $response->assertRedirect(route('projects.evaluation.index'));
        $response->assertSessionHas('error');

        $project->refresh();
        $this->assertEquals($assignedStatus->id, $project->project_status_id);
        $this->assertEquals($activePeriod->id, $project->assignment_academic_period_id);
        $this->assertNotNull($project->assigned_at);
    }

    private function createStatus(string $name, string $description): ProjectStatus
    {
        $status = new ProjectStatus();
        $status->name = $name;
        $status->description = $description;
        $status->save();

        return $status;
    }
}
