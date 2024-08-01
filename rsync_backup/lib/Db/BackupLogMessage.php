<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class BackupLogMessage
 * @method int getId()
 * @method void setId(int $value)
 * @method string getLogId()
 * @method void setLogId(int $value)
 * @method int getTime()
 * @method void setTime(int $value)
 * @method string getType()
 * @method void setType(string $value)
 * @method string getMessage()
 * @method void setMessage(string $value)
 * @package OCA\RsyncBackup\Db
 */
class BackupLogMessage extends Entity {
    
    protected $logId;
    protected $time;
    protected $type;
    protected $message;
    
}
