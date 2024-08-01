<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\BackgroundJob;

use Exception;
use OCA\RsyncBackup\Db\BackupLogMapper;
use OCA\RsyncBackup\Db\BackupLogMessageMapper;
use OCA\RsyncBackup\BackupLogger;
use OCA\RsyncBackup\Service\BackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/**
 * Class BackupJob is a background job used to backup the Nextcloud instance
 *
 * @package OCA\RsyncTools\BackgroundJob
 */
class BackupJob extends TimedJob {
    
    const INTERVAL = 60 * 60 * 24;
    
    private BackupService $backupService;
    private IConfig $config;
    private LoggerInterface $loggerInterface;
    private IMailer $mailer;
    private BackupLogMapper $logMapper;
    private BackupLogMessageMapper $logMessageMapper;
    
    /**
     * @param BackupService $backupService
     * @param IConfig $config
     * @param LoggerInterface $loggerInterface
     * @param IMailer $mailer
     * @param BackupLogMapper $logMapper
     * @param BackupLogMessageMapper $logMessageMapper
     * @param ITimeFactory $time
     */
    public function __construct(BackupService $backupService, IConfig $config, LoggerInterface $loggerInterface, IMailer $mailer, BackupLogMapper $logMapper, BackupLogMessageMapper $logMessageMapper, ITimeFactory $time) {
        parent::__construct($time);
        
        $this->setInterval(self::INTERVAL);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
        
        $this->backupService = $backupService;
        $this->config = $config;
        $this->loggerInterface = $loggerInterface;
        $this->mailer = $mailer;
        $this->logMapper = $logMapper;
        $this->logMessageMapper = $logMessageMapper;
    }
    
    /**
     * @param array $argument unused argument
     * @throws Exception
     */
    protected function run($argument): void {
        $disabled = $this->config->getSystemValueBool('rsync_backup_disabled');
        if ($disabled) {
            return;
        }
        
        $log = $this->backupService->createBackupLog();
        
        $backupLogger = new BackupLogger($this->config->getSystemValueString('logtimezone', date_default_timezone_get()));
        $backupLogger->bindLoggerInterface($this->loggerInterface);
        $backupLogger->bindBackupLog($log, $this->backupService);
        
        $deleteOldBackups = $this->config->getSystemValueInt('rsync_backup_delete_old_backups', -1);
        $this->backupService->backup($backupLogger, self::INTERVAL / 2, true, true, true, true, $deleteOldBackups);
        
        $this->backupService->closeBackupLog($log, 'completed');
        
        $backupNotificationRecipients = $this->config->getSystemValue('rsync_backup_notification_recipients', []);
        if (count($backupNotificationRecipients) > 0) {
            $this->backupService->sendBackupLog($backupLogger, $backupNotificationRecipients);
        }
    }
    
}
