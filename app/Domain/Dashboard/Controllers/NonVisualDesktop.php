<?php

namespace Leantime\Domain\Dashboard\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Setting\Services\Setting;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class NonVisualDesktop extends Controller
{
    private Setting $settingsService;

    private ProjectService $projectService;

    private TicketService $ticketService;

    public function init(
        Setting $settingsService,
        ProjectService $projectService,
        TicketService $ticketService
    ): void {
        $this->settingsService = $settingsService;
        $this->projectService = $projectService;
        $this->ticketService = $ticketService;

        session(['lastPage' => BASE_URL.'/dashboard/nonVisualDesktop']);
    }

    public function get(): Response
    {
        Auth::authOrRedirect();

        $isEnabled = $this->settingsService->getSetting('usersettings.'.session('userdata.id').'.nonVisualDesktop');
        if (! in_array(strtolower((string) $isEnabled), ['1', 'true', 'on', 'yes'], true)) {
            $this->tpl->setNotification('Non Visual Desktop is not enabled for your account.', 'error');

            return Frontcontroller::redirect(BASE_URL.'/dashboard/home');
        }

        $projects = $this->projectService->getProjectsAssignedToUser(session('userdata.id'), 'open');
        $ticketData = $this->ticketService->getToDoWidgetAssignments([
            'groupBy' => 'project',
            'projectFilter' => '',
        ]);

        $tickets = [];
        foreach (($ticketData['tickets'] ?? []) as $group) {
            foreach (($group['tickets'] ?? []) as $ticket) {
                $tickets[] = $ticket;
            }
        }

        usort($tickets, function (array $a, array $b): int {
            return strcmp((string) ($a['dateToFinish'] ?? ''), (string) ($b['dateToFinish'] ?? ''));
        });

        $this->tpl->assign('projects', is_array($projects) ? $projects : []);
        $this->tpl->assign('tickets', $tickets);
        $this->tpl->assign('statusLabels', $ticketData['statusLabels'] ?? []);

        return $this->tpl->display('dashboard.nonVisualDesktop');
    }
}
