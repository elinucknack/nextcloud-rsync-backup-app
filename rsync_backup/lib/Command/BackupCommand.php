<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Command;

use OCA\RsyncBackup\BackupLogger;
use OCA\RsyncBackup\Service\BackupService;
use OCP\IConfig;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BackupCommand extends Command {
    
    protected BackupService $backupService;
    protected IConfig $config;
    protected LoggerInterface $loggerInterface;
    protected IMailer $mailer;
    
    public function __construct(BackupService $backupService, IConfig $config, LoggerInterface $loggerInterface, IMailer $mailer) {
        parent::__construct();
        
        $this->backupService = $backupService;
        $this->config = $config;
        $this->loggerInterface = $loggerInterface;
        $this->mailer = $mailer;
    }
    
    protected function configure(): void {
        $this
            ->setName('rsync-backup:backup')
            ->setDescription('Backup Nextcloud')
            ->addOption(
                'dump-database',
                null,
                InputOption::VALUE_NONE,
                'Dump Nextcloud database'
            )->addOption(
                'backup-database',
                null,
                InputOption::VALUE_NONE,
                'Backup Nextcloud database'
            )->addOption(
                'backup-application',
                null,
                InputOption::VALUE_NONE,
                'Backup Nextcloud application directory'
            )->addOption(
                'backup-data',
                null,
                InputOption::VALUE_NONE,
                'Backup Nextcloud data directory'
            )->addOption(
                'delete-old-backups',
                null,
                InputOption::VALUE_REQUIRED,
                'Delete backups older than given time in seconds, eg. --delete-old-backups=2592000 for deletion of backups older than 30 days'
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $log = $this->backupService->createBackupLog();
        
        $backupLogger = new BackupLogger($this->config->getSystemValueString('logtimezone', date_default_timezone_get()));
        $backupLogger->bindOutputInterface($output);
        $backupLogger->bindLoggerInterface($this->loggerInterface);
        $backupLogger->bindBackupLog($log, $this->backupService);
        
        $dumpDatabase = $input->getOption('dump-database');
        $backupDatabase = $input->getOption('backup-database');
        $backupApplication = $input->getOption('backup-application');
        $backupData = $input->getOption('backup-data');
        $deleteOldBackups = intval($input->getOption('delete-old-backups') ?? '-1');
        $this->backupService->backup($backupLogger, null, $dumpDatabase, $backupDatabase, $backupApplication, $backupData, $deleteOldBackups);
        
        $this->backupService->closeBackupLog($log, 'completed');
        
        $backupNotificationRecipients = $this->config->getSystemValue('rsync_backup_notification_recipients', []);
        if (count($backupNotificationRecipients) > 0) {
            $this->backupService->sendBackupLog($backupLogger, $backupNotificationRecipients);
        }
        
        return $backupLogger->hasErrors() ? 1 : 0;
    }
    
}
