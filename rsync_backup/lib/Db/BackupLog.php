<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class BackupLog
 * @method int getId()
 * @method void setId(int $value)
 * @method int getUpdated()
 * @method void setUpdated(int $value)
 * @method int getPid()
 * @method void setPid(int $value)
 * @method int getStartTime()
 * @method void setStartTime(int $value)
 * @method ?int getEndTime()
 * @method void setEndTime(?int $value)
 * @method string getStatus()
 * @method void setStatus(string $value)
 * @method int getSuccesses()
 * @method int getWarnings()
 * @method int getErrors()
 * @package OCA\RsyncBackup\Db
 */
class BackupLog extends Entity {
    
    protected $pid;
    protected $updated;
    protected $startTime;
    protected $endTime;
    protected $status;
    protected $successes;
    protected $warnings;
    protected $errors;
    
}
