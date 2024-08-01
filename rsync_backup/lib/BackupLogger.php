<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup;

use DateTime;
use DateTimeZone;
use OCA\RsyncBackup\Db\BackupLog;
use OCA\RsyncBackup\Service\BackupService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BackupLogger {
    
    private DateTimeZone $dateTimeZone;
    private array $messages;
    private array $readers = [];
    
    public function __construct(string $dateTimeZone) {
        $this->dateTimeZone = new DateTimeZone($dateTimeZone);
        $this->messages = [];
    }
    
    private function log(string $type, string $message): void {
        $messageObject = (object) ['time' => intval(microtime(true) * 1000000), 'type' => $type, 'message' => $message];
        array_push($this->messages, $messageObject);
        foreach ($this->readers as $reader) {
            $reader($messageObject);
        }
    }
    
    public function logInfo(string $message): void {
        $this->log('info', $message);
    }
    
    public function logSuccess(string $message): void {
        $this->log('success', $message);
    }
    
    public function logWarning(string $message): void {
        $this->log('warning', $message);
    }
    
    public function logError(string $message): void {
        $this->log('error', $message);
    }
    
    public function hasWarnings(): bool {
        return count(array_filter($this->messages, function($message) {
            return $message->type === 'warning';
        })) > 0;
    }
    
    public function hasErrors(): bool {
        return count(array_filter($this->messages, function($message) {
            return $message->type === 'error';
        })) > 0;
    }
    
    public function bindOutputInterface(OutputInterface $output) {
        array_push($this->readers, function(object $message) use($output) {
            $dateTime = DateTime::createFromFormat('U.u', number_format($message->time / 1000000, 6, '.', ''), new DateTimeZone('UTC'));
            $dateTime->setTimezone($this->dateTimeZone);
            $formattedDateTime = $dateTime->format('Y-m-d H:i:s T');
            switch ($message->type) {
                case 'info': $output->writeln("$formattedDateTime: $message->message"); break;
                case 'success': $output->writeln("<fg=#00ff00>$formattedDateTime: $message->message</>"); break;
                case 'warning': $output->writeln("<fg=#ff8000>$formattedDateTime: $message->message</>"); break;
                case 'error': $output->writeln("<fg=#ff0000>$formattedDateTime: $message->message</>"); break;
            }
        });
    }
    
    public function bindLoggerInterface(LoggerInterface $loggerInterface) {
        array_push($this->readers, function(object $message) use($loggerInterface) {
            $dateTime = DateTime::createFromFormat('U.u', number_format($message->time / 1000000, 6, '.', ''), new DateTimeZone('UTC'));
            $dateTime->setTimezone($this->dateTimeZone);
            $formattedDateTime = $dateTime->format('Y-m-d H:i:s T');
            switch ($message->type) {
                case 'info': $loggerInterface->info("INFO $formattedDateTime: $message->message"); break;
                case 'success': $loggerInterface->info("SUCCESS $formattedDateTime: $message->message"); break;
                case 'warning': $loggerInterface->warning("WARNING $formattedDateTime: $message->message"); break;
                case 'error': $loggerInterface->error("ERROR $formattedDateTime: $message->message"); break;
            }
        });
    }
    
    public function bindBackupLog(BackupLog $log, BackupService $backupService) {
        array_push($this->readers, function(object $message) use($log, $backupService) {
            $backupService->createBackupLogMessage($log, $message->time, $message->type, $message->message);
        });
    }
    
    public function getFullLogAsPlain(): string {
        return implode('', array_map(function($message) {
            $dateTime = DateTime::createFromFormat('U.u', number_format($message->time / 1000000, 6, '.', ''), new DateTimeZone('UTC'));
            $dateTime->setTimezone($this->dateTimeZone);
            $formattedDateTime = $dateTime->format('Y-m-d H:i:s T');
            return strtoupper($message->type) . ' ' . $formattedDateTime . ': ' . $message->message . PHP_EOL;
        }, $this->messages));
    }
    
    public function getFullLogAsHtml(): string {
        $fullLog = implode('', array_map(function($message) {
            $dateTime = DateTime::createFromFormat('U.u', number_format($message->time / 1000000, 6, '.', ''), new DateTimeZone('UTC'));
            $dateTime->setTimezone($this->dateTimeZone);
            $formattedDateTime = $dateTime->format('Y-m-d H:i:s T');
            return '<span class="' . $message->type . '">' . $formattedDateTime . ': ' . $message->message . PHP_EOL . '</span>';
        }, $this->messages));
        return <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="UTF-8" />
                    <title>Backup log</title>
                    <style type="text/css">
                        span {
                            font-family: monospace;
                            margin: 0;
                            padding: 0;
                            white-space: break-spaces;
                        }
                        .success {
                            color: #00ff00;
                        }
                        .warning {
                            color: #ff8000;
                        }
                        .error {
                            color: #ff0000;
                        }
                    </style>
                </head>
                <body>
                    $fullLog
                </body>
            <html>
        HTML;
    }
    
}