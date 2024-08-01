<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\BackgroundJob;

use Exception;
use OCA\RsyncBackup\Service\BackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Class FindAbortedBackupLogsJob is a background job used to find aborted backup logs
 *
 * @package OCA\RsyncTools\BackgroundJob
 */
class FindAbortedBackupLogsJob extends TimedJob {
    
    const INTERVAL = 60 * 5;
    
    private BackupService $backupService;
    
    /**
     * @param BackupService $backupService
     * @param ITimeFactory $time
     */
    public function __construct(BackupService $backupService, ITimeFactory $time) {
        parent::__construct($time);
        
        $this->setInterval(self::INTERVAL);
        $this->setTimeSensitivity(self::TIME_SENSITIVE);
        
        $this->backupService = $backupService;
    }
    
    /**
     * @param array $argument unused argument
     * @throws Exception
     */
    protected function run($argument): void {
        $time = intval(microtime(true));
        
        $logs = $this->backupService->getLogs();
        foreach ($logs as $log) {
            $logMessages = $this->backupService->getLogMessages($log);
            $lastUpdate = count($logMessages) ? end($logMessages)->getTime() : $log->getStartTime();
            if (!$this->backupService->checkRunningBackupLog($log) && $log->getStatus() === 'running' && $time - $lastUpdate / 1000000 > 300) {
                $this->backupService->closeBackupLog($log, 'aborted');
            }
        }
    }
    
}
