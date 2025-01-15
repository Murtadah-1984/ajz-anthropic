class PlanningSession extends BaseSession
{
    public function start(): void
    {
        $this->status = 'sprint_planning';
        $this->processSprintPlanning();
    }

    private function processSprintPlanning(): void
    {
        // Product Owner (AI) presents backlog
        // Scrum Master (AI) facilitates
        // Dev Team (AI) estimates and commits
    }
}
