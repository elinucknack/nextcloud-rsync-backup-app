<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Controller;

use Exception;
use OCA\RsyncBackup\AppInfo\Application;
use OCA\RsyncBackup\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class BackupController extends Controller {
    
    protected BackupService $backupService;
    protected IConfig $config;
    protected IGroupManager $groupManager;
    protected IL10N $l;
    protected IURLGenerator $urlGenerator;
    protected IUserSession $userSession;
    
    public function __construct(BackupService $backupService, IConfig $config, IGroupManager $groupManager, IL10N $l, IRequest $request, IURLGenerator $urlGenerator, IUserSession $userSession) {
        parent::__construct(Application::APP_ID, $request);
        $this->backupService = $backupService;
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
    }
    
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getNavigation(): JSONResponse {
        try {
            $items = [];
            array_push($items, (object) [
                'icon' => 'icon-history',
                'label' => 'Backup logs',
                'url' => $this->urlGenerator->linkToRoute('rsync_backup.page.backupLogs')
            ]);
            if ($this->groupManager->isAdmin($this->userSession->getUser()->getUID())) {
                array_push($items, (object) [
                    'icon' => 'icon-settings',
                    'label' => 'Settings',
                    'url' => $this->urlGenerator->linkToRoute('rsync_backup.page.settings')
                ]);
            }
            return new JSONResponse((object) ['type' => 'success', 'data' => $items]);
        } catch (Exception $e) {
            return new JSONResponse((object) ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getBackupLogs(int $pageSize, int $page): JSONResponse {
        try {
            $logs = $this->backupService->getLogs($pageSize, $page);
            return new JSONResponse((object) ['type' => 'success', 'data' => (object) [
                'count' => $this->backupService->countLogs(),
                'table' => array_map(function($log) {
                    return (object) [
                        'id' => $log->getId(),
                        'updated' => $log->getUpdated(),
                        'startTime' => $log->getStartTime(),
                        'endTime' => $log->getEndTime(),
                        'status' => $log->getStatus(),
                        'successes' => $log->getSuccesses(),
                        'warnings' => $log->getWarnings(),
                        'errors' => $log->getErrors()
                    ];
                }, $logs)
            ]]);
        } catch (Exception $e) {
            return new JSONResponse((object) ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getBackupLog(int $id): JSONResponse {
        try {
            $log = $this->backupService->getLog($id);
            $messages = $this->backupService->getLogMessages($log);
            return new JSONResponse((object) ['type' => 'success', 'data' => (object) [
                'id' => $log->getId(),
                'updated' => $log->getUpdated(),
                'startTime' => $log->getStartTime(),
                'endTime' => $log->getEndTime(),
                'status' => $log->getStatus(),
                'successes' => $log->getSuccesses(),
                'warnings' => $log->getWarnings(),
                'errors' => $log->getErrors(),
                'messages' => array_map(function($message) {
                    return (object) [
                        'id' => $message->getId(),
                        'time' => $message->getTime(),
                        'type' => $message->getType(),
                        'message' => $message->getMessage(),
                    ];
                }, $messages)
            ]]);
        } catch (Exception $e) {
            return new JSONResponse((object) ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    #[NoCSRFRequired]
    public function getSettings(): JSONResponse {
        try {
            return new JSONResponse((object) ['type' => 'success', 'data' => $this->backupService->getSettings()]);
        } catch (Exception $e) {
            return new JSONResponse((object) ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    #[NoCSRFRequired]
    public function setSettings(array $settings): JSONResponse {
        try {
            if (!is_bool($settings['disabled'])) {
                throw new Exception($this->l->t('Invalid type of Disabled, boolean expected.'));
            }
            if (!is_string($settings['databaseDumpDirectory'])) {
                throw new Exception($this->l->t('Invalid type of Database dump directory, string expected.'));
            }
            if (!is_string($settings['databaseBackupDirectory'])) {
                throw new Exception($this->l->t('Invalid type of Database backup directory, string expected.'));
            }
            if (!is_array($settings['databaseBackupSshAuth'])) {
                throw new Exception($this->l->t('Invalid type of Database backup SSH authentication, array expected.'));
            }
            if (count($settings['databaseBackupSshAuth']) > 1) {
                throw new Exception($this->l->t('Invalid content of Database backup SSH authentication, max one item expected.'));
            }
            if (count($settings['databaseBackupSshAuth']) === 1 && !in_array(array_keys($settings['databaseBackupSshAuth'])[0], ['password', 'keyFile'])) {
                throw new Exception($this->l->t('Unknown content of Database backup SSH authentication, Password or Key file expected.'));
            }
            if (!is_string($settings['databaseBackupSshAuth'][array_keys($settings['databaseBackupSshAuth'])[0]])) {
                throw new Exception($this->l->t('Invalid type of Database backup SSH authentication data, string expected.'));
            }
            if (!is_string($settings['applicationBackupDirectory'])) {
                throw new Exception($this->l->t('Invalid type of Application backup directory, string expected.'));
            }
            if (!is_array($settings['applicationBackupSshAuth'])) {
                throw new Exception($this->l->t('Invalid type of Application backup SSH authentication, array expected.'));
            }
            if (count($settings['applicationBackupSshAuth']) > 1) {
                throw new Exception($this->l->t('Invalid content of Application backup SSH authentication, max one item expected.'));
            }
            if (count($settings['applicationBackupSshAuth']) === 1 && !in_array(array_keys($settings['applicationBackupSshAuth'])[0], ['password', 'keyFile'])) {
                throw new Exception($this->l->t('Unknown content of Application backup SSH authentication, Password or Key file expected.'));
            }
            if (!is_string($settings['applicationBackupSshAuth'][array_keys($settings['applicationBackupSshAuth'])[0]])) {
                throw new Exception($this->l->t('Invalid type of Application backup SSH authentication data, string expected.'));
            }
            if (!is_string($settings['dataBackupDirectory'])) {
                throw new Exception($this->l->t('Invalid type of Data backup directory, string expected.'));
            }
            if (!is_array($settings['dataBackupSshAuth'])) {
                throw new Exception($this->l->t('Invalid type of Data backup SSH authentication, array expected.'));
            }
            if (count($settings['dataBackupSshAuth']) > 1) {
                throw new Exception($this->l->t('Invalid content of Data backup SSH authentication, max one item expected.'));
            }
            if (count($settings['dataBackupSshAuth']) === 1 && !in_array(array_keys($settings['dataBackupSshAuth'])[0], ['password', 'keyFile'])) {
                throw new Exception($this->l->t('Unknown content of Data backup SSH authentication, Password or Key file expected.'));
            }
            if (!is_string($settings['dataBackupSshAuth'][array_keys($settings['dataBackupSshAuth'])[0]])) {
                throw new Exception($this->l->t('Invalid type of Data backup SSH authentication data, string expected.'));
            }
            if (!is_array($settings['notificationRecipients'])) {
                throw new Exception($this->l->t('Invalid type of Notification recipients, array of e-mails expected.'));
            }
            foreach($settings['notificationRecipients'] as $recipient) {
                if (!is_string($recipient) || preg_match('/^[^@]+@[^@]+$/', $recipient) !== 1) {
                    throw new Exception($this->l->t('Invalid type of Notification recipients, array of e-mails expected.'));
                }
            }
            
            $this->backupService->setSettings((object) $settings);
            return new JSONResponse((object) ['type' => 'success', 'message' => $this->l->t('Backup settings saved.')]);
        } catch (Exception $e) {
            return new JSONResponse((object) ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
}
