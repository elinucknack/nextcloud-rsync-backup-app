<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup;

use OCA\RsyncBackup\AppInfo\Application;

style(Application::APP_ID, 'main');
if (($_['style'] ?? null) !== null) {
    style(Application::APP_ID, $_['style']);
}
script(Application::APP_ID, 'rsyncBackup');
script(Application::APP_ID, 'main');
if (($_['script'] ?? null) !== null) {
    script(Application::APP_ID, $_['script']);
}

?>

<input type="hidden" id="app-params-rsync" value="<?php echo htmlspecialchars(json_encode($_['params'] ?? [])); ?>" />
<div id="content"></div>
