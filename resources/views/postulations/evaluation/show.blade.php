@extends('tablar::page')

@section('title', 'Detalle de Postulación')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">Detalle de Postulación</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Justificación e Interés</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-justify" style="white-space: pre-wrap;">{{ $postulation->justification }}</p>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Integrantes Propuestos</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table card-table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Carné</th>
                                        <th>Rol</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($postulation->members as $member)
                                        <tr>
                                            <td>
                                                {{ $member->student->name }} {{ $member->student->last_name }}
                                                @if ($member->is_lead) <span class="badge bg-blue-lt">Líder</span> @endif
                                            </td>
                                            <td>{{ $member->student->card_id }}</td>
                                            <td>{{ $member->role_description }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Resultado de la Evaluación</h3>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('projects.evaluation.postulations.evaluate', $postulation) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label required">Comentarios / Observaciones</label>
                                    <textarea name="review_comment" class="form-control @error('review_comment') is-invalid @enderror" rows="4" placeholder="Indica las razones de la aprobación o rechazo..." required></textarea>
                                    @error('review_comment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                
                                @unless ($canApprove)
                                    <div class="alert alert-warning">
                                        Esta idea no está disponible para postulación (estado: {{ $postulation->project->projectStatus->name }}). Solo puedes rechazar la solicitud.
                                    </div>
                                @endunless

                                <div class="d-flex gap-2">
                                    @if ($canApprove)
                                    <button type="submit" name="status" value="approved" class="btn btn-success" onclick="return confirm('¿Confirmas que deseas APROBAR esta postulación? Se asignará la idea y se cancelarán otras solicitudes de los estudiantes.')">Aprobar Postulación</button>
                                    @endif
                                    <button type="submit" name="status" value="rejected" class="btn btn-danger" onclick="return confirm('¿Estás seguro de RECHAZAR esta postulación?')">Rechazar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Información del Proyecto</h3>
                        </div>
                        <div class="card-body">
                            <h4>{{ $postulation->project->title }}</h4>
                            <p class="text-muted small">{{ $postulation->project->thematicArea->name }}</p>
                            <hr>
                            <div class="mb-2">
                                <strong>Estado de la Idea:</strong>
                                <span class="badge bg-azure-lt">{{ $postulation->project->projectStatus->name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Documentación</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between p-2 border rounded">
                                <div class="d-flex align-items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-red" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M14 3v4a1 1 0 0 0 1 1h4"></path>
                                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path>
                                        <path d="M9 12l2 2l4 -4"></path>
                                    </svg>
                                    <span class="fw-bold">Historial Notas</span>
                                </div>
                                <div class="btn-list">
                                    <a href="{{ route('projects.evaluation.postulations.download-grades', [$postulation, 'view' => 1]) }}" 
                                       onclick="window.open(this.href, 'historico', 'width=700,height=700,scrollbars=yes'); return false;"
                                       class="btn btn-icon btn-outline-primary" 
                                       title="Ver PDF">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path>
                                            <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('projects.evaluation.postulations.download-grades', $postulation) }}" 
                                       class="btn btn-icon btn-outline-info" 
                                       title="Descargar PDF">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-download" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"></path>
                                            <path d="M7 11l5 5l5 -5"></path>
                                            <path d="M12 4l0 12"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
