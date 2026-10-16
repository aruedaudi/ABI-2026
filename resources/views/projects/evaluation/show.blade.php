{{--
    View path: projects/show.blade.php.
    Purpose: Presents a detailed summary of the project, its latest version and participants.
--}}
@extends('tablar::page')

@section('title', 'Detalle del proyecto')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Detalle</li>
                        </ol>
                    </nav>
                    <h2 class="page-title d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg me-2 text-indigo" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 20l4 -9l-4 -3l-4 3z" />
                            <path d="M8 4l-2 4l-4 .5l3 3l-.5 4l3.5 -2l3.5 2l-.5 -4l3 -3l-4 -.5l-2 -4z" />
                        </svg>
                        Proyecto #{{ $project->id }}
                    </h2>
                    <p class="text-muted mb-0">Consulta la versión más reciente de la propuesta y sus participantes asociados.</p>
                </div>
                @php
                    // Determine whether the project can be edited according to the same rules enforced by the controller.
                    $statusName = $project->projectStatus->name ?? 'Sin estado';
                    $normalizedStatus = \Illuminate\Support\Str::of($statusName)->ascii()->lower()->squish()->toString();
                @endphp
                <div class="col-auto ms-auto d-print-none">
                    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
                    <a href="{{ route('projects.versions.index', $project) }}" class="btn btn-outline-primary">Ver historial</a>
                    @if ($normalizedStatus === 'devuelto para correccion')
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">Editar</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="card-title mb-0">{{ $project->title }}</h3>
                                <small class="text-secondary">Registrado el {{ optional($project->created_at)->format('d/m/Y H:i') }}</small>
                            </div>
                            @php
                                // Reuse the badge mapping to keep the status consistent with the listing view.
                                $normalizedStatus = \Illuminate\Support\Str::of($statusName)->ascii()->lower()->squish()->toString();
                                $statusClasses = [
                                    'pendiente de aprobacion' => 'bg-warning text-dark',
                                    'devuelto para correccion' => 'bg-danger text-white',
                                    'aprobado' => 'bg-success text-white',
                                    'waiting evaluation' => 'bg-primary text-white',
                                ];
                                $badgeClass = $statusClasses[$normalizedStatus] ?? 'bg-secondary text-white';
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $statusName }}</span>
                        </div>
                        <div class="card-body">
                            <dl class="row g-3 mb-0">
                                <dt class="col-sm-4">Área temática</dt>
                                <dd class="col-sm-8">{{ $project->thematicArea->name ?? 'No definida' }}</dd>

                                <dt class="col-sm-4">Línea de investigación</dt>
                                <dd class="col-sm-8">{{ $project->thematicArea->investigationLine->name ?? 'No definida' }}</dd>

                                @if ($project->evaluation_criteria)
                                    <dt class="col-sm-4">Criterios de evaluación</dt>
                                    <dd class="col-sm-8 text-prewrap">{{ $project->evaluation_criteria }}</dd>
                                @endif

                                <dt class="col-sm-4">Última actualización</dt>
                                <dd class="col-sm-8">{{ optional($project->updated_at)->format('d/m/Y H:i') }}</dd>

                                <dt class="col-sm-4">Versión vigente</dt>
                                <dd class="col-sm-8">
                                    @if ($latestVersion)
                                        {{ $latestVersion->created_at->format('d/m/Y H:i') }}
                                    @else
                                        Sin versiones registradas
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Contenidos de la versión</h3>
                        </div>
                        <div class="card-body">
                            @if ($latestVersion && count($contentValues))
                                <dl class="row g-3 mb-0">
                                    @foreach ($contentValues as $label => $value)
                                        <dt class="col-sm-4">{{ $label }}</dt>
                                        <dd class="col-sm-8 text-prewrap">{{ $value }}</dd>
                                    @endforeach
                                </dl>
                            @else
                                <p class="text-secondary mb-0">La versión aún no registra contenidos.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Frameworks aplicados --}}
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Marcos aplicados</h3>
                        </div>
                        <div class="card-body">
                            @if(isset($frameworksSelected) && $frameworksSelected->count())
                                <div class="row g-3">
                                    @foreach ($frameworksSelected as $contentFramework)
                                        <div class="col-12">
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold">
                                                    {{ $contentFramework->framework->name ?? 'Marco' }}
                                                </span>

                                                <span class="badge bg-indigo-lt text-indigo mt-1 mb-2" style="width: fit-content;">
                                                    {{ $contentFramework->name }}
                                                </span>

                                            </div>
                                            <hr class="my-2">
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-secondary mb-0">No se han registrado marcos para este proyecto.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Evaluar proyecto</h3></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('projects.evaluation.evaluate', $project) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Resultado de la evaluación</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Aprobado">Aprobar</option>
                                        <option value="Rechazado">Rechazar</option>
                                        <option value="Devuelto para corrección">Devolver para corrección</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="comments-field" style="display:none;">
                                    <label for="comments" class="form-label">Comentarios</label>
                                    <textarea name="comments" id="comments" class="form-control" rows="3" placeholder="Indica las observaciones..."></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('projects.evaluation.index') }}" class="btn btn-outline-secondary">
                                        ← Volver al listado
                                    </a>
                                    <button type="submit" class="btn btn-primary">Enviar evaluación</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Profesores asociados</h3>
                            <span class="badge bg-primary">{{ $project->professors->count() }}</span>
                        </div>
                        <div class="card-body">
                            @forelse ($project->professors as $professor)
                                <div class="d-flex align-items-start gap-2 mb-3">
                                    <span class="avatar bg-azure-lt text-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="7" r="4" />
                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                        </svg>
                                    </span>
                                    <div>
                                        <div class="fw-semibold">{{ $professor->name }} {{ $professor->last_name }}</div>
                                        @php
                                            $professorEmail = $professor->mail ?? $professor->user?->email; // Prefer the profile email but fallback to the linked user account when available.
                                        @endphp
                                        <div class="text-secondary small">{{ $professorEmail ?? 'Correo no disponible' }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-secondary mb-0">Sin profesores asociados.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Estudiantes participantes</h3>
                            <span class="badge bg-primary">{{ $project->students->count() }}</span>
                        </div>
                        <div class="card-body">
                            @forelse ($project->students as $student)
                                <div class="d-flex align-items-start gap-2 mb-3">
                                    <span class="avatar bg-green-lt text-green">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <circle cx="12" cy="7" r="4" />
                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                        </svg>
                                    </span>
                                    <div>
                                        <div class="fw-semibold">{{ $student->name }} {{ $student->last_name }}</div>
                                        <div class="text-secondary small">Documento: {{ $student->card_id ?? 'No registrado' }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-secondary mb-0">Sin estudiantes vinculados.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    document.getElementById('status').addEventListener('change', function() {
        const commentsField = document.getElementById('comments-field');
        commentsField.style.display = this.value === 'Devuelto para correccion' ? 'block' : 'none';
    });
</script>
@endsection

@push('css')
    <style>
        .text-prewrap {
            white-space: pre-wrap;
        }
        .avatar {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
@endpush





