<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BackupLogMessage>
 */
class BackupLogMessageMapper extends QBMapper {
    
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'rsync_backup_log_message', BackupLogMessage::class);
    }
    
    public function getAll(BackupLog $log): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)->where(
            $qb->expr()->eq('log_id', $qb->createNamedParameter($log->getId(), IQueryBuilder::PARAM_INT))
        )->orderBy('time', 'ASC');
        return $this->findEntities($qb);
    }
    
    public function deleteAll(BackupLog $log): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->tableName)->where(
            $qb->expr()->eq('log_id', $qb->createNamedParameter($log->getId(), IQueryBuilder::PARAM_INT))
        )->executeStatement();
    }
    
}
