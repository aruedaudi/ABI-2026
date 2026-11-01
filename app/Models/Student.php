<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * students table model, manages communication with the database using the root user,
 * should not be used by any end user,
 * always use an inherited model with the connection specific to each role.
 */
class Student extends Model
{
    use HasFactory, SoftDeletes;

    public const PG_STAGE_PG1 = 'pg1';

    public const PG_STAGE_PG2 = 'pg2';

    /**
     * Project statuses that do not count as an active assignment.
     * A student whose projects are only in these states may apply to a new idea.
     *
     * @var array<int, string>
     */
    public const INACTIVE_PROJECT_STATUSES = [
        'Rechazado',
        'Descartado',
        'Devuelto para correccion',
        'Devuelto para corrección',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'card_id',
        'name',
        'last_name',
        'phone',
        'semester',
        'pg_stage',
        'city_program_id',
        'user_id',
    ];

    protected $casts = [
        'semester' => 'integer',
    ];

    /**
     * Resolve the model class used for the projects relationship.
     */
    protected function getProjectModelClass(): string
    {
        return Project::class;
    }

    /**
     * Get the user associated with the student.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the city program that the student belongs to.
     */
    public function cityProgram()
    {
        return $this->belongsTo(CityProgram::class, 'city_program_id', 'id');
    }

    /**
     * Get the projects assigned to the student.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->getProjectModelClass(),
            'student_project',
            'student_id',
            'project_id'
        )->withTimestamps();
    }

    // Postulaciones donde es líder
    public function postulations()
    {
        return $this->hasMany(Postulation::class, 'lead_student_id', 'id');
    }

    // Postulaciones donde aparece como integrante o líder
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(
            Postulation::class,
            'postulation_members',
            'student_id',
            'postulation_id'
        )->withPivot('role_description', 'is_lead');
    }

    // Cuántas postulaciones activas tiene (máximo 3)
    public function activeApplicationsCount(): int
    {
        return $this->applications()
            ->whereIn('status', ['pending', 'approved'])
            ->count();
    }

    public function hasActiveProject(): bool
    {
        return $this->projects()
            ->whereHas('projectStatus', fn ($status) => $status->whereNotIn('name', self::INACTIVE_PROJECT_STATUSES))
            ->exists();
    }

    /**
     * Get the student's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }
}
