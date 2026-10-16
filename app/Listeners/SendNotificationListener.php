<?php

namespace App\Listeners;

use App\Events\ProjectIdeaEvaluated;
use App\Events\UserCreated;
use App\Events\AcademicProcessWindowOpened;
use App\Events\AcademicProcessWindowClosing;
use App\Models\ResearchStaff\ResearchStaffUser;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class SendNotificationListener
{

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof ProjectIdeaEvaluated) {
            $this->handleProjectIdeaEvaluated($event);
        } elseif ($event instanceof UserCreated) {
            $this->handleUserCreated($event);
        } elseif ($event instanceof AcademicProcessWindowOpened) {
            $this->handleAcademicProcessWindowOpened($event);
        } elseif ($event instanceof AcademicProcessWindowClosing) {
            $this->handleAcademicProcessWindowClosing($event);
        }
    }

    /**
     * Handle AcademicProcessWindowOpened event.
     */
    protected function handleAcademicProcessWindowOpened(AcademicProcessWindowOpened $event): void
    {
        $window = $event->window;
        $recipients = $this->resolveAllRelevantRecipients();
        
        $subject = "Nueva Convocatoria Abierta: " . $window->name;
        $view = 'emails.projects.window-opened';
        
        $content = [
            'windowName' => $window->name,
            'period' => $window->academicPeriod?->name,
            'endDate' => $window->end_at?->format('d/m/Y') ?? 'Fecha por definir',
            'url' => route('projects.create')
        ];

        foreach ($recipients as $recipient) {
            $this->notificationService->sendNotification($recipient, $subject, $view, $content);
        }
    }

    /**
     * Handle AcademicProcessWindowClosing event.
     */
    protected function handleAcademicProcessWindowClosing(AcademicProcessWindowClosing $event): void
    {
        $window = $event->window;
        $recipients = $this->resolveAllRelevantRecipients();
        
        $subject = "Recordatorio: La convocatoria finaliza pronto";
        $view = 'emails.projects.window-closing';
        
        $content = [
            'windowName' => $window->name,
            'daysLeft' => $event->daysLeft,
            'endDate' => $window->end_at?->format('d/m/Y H:i') ?? 'Fecha por definir',
            'url' => route('projects.create')
        ];

        foreach ($recipients as $recipient) {
            $this->notificationService->sendNotification($recipient, $subject, $view, $content);
        }
    }

    /**
     * Resolve all students and professors emails.
     */
    protected function resolveAllRelevantRecipients(): array
    {
        return ResearchStaffUser::query()
            ->where('state', 1)
            ->whereIn('role', ['student', 'professor', 'committee_leader'])
            ->pluck('email')
            ->filter()
            ->unique()
            ->toArray();
    }

    /**
     * Handle ProjectIdeaEvaluated event.
     */
    protected function handleProjectIdeaEvaluated(ProjectIdeaEvaluated $event): void
    {
        $project = $event->project;
        $recipients = $this->resolveProjectRecipients($project);

        Log::info('ProjectIdeaEvaluated recipients', [
            'project_id' => $project->id,
            'recipients' => $recipients,
        ]);

        if (empty($recipients)) {
            Log::warning('No email recipients found for project evaluation', [
                'project_id' => $project->id,
                'project_title' => $project->title,
            ]);
            return;
        }

        $subject = "Actualización de estado: " . $project->title;
        $view = 'emails.projects.evaluated';
        
        $content = [
            'projectTitle' => $project->title,
            'status' => $event->status,
            'comments' => $event->comments,
            'url' => route('projects.show', $project->id)
        ];

        foreach ($recipients as $recipient) {
            $this->notificationService->sendNotification($recipient, $subject, $view, $content);
        }
    }

    /**
     * Handle UserCreated event.
     */
    protected function handleUserCreated(UserCreated $event): void
    {
        try {
            $user = $event->user;
            $recipient = $user->email;
            $subject = "Bienvenido a ABI";
            $view = 'emails.users.welcome';

            $content = [
                'name' => $event->data['name'] ?? $user->name,
                'email' => $user->email,
                'password' => $event->data['password'] ?? '********',
                'role' => $user->role,
                'url' => route('login')
            ];

            if ($recipient) {
                $this->notificationService->sendNotification($recipient, $subject, $view, $content);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in UserCreated notification: " . $e->getMessage());
        }
    }

    /**
     * Resolve who should receive project notifications.
     */
    protected function resolveProjectRecipients($project): array
    {

        $emails = [];

        // Add students
        foreach ($project->students as $student) {
            if ($student->user && $student->user->email) {
                $emails[] = $student->user->email;
            }
        }

        // Add professors
        foreach ($project->professors as $professor) {
            if ($professor->user && $professor->user->email) {
                $emails[] = $professor->user->email;
            }
        }

        return array_unique($emails);
    }
}
