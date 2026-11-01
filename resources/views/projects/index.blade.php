@extends('tablar::page')

@section('title', 'Gestion de Proyectos')

@section('content')
    @include('reports.partials.visual-report-styles')

    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Proyectos</li>
                        </ol>
                    </nav>
                    <h2 class="page-title d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg me-2 text-primary" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 21v-13l8 -4l8 4v13" />
                            <path d="M12 13l8 -4" />
                            <path d="M12 13l-8 -4" />
                            <path d="M12 13v8" />
                            <path d="M8 21h8" />
                        </svg>
                        Gestion de Proyectos
                    </h2>
                    <p class="text-muted mb-0">Consulta tus proyectos y registra nuevas ideas.</p>
                </div>
                @if ($isProfessor || ($isStudent && $enableButtonStudent))
                    <div class="col-auto ms-auto d-print-none">
                        <div class="btn-list">
                            @if ($canCreateProject)
                                <a href="{{ route('projects.create') }}" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                    Nuevo proyecto
                                </a>
                            @else
                                <button type="button" class="btn btn-primary" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                    Nuevo proyecto
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
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
            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible" role="alert">
                    {{ session('warning') }}
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
                </div>
            @endif

            @if ($isProfessor || $isStudent)
                <div class="alert {{ $proposalWindowOpen ? 'alert-info' : 'alert-warning' }} mb-3">
                    <strong>Ventana de propuesta:</strong>
                    @if ($proposalWindowOpen && $proposalWindow)
                        {{ optional($activeAcademicPeriod)->name ?? 'Periodo activo' }} -
                        {{ optional($proposalWindow->start_at)->format('d/m/Y H:i') }} a {{ optional($proposalWindow->end_at)->format('d/m/Y H:i') }}.
                    @else
                        {{ $proposalWindowMessage }}
                    @endif
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Buscar proyectos</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        @if ($isResearchStaff)
                            <input type="hidden" name="report_key" value="{{ $reportFilters['report_key'] }}">
                            <input type="hidden" name="report_from" value="{{ $reportFilters['report_from'] }}">
                            <input type="hidden" name="report_to" value="{{ $reportFilters['report_to'] }}">
                            <input type="hidden" name="report_program_id" value="{{ $reportFilters['report_program_id'] }}">
                        @endif
                        <div class="col-12 col-md-6 col-lg-4">
                            <label for="search" class="form-label">Titulo</label>
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <circle cx="10" cy="10" r="7" />
                                        <line x1="21" y1="21" x2="15" y2="15" />
                                    </svg>
                                </span>
                                <input type="search" id="search" name="search" value="{{ $search }}" class="form-control" placeholder="Titulo del proyecto">
                            </div>
                        </div>
                        @if ($isResearchStaff)
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="city_program_id" class="form-label">Programa - Ciudad</label>
                                <select name="city_program_id" id="city_program_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    @foreach ($cityPrograms as $cp)
                                        <option value="{{ $cp->id }}" {{ (string) $selectedCityProgram === (string) $cp->id ? 'selected' : '' }}>
                                            {{ $cp->program->name }} - {{ $cp->city->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12 col-md-6 col-lg-4">
                            <label for="status_id" class="form-label">Estado</label>
                            <select name="status_id" id="status_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos los estados</option>
                                @foreach ($projectStatuses as $status)
                                    <option value="{{ $status->id }}" {{ (string) $selectedStatus === (string) $status->id ? 'selected' : '' }}>
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">Aplicar filtros</button>
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Listado de proyectos</h3>
                    <div class="card-actions">
                        <span class="badge bg-azure">{{ $projects->total() }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter align-middle">
                        <thead>
                            <tr>
                                <th class="w-1">ID</th>
                                <th>Titulo</th>
                                <th>Area tematica</th>
                                <th>Estado</th>
                                <th>Profesores</th>
                                <th>Estudiantes</th>
                                <th class="w-1">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                <tr>
                                    <td>{{ $project->id }}</td>
                                    <td class="text-break">{{ $project->title }}</td>
                                    <td>{{ $project->thematicArea->name ?? 'Sin area' }}</td>
                                    @php
                                        $statusName = $project->projectStatus->name ?? 'Sin estado';
                                        $normalizedStatus = \Illuminate\Support\Str::of($statusName)->ascii()->lower()->squish()->toString();
                                        $statusClasses = [
                                            'pendiente de aprobacion' => 'bg-warning text-dark',
                                            'devuelto para correccion' => 'bg-danger text-white',
                                            'aprobado' => 'bg-success text-white',
                                            'waiting evaluation' => 'bg-primary text-white',
                                        ];
                                        $badgeClass = $statusClasses[$normalizedStatus] ?? 'bg-secondary text-white';
                                    @endphp
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-start">
                                            <span class="badge {{ $badgeClass }}">{{ $statusName }}</span>
                                            @if ($project->pending_review_due_to_age)
                                                <span class="badge bg-orange-lt text-orange">Pendiente de revision por antiguedad</span>
                                                @if ($project->elapsed_periods_since_proposal !== null)
                                                    <small class="text-secondary">{{ $project->elapsed_periods_since_proposal }} periodos transcurridos</small>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @forelse ($project->professors as $professor)
                                            <div>{{ $professor->name }} {{ $professor->last_name }}</div>
                                        @empty
                                            <span class="text-secondary">Sin profesores</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @forelse ($project->students as $student)
                                            <div>{{ $student->name }} {{ $student->last_name }}</div>
                                        @empty
                                            <span class="text-secondary">Sin estudiantes</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                                            @if ($normalizedStatus === 'devuelto para correccion' && ! $isResearchStaff)
                                                <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-primary btn-sm">
                                                    Editar
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary">No se encontraron proyectos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex flex-column flex-lg-row align-items-center justify-content-between gap-2">
                    <div class="text-secondary mb-2 mb-lg-0">Mostrando {{ $projects->firstItem() ?? 0 }} a {{ $projects->lastItem() ?? 0 }} de {{ $projects->total() }} registros</div>
                    @if ($projects->hasPages())
                        {{ $projects->links('vendor.pagination.bootstrap-5-numeric') }}
                    @endif
                </div>
            </div>

            @if ($isResearchStaff)
                <div class="card mt-3" id="projects-report">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Reporte de proyectos</h3>
                            <div class="text-muted">{{ $reportModules[$activeReportKey]['description'] ?? 'Distribucion de proyectos.' }}</div>
                        </div>
                    </div>
                    <div class="card-body project-report-shell">
                        <form method="GET" action="{{ route('projects.index') }}#projects-report" class="row g-3 align-items-end">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status_id" value="{{ $selectedStatus }}">
                            <input type="hidden" name="city_program_id" value="{{ $selectedCityProgram }}">
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="report_key" class="form-label">Que deseas comparar</label>
                                <select id="report_key" name="report_key" class="form-select">
                                    @foreach ($reportModules as $reportKey => $module)
                                        <option value="{{ $reportKey }}" @selected($activeReportKey === $reportKey)>
                                            {{ $module['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4 col-lg-2">
                                <label for="report_from" class="form-label">Desde</label>
                                <input
                                    type="date"
                                    id="report_from"
                                    name="report_from"
                                    class="form-control"
                                    value="{{ $reportFilters['report_from'] }}"
                                >
                            </div>
                            <div class="col-12 col-md-4 col-lg-2">
                                <label for="report_to" class="form-label">Hasta</label>
                                <input
                                    type="date"
                                    id="report_to"
                                    name="report_to"
                                    class="form-control"
                                    value="{{ $reportFilters['report_to'] }}"
                                >
                            </div>
                            <div class="col-12 col-md-4 col-lg-4">
                                <label for="report_program_id" class="form-label">Programa</label>
                                <select id="report_program_id" name="report_program_id" class="form-select">
                                    <option value="">Todos los programas</option>
                                    @foreach ($reportProgramOptions as $program)
                                        <option value="{{ $program->id }}" @selected((int) $reportFilters['report_program_id'] === (int) $program->id)>
                                            {{ $program->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Generar reporte</button>
                                    <a href="{{ route('projects.index') }}#projects-report" class="btn btn-outline-secondary">Limpiar</a>
                                    <a
                                        href="{{ route('projects.index', array_filter([
                                            'search' => $search,
                                            'status_id' => $selectedStatus,
                                            'city_program_id' => $selectedCityProgram,
                                            'report_key' => $activeReportKey,
                                            'report_from' => $reportFilters['report_from'],
                                            'report_to' => $reportFilters['report_to'],
                                            'report_program_id' => $reportFilters['report_program_id'],
                                            'report_export' => 'pdf',
                                        ], static fn ($value) => $value !== null && $value !== '')) }}"
                                        class="btn btn-outline-danger"
                                    >
                                        Exportar PDF
                                    </a>
                                </div>
                            </div>
                        </form>

                        <div class="text-muted small">
                            Cada diagrama tiene sus propios controles para cambiar entre dona, barras verticales y barras horizontales.
                        </div>

                        <div class="project-report-grid">
                            @forelse ($reportInsights as $insight)
                                <div class="project-report-stat">
                                    <div class="project-report-stat__label">{{ $insight['label'] }}</div>
                                    <div class="project-report-stat__value" style="font-size: 1.2rem;">
                                        {{ $insight['value'] }}
                                    </div>
                                    <div class="text-muted small mt-2">{{ $insight['caption'] }}</div>
                                </div>
                            @empty
                                <div class="project-report-stat">
                                    <div class="project-report-stat__label">Total de registros</div>
                                    <div class="project-report-stat__value">{{ $reportData['total'] }}</div>
                                </div>
                            @endforelse
                        </div>

                        <div class="project-report-visuals">
                            @forelse ($reportVisuals as $visual)
                                @include('projects.partials.report-visual', [
                                    'visual' => $visual,
                                    'groupId' => 'report-' . $loop->index,
                                ])
                            @empty
                                <div class="project-report-empty">Sin datos para construir el reporte.</div>
                            @endforelse
                        </div>

                        @if ($reportTable)
                            <div class="card project-report-table-card bg-white">
                                <div class="card-header">
                                    <div>
                                        <h4 class="card-title mb-0">{{ $reportTable['title'] }}</h4>
                                        <div class="text-muted">{{ $reportTable['description'] }}</div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table card-table table-vcenter">
                                        <thead>
                                            <tr>
                                                @foreach ($reportTable['columns'] as $column)
                                                    <th>{{ $column }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($reportTable['rows'] as $row)
                                                <tr>
                                                    @foreach ($row as $cell)
                                                        <td class="text-break">{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ count($reportTable['columns']) }}" class="text-center text-secondary">
                                                        No se encontraron registros para este reporte.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if ($isResearchStaff)
        @include('reports.partials.visual-report-scripts')
    @endif
@endsection
