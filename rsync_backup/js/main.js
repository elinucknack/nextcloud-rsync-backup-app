$(() => {
    OC.RsyncBackup.loadParams();
    OC.RsyncBackup.createLayout('rsync_backup');
});

OC.RsyncBackup.onNavigationPresent(element => {
    OC.RsyncBackup.loadNavigation('rsync_backup', element);
});
