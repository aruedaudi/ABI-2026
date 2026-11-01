<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ThematicArea;
use App\Models\Professor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()
            ->with(['thematicArea', 'projectStatus', 'professors', 'students']);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status_id')) {
            $query->where('project_status_id', $request->status_id);
        }

        if ($request->filled('thematic_area_id')) {
            $query->where('thematic_area_id', $request->thematic_area_id);
        }

        if ($request->filled('professor_id')) {
            $query->whereHas('professors', function ($q) use ($request) {
                $q->where('professors.id', $request->professor_id);
            });
        }

        if ($request->filled('student_id')) {
            $query->whereHas('students', function ($q) use ($request) {
                $q->where('students.id', $request->student_id);
            });
        }

        return response()->json(
            $query->orderByDesc('id')->paginate(
                min(max((int) $request->input('per_page', 15), 1), 100)
            )
        );
    }

    public function show(Project $project)
    {
        return response()->json([
            'data' => $project->load([
                'thematicArea',
                'projectStatus',
                'professors',
                'students',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'evaluation_criteria' => ['nullable', 'string'],
            'thematic_area_id' => [
                'required', 'integer',
                Rule::exists('thematic_areas', 'id'),
            ],
            'project_status_id' => [
                'required', 'integer',
                Rule::exists('project_statuses', 'id'),
            ],
            'professor_ids' => ['sometimes', 'array'],
            'professor_ids.*' => [
                'integer', Rule::exists('professors', 'id'),
            ],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => [
                'integer', Rule::exists('students', 'id'),
            ],
        ]);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create([
                'title' => $data['title'],
                'evaluation_criteria' => $data['evaluation_criteria'] ?? null,
                'thematic_area_id' => $data['thematic_area_id'],
                'project_status_id' => $data['project_status_id'],
            ]);

            $project->professors()->sync($data['professor_ids'] ?? []);
            $project->students()->sync($data['student_ids'] ?? []);

            return $project->load([
                'thematicArea', 'projectStatus', 'professors', 'students',
            ]);
        });

        return response()->json([
            'message' => 'Proyecto creado correctamente.',
            'data' => $project,
        ], 201);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'evaluation_criteria' => ['sometimes', 'nullable', 'string'],
            'thematic_area_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('thematic_areas', 'id'),
            ],
            'project_status_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('project_statuses', 'id'),
            ],
            'professor_ids' => ['sometimes', 'array'],
            'professor_ids.*' => [
                'integer', Rule::exists('professors', 'id'),
            ],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => [
                'integer', Rule::exists('students', 'id'),
            ],
        ]);

        DB::transaction(function () use ($data, $project) {
            $project->fill(collect($data)->only([
                'title',
                'evaluation_criteria',
                'thematic_area_id',
                'project_status_id',
            ])->all())->save();

            if (array_key_exists('professor_ids', $data)) {
                $project->professors()->sync($data['professor_ids']);
            }

            if (array_key_exists('student_ids', $data)) {
                $project->students()->sync($data['student_ids']);
            }
        });

        return response()->json([
            'message' => 'Proyecto actualizado correctamente.',
            'data' => $project->refresh()->load([
                'thematicArea', 'projectStatus', 'professors', 'students',
            ]),
        ]);
    }

    public function destroy(Project $project)
    {
        if ($project->versions()->exists()) {
            return response()->json([
                'message' => 'El proyecto tiene versiones asociadas.',
            ], 409);
        }

        $project->delete();

        return response()->noContent();
    }

    public function restore(int $project)
    {
        $item = Project::withTrashed()->findOrFail($project);

        if (!$item->trashed()) {
            return response()->json([
                'message' => 'El proyecto no está eliminado.',
            ], 409);
        }

        $item->restore();

        return response()->json([
            'message' => 'Proyecto restaurado correctamente.',
            'data' => $item->fresh(),
        ]);
    }

    public function meta()
    {
        return response()->json([
            'data' => [
                'thematic_areas' => ThematicArea::query()->get(),
                'project_statuses' => ProjectStatus::query()->get(),
                'professors' => Professor::query()->get(),
                'students' => Student::query()->get(),
            ],
        ]);
    }
}
