@extends('tablar::page')

@section('title', 'Seleccionar Idea Aprobada')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('students.projects.approved.index') }}">Ideas Aprobadas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Seleccionar Idea</li>
                    </ol>
                </nav>
                <h2 class="page-title mb-0">
                    Seleccionar Idea Aprobada
                </h2>
                <p class="text-muted">Asume esta idea para ejecutarla bajo tu responsabilidad.</p>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="alert alert-info mb-3">
            <strong>Ventana de seleccion:</strong>
            {{ optional($activeAcademicPeriod)->name ?? 'Periodo activo' }} ·
            {{ optional($selectionWindow->start_at)->format('d/m/Y H:i') }} a {{ optional($selectionWindow->end_at)->format('d/m/Y H:i') }}.
        </div>

        @if(isset($selectionWindow) && $selectionWindow->requires_evaluation)
            <div class="alert alert-warning">
                <h4 class="alert-title">¡Atención! Proceso de Postulación Activo</h4>
                <div class="text-secondary">
                    Para este periodo, no se permite la asignación directa. Debes enviar una postulación para que sea revisada por un evaluador.
                </div>
                <div class="mt-3">
                    <a href="{{ route('students.postulations.create', $project) }}" class="btn btn-warning w-100">
                        Ir al formulario de Postulación
                    </a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('projects.student.assign', $project) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-lg-8">

                        {{-- Resumen del Proyecto --}}
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="card-title mb-1">{{ $project->title }}</h3>
                                    <small class="text-secondary">
                                        Área: {{ $project->thematicArea->name ?? 'No definida' }} •
                                        Línea: {{ $project->thematicArea->investigationLine->name ?? 'No definida' }}
                                    </small>
                                </div>
                                <span class="badge bg-indigo">Aprobado</span>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary mb-0">
                                    Estás a punto de asumir este proyecto. Si continúas, el estado cambiará a
                                    <span class="fw-semibold text-dark">Asignado</span> y pasará a tu responsabilidad.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <a href="{{ route('students.projects.approved.index') }}" class="btn btn-outline-secondary w-50">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary w-50">
                                Confirmar Selección
                            </button>
                        </div>
                    </div>

                    {{-- Info Lateral --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">Información Clave</h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small text-secondary mb-0">
                                    <li>• El proyecto pasará a estado <strong>Asignado</strong></li>
                                    <li>• Serás responsable de su ejecución</li>
                                    <li>• Podrás verlo luego en tu listado de proyectos</li>
                                    <li>• Te asignarás únicamente a ti en este proyecto</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        @endif

    </div>
</div>
@endsection