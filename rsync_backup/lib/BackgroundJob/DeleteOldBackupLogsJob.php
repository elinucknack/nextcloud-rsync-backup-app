<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\BackgroundJob;

use Exception;
use OCA\RsyncBackup\Service\BackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;

/**
 * Class DeleteOldBackupLogsJob is a background job used to delete old backup logs
 *
 * @package OCA\RsyncTools\BackgroundJob
 */
class DeleteOldBackupLogsJob extends TimedJob {
    
    const INTERVAL = 60 * 60 * 24;
    
    private BackupService $backupService;
    private IConfig $config;
    
    /**
     * @param BackupService $backupService
     * @param IConfig $config
     * @param ITimeFactory $time
     */
    public function __construct(BackupService $backupService, IConfig $config, ITimeFactory $time) {
        parent::__construct($time);
        
        $this->setInterval(self::INTERVAL);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
        
        $this->backupService = $backupService;
        $this->config = $config;
    }
    
    /**
     * @param array $argument unused argument
     * @throws Exception
     */
    protected function run($argument): void {
        $time = intval(microtime(true));
        
        $deleteOldBackupLogs = $this->config->getSystemValueInt('rsync_backup_delete_old_backup_logs', -1);
        
        if ($deleteOldBackupLogs >= 0) {
            $logs = $this->backupService->getLogs();
            foreach ($logs as $log) {
                $logMessages = $this->backupService->getLogMessages($log);
                $lastUpdate = $log->getEndTime() ?? (count($logMessages) ? end($logMessages)->getTime() : $log->getStartTime());
                if ($log->getStatus() !== 'running' && $time - $lastUpdate / 1000000 > $deleteOldBackupLogs) {
                    $this->backupService->deleteBackupLog($log);
                }
            }
        }
    }
    
}
