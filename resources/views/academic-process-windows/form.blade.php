@php
    $isEdit = isset($window) && $window->exists;
    $ideaSelectionProcess = \App\Models\AcademicProcessWindow::PROCESS_IDEA_SELECTION;
    $selectedProcess = old('process_key', $window->process_key ?? '');
    $canRequireEvaluation = $selectedProcess === $ideaSelectionProcess;
@endphp

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="academic_period_id" class="form-label required">Periodo academico</label>
        <select id="academic_period_id" name="academic_period_id" class="form-select {{ $errors->has('academic_period_id') ? 'is-invalid' : '' }}" required>
            <option value="">Selecciona un periodo...</option>
            @foreach($periods as $id => $periodName)
                <option value="{{ $id }}" {{ (string) old('academic_period_id', $window->academic_period_id ?? '') === (string) $id ? 'selected' : '' }}>{{ $periodName }}</option>
            @endforeach
        </select>
        @error('academic_period_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="process_key" class="form-label required">Proceso</label>
        <select id="process_key" name="process_key" class="form-select {{ $errors->has('process_key') ? 'is-invalid' : '' }}" required>
            <option value="">Selecciona un proceso...</option>
            @foreach($processOptions as $value => $label)
                <option value="{{ $value }}" {{ old('process_key', $window->process_key ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('process_key')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <label for="name" class="form-label">Nombre interno</label>
        <input type="text" id="name" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" value="{{ old('name', $window->name ?? '') }}" placeholder="Ej: Cierre seleccion ideas 2026-1">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12 col-md-6">
        <label for="start_at" class="form-label required">Apertura</label>
        <input type="datetime-local" id="start_at" name="start_at" class="form-control {{ $errors->has('start_at') ? 'is-invalid' : '' }}" value="{{ old('start_at', optional($window->start_at)->format('Y-m-d\\TH:i')) }}" required>
        @error('start_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="end_at" class="form-label required">Cierre</label>
        <input type="datetime-local" id="end_at" name="end_at" class="form-control {{ $errors->has('end_at') ? 'is-invalid' : '' }}" value="{{ old('end_at', optional($window->end_at)->format('Y-m-d\\TH:i')) }}" required>
        @error('end_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-3">
    <label for="notes" class="form-label">Observaciones</label>
    <textarea id="notes" name="notes" rows="3" class="form-control {{ $errors->has('notes') ? 'is-invalid' : '' }}">{{ old('notes', $window->notes ?? '') }}</textarea>
    @error('notes')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row g-3 mt-1">
    <div class="col-12 col-md-6">
        <label class="form-check form-switch">
            <input type="hidden" name="is_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $window->is_enabled ?? true) ? 'checked' : '' }}>
            <span class="form-check-label">Habilitada</span>
        </label>
        <small class="form-hint text-muted">Si la ventana está activa para los usuarios.</small>
    </div>

    <input type="hidden" name="requires_evaluation" value="0">
    <div
        id="requires-evaluation-group"
        class="col-12 col-md-6"
        data-idea-selection-process="{{ $ideaSelectionProcess }}"
    >
        <label class="form-check form-switch">
            <input
                id="requires_evaluation"
                class="form-check-input"
                type="checkbox"
                name="requires_evaluation"
                value="1"
                {{ old('requires_evaluation', $window->requires_evaluation ?? false) ? 'checked' : '' }}
                {{ $canRequireEvaluation ? '' : 'disabled' }}
                aria-describedby="requires-evaluation-help"
            >
            <span class="form-check-label">Requiere evaluación</span>
        </label>
        <small id="requires-evaluation-help" class="form-hint text-muted">Disponible para el proceso Selección de ideas. Si se activa, el estudiante debe postularse y esperar aprobación; si se desactiva, la asignación es directa.</small>
    </div>
</div>

<hr class="my-4">

<div class="form-footer d-flex justify-content-end gap-2">
    <a href="{{ route('academic-process-windows.index') }}" class="btn btn-link">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Actualizar ventana' : 'Crear ventana' }}</button>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const processSelect = document.getElementById('process_key');
    const requiresEvaluationGroup = document.getElementById('requires-evaluation-group');
    const requiresEvaluationCheckbox = document.getElementById('requires_evaluation');

    if (!processSelect || !requiresEvaluationGroup || !requiresEvaluationCheckbox) {
        return;
    }

    const ideaSelectionProcess = requiresEvaluationGroup.dataset.ideaSelectionProcess;

    const syncRequiresEvaluationVisibility = () => {
        const canRequireEvaluation = processSelect.value === ideaSelectionProcess;

        requiresEvaluationCheckbox.disabled = !canRequireEvaluation;
    };

    processSelect.addEventListener('change', syncRequiresEvaluationVisibility);

    syncRequiresEvaluationVisibility();
});
</script>
@endpush
