OC.RsyncBackup.onContentPresent(element => {
    
    let form = OC.RsyncBackup.createForm({
        id: 'backupLog',
        appName: 'rsync_backup',
        dataSource: `/apps/rsync_backup/action/get-backup-log/${OC.RsyncBackup.params.id}`,
        mapSource: data => {
            let updated = new Date(data.updated / 1000);
            let startTime = new Date(data.startTime / 1000);
            let endTime = data.endTime === null ? null : new Date(data.endTime / 1000);
            let status = {
                running: 'Running',
                completed: 'Completed',
                aborted: 'Aborted'
            }[data.status];
            let statusClass = (data.errors ? 'error' : (data.warnings || data.status !== 'completed' ? 'warning' : (data.successes ? 'success' : '')));
            data.backupLogTable = {
                updated: updated.toLocaleString(),
                startTime: startTime.toLocaleString(),
                endTime: endTime === null ? '' : endTime.toLocaleString(),
                status: t('rsync_backup', status),
                statusClass
            };
            data.backupLogMessages = data.messages;
            return data;
        },
        render: () => {
            $(element).empty();
            
            form.createHeader('backupLogHeader', 2, { text: 'Backup log {startTime}', closeUrl: '/apps/rsync_backup/backup-logs', params: model => ({ startTime: model.backupLogTable.startTime }) }).appendTo(element);
            
            let backupLogSection = form.createSection('backup-log').appendTo(element);
            
            form.createNotificationDiv('backupLogNotifications').appendTo(backupLogSection);
            form.createAttrTable('backupLogTable', {
                rows: [
                    { label: 'Updated', attr: 'updated' },
                    { label: 'Start time', attr: 'startTime' },
                    { label: 'End time', attr: 'endTime' },
                    { label: 'Status', attr: 'status', class: 'statusClass' }
                ]
            }).appendTo(backupLogSection);
            form.createHeader('backupLogMessagesHeader', 3, { text: 'Messages' }).appendTo(backupLogSection);
            form.createMessageLog('backupLogMessages', {
                mapMessage: message => {
                    let time = new Date(message.time / 1000);
                    return time.toLocaleString() + ': ' + message.message + '\n';
                },
                mapMessageClass: message => message.type
            }).appendTo(backupLogSection);
        },
        refreshInterval: 5000
    });
    
    form.init();
    
});
