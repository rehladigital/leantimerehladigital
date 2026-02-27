<?php

namespace Leantime\Domain\Users\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Clients\Repositories\Clients as ClientRepository;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Leantime\Domain\Setting\Services\Setting as SettingService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Domain\Users\Services\Users;
use Ramsey\Uuid\Uuid;

class EditUser extends Controller
{
    private ProjectRepository $projectsRepo;

    private UserRepository $userRepo;

    private ClientRepository $clientsRepo;

    private Users $userService;

    private SettingService $settingsService;

    /**
     * init - initialize private variables
     */
    public function init(
        ProjectRepository $projectsRepo,
        UserRepository $userRepo,
        ClientRepository $clientsRepo,
        Users $userService,
        SettingService $settingsService
    ) {
        $this->projectsRepo = $projectsRepo;
        $this->userRepo = $userRepo;
        $this->clientsRepo = $clientsRepo;
        $this->userService = $userService;
        $this->settingsService = $settingsService;
    }

    /**
     * run - display template and edit data
     */
    public function run()
    {

        Auth::authOrRedirect([Roles::$owner, Roles::$admin], true);

        // Only admins

        if (isset($_GET['id']) === true) {
            $id = (int) ($_GET['id']);
            $row = $this->userRepo->getUser($id);
            $edit = false;
            $infoKey = '';

            // Build values array
            $values = [
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'user' => $row['username'],
                'phone' => $row['phone'],
                'status' => $row['status'],
                'role' => $row['role'],
                'hours' => $row['hours'],
                'wage' => $row['wage'],
                'clientId' => $row['clientId'],
                'clientIds' => $this->userRepo->getUserClientIds($id),
                'source' => $row['source'],
                'pwReset' => $row['pwReset'],
                'jobTitle' => $row['jobTitle'],
                'jobLevel' => $row['jobLevel'],
                'department' => $row['department'],
                'nonVisualDesktop' => false,
            ];

            $nonVisualSetting = $this->settingsService->getSetting('usersettings.'.$id.'.nonVisualDesktop');
            $values['nonVisualDesktop'] = in_array(strtolower((string) $nonVisualSetting), ['1', 'true', 'on', 'yes'], true);

            if (isset($_GET['resendInvite']) && $row !== false) {
                if (! session()->exists('lastInvite.'.$values['id']) ||
                    session('lastInvite.'.$values['id']) < time() - 240) {
                    session(['lastInvite.'.$values['id'] => time()]);

                    // If pw reset is empty for whatever reason, create new invite code
                    if (empty($values['pwReset'])) {
                        $inviteCode = Uuid::uuid4()->toString();
                        $this->userRepo->patchUser($values['id'], ['pwReset' => $inviteCode]);
                        $values['pwReset'] = $inviteCode;
                    }

                    $this->userService->sendUserInvite(
                        inviteCode: $values['pwReset'],
                        user: $values['user']
                    );

                    $this->tpl->setNotification($this->language->__('notification.invitation_sent'), 'success', 'userinvitation_sent');
                } else {
                    $this->tpl->setNotification($this->language->__('notification.invite_too_soon'), 'error');
                }

                Frontcontroller::redirect(BASE_URL.'/users/editUser/'.$values['id']);
            }

            if (isset($_POST['save'])) {
                if (isset($_POST[session('formTokenName')]) && $_POST[session('formTokenName')] == session('formTokenValue')) {
                    $values = [
                        'id' => $row['id'],
                        'firstname' => ($_POST['firstname'] ?? $row['firstname']),
                        'lastname' => ($_POST['lastname'] ?? $row['lastname']),
                        'user' => ($_POST['user'] ?? $row['username']),
                        'phone' => ($_POST['phone'] ?? $row['phone']),
                        'status' => ($_POST['status'] ?? $row['status']),
                        'role' => ($_POST['role'] ?? $row['role']),
                        'hours' => ($_POST['hours'] ?? $row['hours']),
                        'wage' => ($_POST['wage'] ?? $row['wage']),
                        'clientId' => ($_POST['client'] ?? $row['clientId']),
                        'clientIds' => [],
                        'source' => $row['source'],
                        'pwReset' => $row['pwReset'],
                        'jobTitle' => ($_POST['jobTitle'] ?? $row['jobTitle']),
                        'jobLevel' => ($_POST['jobLevel'] ?? $row['jobLevel']),
                        'department' => ($_POST['department'] ?? $row['department']),
                    ];

                    $postedClientIds = isset($_POST['clients']) && is_array($_POST['clients']) ? $_POST['clients'] : [];
                    if (count($postedClientIds) === 0 && isset($_POST['client'])) {
                        $postedClientIds = [$_POST['client']];
                    }

                    $normalizedClientIds = [];
                    foreach ($postedClientIds as $clientId) {
                        $parsedClientId = (int) $clientId;
                        if ($parsedClientId > 0) {
                            $normalizedClientIds[] = $parsedClientId;
                        }
                    }
                    $normalizedClientIds = array_values(array_unique($normalizedClientIds));

                    $values['clientIds'] = $normalizedClientIds;
                    $values['clientId'] = count($normalizedClientIds) > 0 ? $normalizedClientIds[0] : 0;

                    $changedEmail = 0;

                    if ($row['username'] != $values['user']) {
                        $changedEmail = 1;
                    }

                    if ($values['user'] !== '') {
                        if (! isset($_POST['password']) || ($_POST['password'] == $_POST['password2'])) {
                            if (filter_var($values['user'], FILTER_VALIDATE_EMAIL)) {
                                if ($changedEmail == 1) {
                                    if ($this->userRepo->usernameExist($row['username'], $id) === false) {
                                        $edit = true;
                                    } else {
                                        $this->tpl->setNotification($this->language->__('notification.user_exists'), 'error');
                                    }
                                } else {
                                    $edit = true;
                                }
                            } else {
                                $this->tpl->setNotification($this->language->__('notification.no_valid_email'), 'error');
                            }
                        } else {
                            $this->tpl->setNotification($this->language->__('notification.enter_email'), 'error');
                        }
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.passwords_dont_match'), 'error');
                    }
                } else {
                    $this->tpl->setNotification($this->language->__('notification.form_token_incorrect'), 'error');
                }
            }

            // Was everything okay?
            if ($edit !== false) {
                $this->userRepo->editUser($values, $id);
                $nonVisualEnabled = isset($_POST['nonVisualDesktop']) ? '1' : '0';
                $this->settingsService->saveSetting('usersettings.'.$id.'.nonVisualDesktop', $nonVisualEnabled);

                if (isset($_POST['projects'])) {
                    if ($_POST['projects'][0] !== '0') {
                        $this->projectsRepo->editUserProjectRelations($id, $_POST['projects']);
                    } else {
                        $this->projectsRepo->deleteAllProjectRelations($id);
                    }
                } else {
                    // If projects is not set, all project assignments have been removed.
                    $this->projectsRepo->deleteAllProjectRelations($id);
                }
                $this->tpl->setNotification($this->language->__('notifications.user_edited'), 'success');
            }

            // Get relations to projects
            $projects = $this->projectsRepo->getUserProjectRelation($id);

            $projectrelation = [];

            foreach ($projects as $projectId) {
                $projectrelation[] = $projectId['projectId'];
            }

            // Assign vars
            $this->tpl->assign('allProjects', $this->projectsRepo->getAll(true));
            $this->tpl->assign('roles', Roles::getRoles());
            $this->tpl->assign('clients', $this->clientsRepo->getAll());

            // Sensitive Form, generate form tokens
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            session(['formTokenName' => substr(str_shuffle($permitted_chars), 0, 32)]);
            session(['formTokenValue' => substr(str_shuffle($permitted_chars), 0, 32)]);

            $this->tpl->assign('values', $values);
            $this->tpl->assign('relations', $projectrelation);

            $this->tpl->assign('status', $this->userRepo->status);
            $this->tpl->assign('id', $id);

            return $this->tpl->display('users.editUser');
        } else {
            return $this->tpl->display('errors.error403');
        }
    }
}
