<?php

namespace App\Enums;

enum WorkflowTransition: string
{
    case REGISTERED = 'registered';
    case WAITING_SURVEY = 'waiting_survey';
    case SURVEY_IN_PROGRESS = 'survey_in_progress';
    case SURVEYED = 'surveyed';
    case WAITING_ACC = 'waiting_acc';
    case WAITING_INSTALLATION = 'waiting_installation';
    case INSTALLATION_IN_PROGRESS = 'installation_in_progress';
    case INSTALLED = 'installed';
    case VERIFICATION_ADMIN = 'verification_admin';
    case REVISION_INSTALLATION = 'revision_installation';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
    case REJECTED = 'rejected';

    /**
     * Define the valid next statuses for each status.
     * This acts as the rules engine for our state machine.
     *
     * @return array<WorkflowTransition>
     */
    public function allowedNextTransitions(): array
    {
        return match ($this) {
            // WAITING_INSTALLATION ditambah khusus buat Skip Survey (Sales input
            // data survey langsung saat registrasi) — lompat tahap survey
            // lapangan DAN ACC Admin sepenuhnya. Lihat CustomerController::store().
            self::REGISTERED => [self::WAITING_SURVEY, self::WAITING_INSTALLATION, self::REJECTED],
            self::WAITING_SURVEY => [self::SURVEY_IN_PROGRESS, self::REJECTED],
            self::SURVEY_IN_PROGRESS => [self::WAITING_ACC, self::SURVEYED, self::REJECTED],
            self::SURVEYED => [self::WAITING_ACC, self::WAITING_INSTALLATION, self::REJECTED],
            self::WAITING_ACC => [self::WAITING_INSTALLATION, self::SURVEY_IN_PROGRESS, self::REJECTED],
            self::WAITING_INSTALLATION => [self::INSTALLATION_IN_PROGRESS, self::REJECTED],
            self::INSTALLATION_IN_PROGRESS => [self::VERIFICATION_ADMIN, self::INSTALLED, self::WAITING_INSTALLATION, self::REJECTED],
            self::INSTALLED => [self::VERIFICATION_ADMIN, self::WAITING_INSTALLATION, self::REJECTED],
            self::VERIFICATION_ADMIN => [self::ACTIVE, self::WAITING_INSTALLATION, self::REVISION_INSTALLATION, self::INSTALLATION_IN_PROGRESS, self::REJECTED],
            self::REVISION_INSTALLATION => [self::VERIFICATION_ADMIN, self::INSTALLED, self::WAITING_INSTALLATION, self::REJECTED],
            self::ACTIVE => [self::INSTALLED, self::VERIFICATION_ADMIN, self::REVISION_INSTALLATION, self::SUSPENDED, self::TERMINATED],
            self::SUSPENDED => [self::ACTIVE, self::TERMINATED],
            self::TERMINATED => [],
            self::REJECTED => [],
        };
    }

    /**
     * Check if a transition is allowed.
     */
    public function canTransitionTo(WorkflowTransition $nextTransition): bool
    {
        return in_array($nextTransition, $this->allowedNextTransitions(), true);
    }
}
