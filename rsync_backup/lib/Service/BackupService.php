<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Service;

use DateTime;
use DateTimeZone;
use OCA\RsyncBackup\AppInfo\Application;
use OCA\RsyncBackup\BackupLogger;
use OCA\RsyncBackup\Db\BackupLog;
use OCA\RsyncBackup\Db\BackupLogMapper;
use OCA\RsyncBackup\Db\BackupLogMessage;
use OCA\RsyncBackup\Db\BackupLogMessageMapper;
use OCP\IConfig;
use OCP\Mail\IMailer;

class BackupService {
    
    protected IConfig $config;
    protected IMailer $mailer;
    protected BackupLogMapper $logMapper;
    protected BackupLogMessageMapper $logMessageMapper;
    
    public function __construct(IConfig $config, IMailer $mailer, BackupLogMapper $logMapper, BackupLogMessageMapper $logMessageMapper) {
        $this->config = $config;
        $this->mailer = $mailer;
        $this->logMapper = $logMapper;
        $this->logMessageMapper = $logMessageMapper;
    }
    
    public function getSettings(): object {
        return (object) [
            'disabled' => $this->config->getSystemValueBool('rsync_backup_disabled'),
            'databaseDumpDirectory' => $this->config->getSystemValue('rsync_backup_database_dump_directory'),
            'databaseBackupDirectory' => $this->config->getSystemValue('rsync_backup_database_backup_directory'),
            'databaseBackupSshAuth' => $this->config->getSystemValue('rsync_backup_database_backup_ssh_auth'),
            'applicationBackupDirectory' => $this->config->getSystemValue('rsync_backup_application_backup_directory'),
            'applicationBackupSshAuth' => $this->config->getSystemValue('rsync_backup_application_backup_ssh_auth'),
            'dataBackupDirectory' => $this->config->getSystemValue('rsync_backup_data_backup_directory'),
            'dataBackupSshAuth' => $this->config->getSystemValue('rsync_backup_data_backup_ssh_auth'),
            'notificationRecipients' => $this->config->getSystemValue('rsync_backup_notification_recipients', []),
        ];
    }
    
    public function setSettings(object $data): void {
        $this->config->setSystemValue('rsync_backup_disabled', $data->disabled);
        $this->config->setSystemValue('rsync_backup_database_dump_directory', $data->databaseDumpDirectory);
        $this->config->setSystemValue('rsync_backup_database_backup_directory', $data->databaseBackupDirectory);
        $this->config->setSystemValue('rsync_backup_database_backup_ssh_auth', $data->databaseBackupSshAuth);
        $this->config->setSystemValue('rsync_backup_application_backup_directory', $data->applicationBackupDirectory);
        $this->config->setSystemValue('rsync_backup_application_backup_ssh_auth', $data->applicationBackupSshAuth);
        $this->config->setSystemValue('rsync_backup_data_backup_directory', $data->dataBackupDirectory);
        $this->config->setSystemValue('rsync_backup_data_backup_ssh_auth', $data->dataBackupSshAuth);
        $this->config->setSystemValue('rsync_backup_notification_recipients', $data->notificationRecipients);
    }
    
    public function createBackupLog(): BackupLog {
        $log = new BackupLog();
        $log->setUpdated(intval(microtime(true) * 1000000));
        $log->setStartTime(intval(microtime(true) * 1000000));
        $log->setPid(posix_getpid());
        $log->setStatus('running');
        $this->logMapper->insert($log);
        return $log;
    }
    
    public function closeBackupLog(BackupLog $log, string $status): void {
        $log->setUpdated(intval(microtime(true) * 1000000));
        $log->setEndTime(intval(microtime(true) * 1000000));
        $log->setStatus($status);
        $this->logMapper->update($log);
    }
    
    public function createBackupLogMessage(BackupLog $log, int $time, string $type, string $message): BackupLogMessage {
        $logMessage = new BackupLogMessage();
        $logMessage->setLogId($log->getId());
        $logMessage->setTime($time);
        $logMessage->setType($type);
        $logMessage->setMessage($message);
        $this->logMessageMapper->insert($logMessage);
        $log->setUpdated(intval(microtime(true) * 1000000));
        $this->logMapper->update($log);
        return $logMessage;
    }
    
    public function getLogs(?int $pageSize = null, int $page = 0): array {
        return $this->logMapper->getAll($pageSize, $page);
    }
    
    public function countLogs(): int {
        return $this->logMapper->countAll();
    }
    
    public function getLog(int $id): BackupLog {
        return $this->logMapper->getById($id);
    }
    
    public function getLogMessages(BackupLog $log): array {
        return $this->logMessageMapper->getAll($log);
    }
    
    public function checkRunningBackupLog(BackupLog $log): bool {
        $pid = $this->getPID();
		return $pid !== -1 && posix_getpgid($pid) !== false && $pid === $log->getPid();
    }
    
    public function backup(BackupLogger $backupLogger, ?int $maxDuration, bool $dumpDatabase, bool $backupDatabase, bool $backupApplication, bool $backupData, int $deleteOldBackups): void {
        $startTime = intval(microtime(true));
        
        $backupLogger->logInfo('Backup started.');
        
        if ($this->checkAlreadyRunning()) {
            $backupLogger->logWarning('Backup skipped. Already running.');
        } else if (!$dumpDatabase && !$backupDatabase && !$backupApplication && !$backupData) {
            $backupLogger->logWarning('Backup skipped. No backup operation selected.');
        } else {
            $this->setPID();
            
            if ($dumpDatabase) {
                $this->dumpDatabase($backupLogger);
            }
            if ($backupDatabase) {
                $this->backupDatabase($backupLogger, $startTime, $maxDuration, $deleteOldBackups);
            }
            if ($backupApplication) {
                $this->backupApplication($backupLogger, $startTime, $maxDuration, $deleteOldBackups);
            }
            if ($backupData) {
                $this->backupData($backupLogger, $startTime, $maxDuration, $deleteOldBackups);
            }
            
            $this->clearPID();
            
            if ($backupLogger->hasErrors()) {
                $backupLogger->logErrors('Backup failed.');
            } else if ($backupLogger->hasWarnings()) {
                $backupLogger->logWarning('Backup completed with warnings.');
            } else {
                $backupLogger->logSuccess('Backup completed.');
            }
        }
    }
    
    private function setPID(): void {
		$this->config->setAppValue(Application::APP_ID, 'pid', posix_getpid());
	}
    
	private function clearPID(): void {
		$this->config->deleteAppValue(Application::APP_ID, 'pid');
	}
    
	private function getPID(): int {
		return (int) $this->config->getAppValue(Application::APP_ID, 'pid', -1);
	}
    
    private function checkAlreadyRunning(): bool {
		$pid = $this->getPID();
		return $pid !== -1 && posix_getpgid($pid) !== false;
	}
    
    private function dumpDatabase(BackupLogger $backupLogger): void {
        $backupLogger->logInfo('Database dump started.');
        
        $dumpDirectory = $this->config->getSystemValueString('rsync_backup_database_dump_directory');
        
        if ($dumpDirectory === '') {
            $backupLogger->logWarning('Database dump skipped. Database dump directory not set');
            return;
        }
        
        $type = $this->config->getSystemValueString('dbtype');
        
        if ($type !== 'pgsql') {
            $backupLogger->logError("Database dump failed. Not defined for database type '$type'.");
            return;
        }
        
        $host = $this->config->getSystemValueString('dbhost');
        $port = $this->config->getSystemValueString('dbport');
        $user = $this->config->getSystemValueString('dbuser');
        $passwd = $this->config->getSystemValueString('dbpassword');
        $name = $this->config->getSystemValueString('dbname');
        
        $encodedHost = urlencode($host);
        $encodedPort = $port === '' ? '5432' : urlencode($port);
        $encodedUser = urlencode($user);
        $encodedPasswd = urlencode($passwd);
        $encodedName = urlencode($name);
        $escapedDumpDirectory = mb_ereg_replace("'", "'\\''", $dumpDirectory);
        
        $command = "pg_dump --dbname=postgresql://$encodedUser:$encodedPasswd@$encodedHost:$encodedPort/$encodedName -F t > '$escapedDumpDirectory/database-dump.tar'";
        $pipes = null;
        
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            $backupLogger->logError('Database dump failed. Process opening failed.');
            return;
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        $processErrorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $processResult = proc_close($process);
        
        if ($processResult !== 0) {
            $backupLogger->logInfo($processErrorOutput);
            $backupLogger->logError('Database dump failed. Command execution failed.');
            return;
        }
        $backupLogger->logSuccess('Database dump completed.');
    }
    
    private function backupDatabase(BackupLogger $backupLogger, int $startTime, ?int $maxDuration, int $deleteOldBackups): void {
        $backupLogger->logInfo('Database backup started.');
        
        $sourceDirectory = $this->config->getSystemValueString('rsync_backup_database_dump_directory');
        if ($sourceDirectory === '') {
            $backupLogger->logWarning('Database backup skipped. Database dump directory not set.');
            return;
        }
        
        $backupDirectory = $this->config->getSystemValueString('rsync_backup_database_backup_directory');
        if ($backupDirectory === '') {
            $backupLogger->logWarning('Database backup skipped. Database backup directory not set.');
            return;
        }
        
        $backupSshAuth = (object) $this->config->getSystemValue('rsync_backup_database_backup_ssh_auth', []);
        
        $this->backupDirectory($backupLogger, 'Database', $sourceDirectory, $backupDirectory, $backupSshAuth, $startTime, $maxDuration);
        
        if ($deleteOldBackups >= 0) {
            $backupLogger->logInfo('Database backup deletion started.');
            
            $this->deleteBackups($backupLogger, 'Database', $backupDirectory, $backupSshAuth, $startTime, $maxDuration, $deleteOldBackups);
        }
    }
    
    private function backupApplication(BackupLogger $backupLogger, int $startTime, ?int $maxDuration, int $deleteOldBackups): void {
        $backupLogger->logInfo('Application backup started.');
        
        $sourceDirectory = dirname(__DIR__, 4);
        
        $backupDirectory = $this->config->getSystemValueString('rsync_backup_application_backup_directory');
        if ($backupDirectory === '') {
            $backupLogger->logWarning('Application backup skipped. Application backup directory not set.');
            return;
        }
        
        $backupSshAuth = (object) $this->config->getSystemValue('rsync_backup_application_backup_ssh_auth', []);
        
        $this->backupDirectory($backupLogger, 'Application', $sourceDirectory, $backupDirectory, $backupSshAuth, $startTime, $maxDuration);
        
        if ($deleteOldBackups >= 0) {
            $backupLogger->logInfo('Application backup deletion started.');
            
            $this->deleteBackups($backupLogger, 'Application', $backupDirectory, $backupSshAuth, $startTime, $maxDuration, $deleteOldBackups);
        }
    }
    
    private function backupData(BackupLogger $backupLogger, int $startTime, ?int $maxDuration, int $deleteOldBackups): void {
        $backupLogger->logInfo('Data backup started.');
        
        $sourceDirectory = $this->config->getSystemValueString('datadirectory');
        
        $backupDirectory = $this->config->getSystemValueString('rsync_backup_data_backup_directory');
        if ($backupDirectory === '') {
            $backupLogger->logWarning('Data backup skipped. Data backup directory not set.');
            return;
        }
        
        $backupSshAuth = (object) $this->config->getSystemValue('rsync_backup_data_backup_ssh_auth', []);
        
        $this->backupDirectory($backupLogger, 'Data', $sourceDirectory, $backupDirectory, $backupSshAuth, $startTime, $maxDuration);
        
        if ($deleteOldBackups >= 0) {
            $backupLogger->logInfo('Data backup deletion started.');
            
            $this->deleteBackups($backupLogger, 'Data', $backupDirectory, $backupSshAuth, $startTime, $maxDuration, $deleteOldBackups);
        }
    }
    
    private function backupDirectory(BackupLogger $backupLogger, string $object, string $sourceDirectory, string $backupDirectory, object $backupSshAuth, int $startTime, ?int $maxDuration): void {
        $stopAfter = $maxDuration === null ? null : max(intval(ceil(($maxDuration - intval(microtime(true)) + $startTime) / 60)), 0);
        if ($stopAfter === 0) {
            $backupLogger->logWarning("$object backup skipped. Max duration exceeded.");
            return;
        }
        
        $parsedDirectory = $this->parseBackupDirectory($backupDirectory);
        $escapedSourceDirectory = mb_ereg_replace("'", "'\\''", $sourceDirectory);
        $escapedBackupDirectory = mb_ereg_replace("'", "'\\''", $backupDirectory);
        $escapedPassword = ($backupSshAuth->password ?? null) === null ? null : mb_ereg_replace("'", "'\\''", $backupSshAuth->password);
        $escapedKeyFile = ($backupSshAuth->keyFile ?? null) === null ? null : mb_ereg_replace("'", "'\\''", $backupSshAuth->keyFile);
        $startDateTime = DateTime::createFromFormat('U', strval($startTime), new DateTimeZone('UTC'));
        $startDateTime->setTimezone(new DateTimeZone('UTC'));
        $escapedBackup = mb_ereg_replace("'", "'\\''", '../' . basename($backupDirectory) . '-' . $startDateTime->format('YmdHis'));
        
        $sshpassCommand = '';
        $sshOptions = [];
        if ($parsedDirectory->host !== null) {
            array_push($sshOptions, '-o StrictHostKeyChecking=no');
            if ($escapedPassword === null) {
                array_push($sshOptions, '-o BatchMode=yes');
            } else {
                $sshpassCommand = "sshpass -p '$escapedPassword'";
            }
            if ($escapedKeyFile !== null) {
                array_push($sshOptions, "-i '$escapedKeyFile'");
            }
        }
        $sshOptionsCommand = count($sshOptions) === 0 ? '' : "-e 'ssh " . implode(' ', $sshOptions) . "'";
        $stopAfterCommand = $stopAfter === null ? '' : "--stop-after $stopAfter";
        $command = "$sshpassCommand rsync $sshOptionsCommand -a '$escapedSourceDirectory' '$escapedBackupDirectory' --delete --stats --backup --backup-dir '$escapedBackup' $stopAfterCommand";
        $pipes = null;
        
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            $backupLogger->logError("$object backup failed. Process opening failed.");
            return;
        }
        fclose($pipes[0]);
        $processOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $processErrorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $processResult = proc_close($process);

        if ($processResult !== 0) {
            $backupLogger->logInfo($processErrorOutput);
            $backupLogger->logError("$object backup failed. Command execution failed.");
            return;
        }
        $backupLogger->logInfo($processOutput);
        
        if ($maxDuration !== null && intval(microtime(true)) - $startTime > $maxDuration) {
            $backupLogger->logWarning("$object backup aborted. Max duration exceeded.");
        } else {
            $backupLogger->logSuccess("$object backup completed.");
        }
    }
    
    private function parseBackupDirectory(string $backupDirectory): object {
        $matches0 = null;
        $result0 = preg_match('/^rsync:\\/\\/((?:[^@:]+@)?[^@:]+(?::[0-9]+)?)(\\/.*)$/', $backupDirectory, $matches0);
        if ($result0 === 1) {
            return (object) ['host' => $matches0[1], 'path' => $matches0[2]];
        }
        
        $matches1 = null;
        $result1 = preg_match('/^((?:[^@:]+@)?[^@:]+):{1,2}(\\/.*)$/', $backupDirectory, $matches1);
        if ($result1 === 1) {
            return (object) ['host' => $matches1[1], 'path' => $matches1[2]];
        }
        
        return (object) ['host' => null, 'path' => $backupDirectory];
    }
    
    private function deleteBackups(BackupLogger $backupLogger, string $object, string $backupDirectory, object $backupSshAuth, int $startTime, ?int $maxDuration, int $deleteOldBackups): void {
        if ($maxDuration !== null && intval(microtime(true)) - $startTime > $maxDuration) {
            $backupLogger->logWarning("$object backup deletion skipped. Max duration exceeded.");
            return;
        }
        
        $parsedDirectory = $this->parseBackupDirectory($backupDirectory);
        $escapedHost = $parsedDirectory->host === null ? null : mb_ereg_replace("'", "'\\''", $parsedDirectory->host);
        $escapedPath = mb_ereg_replace("'", "'\\''", $parsedDirectory->path);
        $escapedPassword = ($backupSshAuth->password ?? null) === null ? null : mb_ereg_replace("'", "'\\''", $backupSshAuth->password);
        $escapedKeyFile = ($backupSshAuth->keyFile ?? null) === null ? null : mb_ereg_replace("'", "'\\''", $backupSshAuth->keyFile);
        
        $findCommand = "cd '$escapedPath/..'; ls";
        if ($escapedHost !== null) {
            $sshpassCommand = '';
            $sshOptions = ['-o StrictHostKeyChecking=no'];
            if ($escapedPassword === null) {
                array_push($sshOptions, '-o BatchMode=yes');
            } else {
                $sshpassCommand = "sshpass -p '$escapedPassword'";
            }
            if ($escapedKeyFile !== null) {
                array_push($sshOptions, "-i '$escapedKeyFile'");
            }
            $sshOptionsCommand = implode(' ', $sshOptions);
            $findCommandEscaped = mb_ereg_replace('"', '"\\""', $findCommand);
            $findCommand = "$sshpassCommand ssh $sshOptionsCommand '$escapedHost' \"$findCommandEscaped\"";
        }
        $findPipes = null;
        
        $findProcess = proc_open($findCommand, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $findPipes);
        if (!is_resource($findProcess)) {
            $backupLogger->logError("$object backup deletion failed. Backups finding process opening failed.");
            return;
        }
        fclose($findPipes[0]);
        $findProcessOutput = stream_get_contents($findPipes[1]);
        fclose($findPipes[1]);
        $findProcessErrorOutput = stream_get_contents($findPipes[2]);
        fclose($findPipes[2]);
        $findProcessResult = proc_close($findProcess);
        
        if ($findProcessResult !== 0) {
            $backupLogger->logInfo($findProcessErrorOutput);
            $backupLogger->logError("$object backup deletion failed. Backups finding command execution failed.");
            return;
        }
        
        $minBackupTime = $startTime - $deleteOldBackups;
        $backupsToDelete = $this->filterBackupsToDelete($backupDirectory, mb_split(PHP_EOL, $findProcessOutput), $minBackupTime);
        if (count($backupsToDelete) === 0) {
            $backupLogger->logInfo('No backups deleted');
            $minBackupDateTime = DateTime::createFromFormat('U', strval($minBackupTime), new DateTimeZone('UTC'));
            $minBackupDateTime->setTimezone(new DateTimeZone($this->config->getSystemValueString('logtimezone', date_default_timezone_get())));
            $backupLogger->logSuccess("$object backup deletion completed. Backups older than " . $minBackupDateTime->format('Y-m-d H:i:s T') . ' deleted.');
            return;
        }
        
        $deleteCommand = 'rm -rf ' . implode(' ', array_map(function(string $backup) use($escapedPath) {
            return "'$escapedPath/../$backup'";
        }, $backupsToDelete));
        if ($escapedHost !== null) {
            $sshpassCommand = '';
            $sshOptions = ['-o StrictHostKeyChecking=no'];
            if ($escapedPassword === null) {
                array_push($sshOptions, '-o BatchMode=yes');
            } else {
                $sshpassCommand = "sshpass -p '$escapedPassword'";
            }
            if ($escapedKeyFile !== null) {
                array_push($sshOptions, "-i '$escapedKeyFile'");
            }
            $sshOptionsCommand = implode(' ', $sshOptions);
            $deleteCommandEscaped = mb_ereg_replace('"', '"\\""', $deleteCommand);
            $deleteCommand = "$sshpassCommand ssh $sshOptionsCommand '$escapedHost' \"$deleteCommandEscaped\"";
        }
        $deletePipes = null;
        
        $deleteProcess = proc_open($deleteCommand, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $deletePipes);
        if (!is_resource($deleteProcess)) {
            $backupLogger->logError("$object backup deletion failed. Backups deletion process opening failed.");
            return;
        }
        fclose($deletePipes[0]);
        fclose($deletePipes[1]);
        $deleteProcessErrorOutput = stream_get_contents($deletePipes[2]);
        fclose($deletePipes[2]);
        $deleteProcessResult = proc_close($deleteProcess);
        
        if ($deleteProcessResult !== 0) {
            $backupLogger->logInfo($deleteProcessErrorOutput);
            $backupLogger->logError("$object backup deletion failed. Backups deletion command execution failed.");
            return;
        }
        $backupLogger->logInfo(PHP_EOL . 'Deleted backups: ' . implode(', ', $backupsToDelete) . PHP_EOL);
        $minBackupDateTime = DateTime::createFromFormat('U', strval($minBackupTime), new DateTimeZone('UTC'));
        $minBackupDateTime->setTimezone(new DateTimeZone($this->config->getSystemValueString('logtimezone', date_default_timezone_get())));
        $backupLogger->logSuccess("$object backup deletion completed. Backups older than " . $minBackupDateTime->format('Y-m-d H:i:s T') . ' deleted.');
    }
    
    private function filterBackupsToDelete(string $backupDirectory, array $backups, int $minBackupTime): array {
        $escapedBackupDirectory = preg_quote(basename($backupDirectory), '/');
        $backupsToDelete = [];
        foreach ($backups as $backup) {
            $matches = null;
            $result = preg_match("/^$escapedBackupDirectory-([0-9]{14})$/", $backup, $matches);
            if ($result === 1) {
                $backupDateTime = DateTime::createFromFormat('YmdHis', $matches[1], new DateTimeZone('UTC'));
                $backupDateTime->setTimezone(new DateTimeZone('UTC'));
                if ($backupDateTime->getTimestamp() < $minBackupTime) {
                    array_push($backupsToDelete, $backup);
                }
            }
        }
        return $backupsToDelete;
    }
    
    public function sendBackupLog(BackupLogger $backupLogger, array $backupNotificationRecipients): void {
        $message = $this->mailer->createMessage();
        $subject = 'Backup notification';
        if ($backupLogger->hasErrors()) {
            $subject = '[ERROR] ' . $subject;
        } else if ($backupLogger->hasWarnings()) {
            $subject = '[WARNING] ' . $subject;
        } else {
            $subject = '[SUCCESS] ' . $subject;
        }
        $message->setSubject($subject);
        $message->setPlainBody($backupLogger->getFullLogAsPlain());
        $message->setHtmlBody($backupLogger->getFullLogAsHtml());
        $message->setTo($backupNotificationRecipients);
        $this->mailer->send($message);
    }
    
    public function deleteBackupLog(BackupLog $log): void {
        $this->logMessageMapper->deleteAll($log);
        $this->logMapper->delete($log);
    }
    
}
