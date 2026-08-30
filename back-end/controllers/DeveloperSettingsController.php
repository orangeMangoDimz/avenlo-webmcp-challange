<?php

require_once __DIR__ . '/../services/DeveloperSettingsService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class DeveloperSettingsController {
    private $developerSettings;

    public function __construct() {
        $this->developerSettings = new DeveloperSettingsService();
    }

    public function index() {
        AuthMiddleware::authenticate();
        AuthMiddleware::requireAdmin();
        if (!$this->developerSettings->isNonProduction()) {
            Response::notFound();
        }
        Response::success($this->developerSettings->getAll());
    }

    public function update() {
        AuthMiddleware::authenticate();
        AuthMiddleware::requireAdmin();
        if (!$this->developerSettings->isNonProduction()) {
            Response::notFound();
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error('Invalid data format', 400);
        }

        $flags = [];
        foreach (['mt4Sync', 'mt5Sync', 'emailSending'] as $key) {
            if (array_key_exists($key, $data)) {
                $flags[$key] = $data[$key];
            }
        }

        if (empty($flags)) {
            Response::error('At least one of mt4Sync, mt5Sync, emailSending is required', 400);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $updatedBy = isset($currentUser['userId']) ? (int)$currentUser['userId'] : null;
        if ($updatedBy !== null && $updatedBy <= 0) {
            $updatedBy = null;
        }

        Response::success($this->developerSettings->updateFlags($flags, $updatedBy));
    }
}
