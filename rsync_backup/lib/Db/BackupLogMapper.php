<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Db;

use Exception;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * @extends QBMapper<BackupLog>
 */
class BackupLogMapper extends QBMapper {
    
    public function __construct(IDBConnection $db, IL10N $l) {
        parent::__construct($db, 'rsync_backup_log', BackupLog::class);
        $this->l = $l;
    }
    
    public function getAll(?int $pageSize = null, int $page = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'l.id', 'l.updated', 'l.start_time', 'l.end_time', 'l.status',
            $qb->createFunction("SUM(CASE m.type WHEN 'success' THEN 1 ELSE 0 END) AS successes"),
            $qb->createFunction("SUM(CASE m.type WHEN 'warning' THEN 1 ELSE 0 END) AS warnings"),
            $qb->createFunction("SUM(CASE m.type WHEN 'error' THEN 1 ELSE 0 END) AS errors")
        )->from($this->tableName, 'l')
            ->leftJoin('l', 'rsync_backup_log_message', 'm', 'l.id = m.log_id')
            ->orderBy('l.start_time', 'DESC')
            ->groupBy('l.id', 'l.updated', 'l.start_time', 'l.end_time', 'l.status');
        if ($pageSize !== null) {
            $qb->setMaxResults($pageSize)->setFirstResult($page * $pageSize);
        }
        return $this->findEntities($qb);
    }
    
    public function countAll(): int {
        $qb = $this->db->getQueryBuilder();
        $qb->selectAlias($qb->createFunction('COUNT(*)'), 'count')->from($this->tableName);
        $cursor = $qb->execute();
        $row = $cursor->fetch();
        $cursor->closeCursor();
        return $row['count'];
    }
    
    public function getById(int $id): BackupLog {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select(
                'l.id', 'l.updated', 'l.start_time', 'l.end_time', 'l.status',
                $qb->createFunction("SUM(CASE m.type WHEN 'success' THEN 1 ELSE 0 END) AS successes"),
                $qb->createFunction("SUM(CASE m.type WHEN 'warning' THEN 1 ELSE 0 END) AS warnings"),
                $qb->createFunction("SUM(CASE m.type WHEN 'error' THEN 1 ELSE 0 END) AS errors")
            )->from($this->tableName, 'l')
                ->leftJoin('l', 'rsync_backup_log_message', 'm', 'l.id = m.log_id')
                ->where(
                    $qb->expr()->eq('l.id', $qb->createNamedParameter($id), IQueryBuilder::PARAM_INT)
                )
                ->orderBy('l.start_time', 'DESC')
                ->groupBy('l.id', 'l.updated', 'l.start_time', 'l.end_time', 'l.status');
            return $this->findEntity($qb);
        } catch (Exception $e) {
            throw new Exception($this->l->t('Backup log not found.'));
        }
    }
    
}
