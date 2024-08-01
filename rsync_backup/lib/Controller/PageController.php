<?php

declare(strict_types = 1);

namespace OCA\RsyncBackup\Controller;

use OCA\RsyncBackup\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;

class PageController extends Controller {
    
    protected IL10N $l;
    
    public function __construct(IL10N $l, IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
        $this->l = $l;
    }
    
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function backupLogs(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'main', [
            'style' => 'backupLogs',
            'script' => 'backupLogs'
        ]);
    }
    
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function backupLog(int $id): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'main', [
            'style' => 'backupLog',
            'script' => 'backupLog',
            'params' => [
                'id' => $id
            ]
        ]);
    }
    
    #[NoCSRFRequired]
    public function settings(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'main', [
            'style' => 'settings',
            'script' => 'settings'
        ]);
    }
    
}
