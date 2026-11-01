<?php

namespace Tests\Feature;

use App\Models\AcademicProcessWindow;
use App\Models\ResearchStaff\ResearchStaffAcademicPeriod;
use App\Models\ResearchStaff\ResearchStaffAcademicProcessWindow;
use App\Models\ResearchStaff\ResearchStaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicProcessWindowTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql_research_staff', config('database.connections.sqlite'));
        config()->set('database.default', 'sqlite');
    }

    public function test_create_and_edit_forms_offer_evaluation_setting_for_idea_selection(): void
    {
        $user = $this->createAuthUser();
        $period = $this->createPeriod();

        $this->actingAs($user)
            ->get(route('academic-process-windows.create', ['academic_period_id' => $period->id]))
            ->assertOk()
            ->assertSee('id="requires_evaluation"', false)
            ->assertSee('Requiere evaluación (Postulación)');

        $window = ResearchStaffAcademicProcessWindow::query()->create([
            'academic_period_id' => $period->id,
            'process_key' => AcademicProcessWindow::PROCESS_IDEA_SELECTION,
            'name' => 'Selección de ideas',
            'start_at' => '2026-02-01 08:00:00',
            'end_at' => '2026-02-28 18:00:00',
            'is_enabled' => true,
            'requires_evaluation' => true,
        ]);

        $this->actingAs($user)
            ->get(route('academic-process-windows.edit', $window))
            ->assertOk()
            ->assertSee('id="requires_evaluation"', false)
            ->assertSee('checked', false);
    }

    public function test_create_and_update_persist_evaluation_setting_and_allow_turning_it_off(): void
    {
        $user = $this->createAuthUser();
        $period = $this->createPeriod();

        $response = $this->actingAs($user)->post(route('academic-process-windows.store'), [
            'academic_period_id' => $period->id,
            'process_key' => AcademicProcessWindow::PROCESS_IDEA_SELECTION,
            'name' => 'Selección de ideas',
            'start_at' => '2026-02-01 08:00:00',
            'end_at' => '2026-02-28 18:00:00',
            'is_enabled' => '1',
            'requires_evaluation' => '1',
        ]);

        $response->assertRedirect(route('academic-process-windows.index'));
        $window = ResearchStaffAcademicProcessWindow::query()->firstOrFail();
        $this->assertTrue($window->requires_evaluation);

        $response = $this->actingAs($user)->put(route('academic-process-windows.update', $window), [
            'academic_period_id' => $period->id,
            'process_key' => AcademicProcessWindow::PROCESS_IDEA_SELECTION,
            'name' => 'Selección de ideas actualizada',
            'start_at' => '2026-02-02 08:00:00',
            'end_at' => '2026-02-28 18:00:00',
            'is_enabled' => '1',
            'requires_evaluation' => '0',
        ]);

        $response->assertRedirect(route('academic-process-windows.index'));
        $this->assertFalse($window->fresh()->requires_evaluation);
        $this->assertSame('Selección de ideas actualizada', $window->fresh()->name);
    }

    private function createAuthUser(): ResearchStaffUser
    {
        return ResearchStaffUser::query()->create([
            'email' => 'process-windows-' . (++self::$sequence) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'research_staff',
            'state' => 1,
        ]);
    }

    private function createPeriod(): ResearchStaffAcademicPeriod
    {
        $suffix = ++self::$sequence;

        return ResearchStaffAcademicPeriod::query()->create([
            'code' => '2026-' . $suffix,
            'name' => 'Periodo académico 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'is_active' => false,
        ]);
    }
}
