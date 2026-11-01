<?php

namespace App\Http\Controllers;

use App\Models\Postulation;
use App\Models\PostulationPriority;
use App\Models\Professor;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Services\AcademicCalendar\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostulationEvaluationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $professor = Professor::where('user_id', $user->id)->firstOrFail();

        $query = Postulation::with(['project', 'leadStudent', 'members.student'])
            ->whereHas('leadStudent', function ($q) use ($professor) {
                $q->where('city_program_id', $professor->city_program_id);
            })
            ->pending();

        $postulations = $query->orderBy('created_at', 'desc')->get();

        return view('postulations.evaluation.index', compact('postulations'));
    }

    public function show(Postulation $postulation)
    {
        $user = Auth::user();
        $professor = Professor::where('user_id', $user->id)->firstOrFail();

        // Validar que el líder de la postulación sea del mismo programa/ciudad que el evaluador
        if ($postulation->leadStudent->city_program_id !== $professor->city_program_id) {
            abort(403, 'No tienes permiso para ver esta postulación.');
        }

        $postulation->load(['project', 'leadStudent', 'members.student', 'priorities']);

        $canApprove = $postulation->project->isAvailableForStudent($postulation->leadStudent);

        return view('postulations.evaluation.show', compact('postulation', 'canApprove'));
    }

     public function evaluate(Request $request, Postulation $postulation)
    {
        $user = Auth::user();
        $professor = Professor::where('user_id', $user->id)->firstOrFail();

        // Validar permisos de city_program
        if ($postulation->leadStudent->city_program_id !== $professor->city_program_id) {
            abort(403, 'No tienes permiso para evaluar esta postulación.');
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'review_comment' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Serializar las aprobaciones de un mismo proyecto para que dos
            // evaluaciones simultáneas no puedan reemplazar la asignación.
            $postulation = Postulation::whereKey($postulation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($postulation->status !== 'pending') {
                DB::rollBack();

                return back()->with('error', 'Esta postulación ya ha sido evaluada.');
            }

            $project = Project::whereKey($postulation->project_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status === 'approved') {
                $assignedStatusId = ProjectStatus::where('name', 'Asignado')->value('id');
                $alreadyAssigned = $project->approved_postulation_id !== null
                    || ($assignedStatusId !== null && (int) $project->project_status_id === (int) $assignedStatusId);

                if ($alreadyAssigned) {
                    DB::rollBack();

                    return back()->with(
                        'error',
                        'No se puede aprobar esta postulación: el proyecto ya fue asignado a otra postulación.'
                    );
                }

                if (! $project->isAvailableForStudent($postulation->leadStudent)) {
                    DB::rollBack();

                    return back()->with(
                        'error',
                        'No se puede aprobar esta postulación: el proyecto no está disponible para postulación (debe estar "Aprobado" y pertenecer al programa del estudiante).'
                    );
                }
            }

            $postulation->update([
                'status' => $request->status,
                'review_comment' => $request->review_comment,
            ]);

            // Si se rechaza, liberar la prioridad
            if ($request->status === 'rejected') {
                $this->cleanupPriority($postulation);
            }

            if ($request->status === 'approved') {
                $activePeriod = AcademicCalendarService::currentActivePeriodOrFail();
                $assignedStatusId = ProjectStatus::where('name', 'Asignado')->firstOrFail()->id;

                // 1. Asignar proyecto y vincular con la postulación aprobada
                $project->update([
                    'project_status_id' => $assignedStatusId,
                    'approved_postulation_id' => $postulation->postulation_id,
                    'assignment_academic_period_id' => $activePeriod->id,
                    'assigned_at' => now(),
                ]);

                // 2. Sincronizar estudiantes con la referencia de la postulación
                $studentIds = $postulation->members->pluck('student_id')->toArray();
                
                // Usamos sync con datos adicionales para el pivot si es necesario, 
                // o simplemente adjuntamos la postulation_id
                $syncData = [];
                foreach ($studentIds as $id) {
                    $syncData[$id] = ['postulation_id' => $postulation->postulation_id];
                }
                $project->students()->sync($syncData);

                // 3. Cancelar otras postulaciones para TODOS los integrantes involucrados
                foreach ($studentIds as $studentId) {
                    $otherPostulations = Postulation::where('status', 'pending')
                        ->where('postulation_id', '!=', $postulation->postulation_id)
                        ->whereHas('members', function ($q) use ($studentId) {
                            $q->where('student_id', $studentId);
                        })
                        ->get();

                    foreach ($otherPostulations as $other) {
                        $other->update([
                            'status' => 'rejected',
                            'review_comment' => 'Cancelada automáticamente por aprobación de otra postulación.',
                        ]);
                        // Liberar prioridades de las postulaciones canceladas
                        $this->cleanupPriority($other);
                    }
                }

                AcademicCalendarService::recordProjectStage(
                    $project,
                    'assigned',
                    $activePeriod,
                    Auth::id(),
                    'Proyecto asignado vía aprobación de postulación. '.$request->review_comment,
                    ['postulation_id' => $postulation->postulation_id, 'student_ids' => $studentIds]
                );
            }

            DB::commit();

            return redirect()->route('projects.evaluation.postulations.index')
                ->with('success', 'Postulación evaluada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            // Guardamos el error real en el log (solo lo ven los programadores)
            \Log::error('Error al evaluar la postulación: ' . $e->getMessage(), ['exception' => $e]);
            
            // Al comité le mostramos un mensaje amigable sin el SQL
            return back()->with('error', 'Ocurrió un error inesperado al evaluar la postulación. Por favor, inténtalo de nuevo.');
        }
    }
    /**
     * Libera y reordena las prioridades de un estudiante cuando una postulación es rechazada.
     */
    private function cleanupPriority(Postulation $postulation): void
    {
        $priorityRecord = PostulationPriority::where('postulation_id', $postulation->postulation_id)
            ->first();

        if ($priorityRecord) {
            $studentId = $priorityRecord->student_id;
            $order = $priorityRecord->priority_order;

            // Eliminar el registro de prioridad actual
            $priorityRecord->delete();

            // Reordenar las prioridades superiores del mismo estudiante para cerrar el hueco
            PostulationPriority::where('student_id', $studentId)
                ->where('priority_order', '>', $order)
                ->decrement('priority_order');
        }
    }

    public function downloadGrades(Request $request, Postulation $postulation)
    {
        $user = Auth::user();
        $professor = Professor::where('user_id', $user->id)->firstOrFail();

        // Validar permisos de city_program
        if ($postulation->leadStudent->city_program_id !== $professor->city_program_id) {
            abort(403, 'No tienes permiso para acceder a este archivo.');
        }

        if (! Storage::disk('local')->exists($postulation->grades_file)) {
            abort(404);
        }

        if ($request->has('view')) {
            return Storage::disk('local')->response($postulation->grades_file);
        }

        return Storage::disk('local')->download($postulation->grades_file, "notas_{$postulation->leadStudent->card_id}.pdf");
    }
}
