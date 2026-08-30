<?php
/**
 * 客户邮件模板控制器
 */

require_once __DIR__ . '/../models/ClientEmailTemplate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ClientEmailTemplateController {
    private $templateModel;

    public function __construct() {
        $this->templateModel = new ClientEmailTemplate();
    }

    /**
     * 获取启用状态的邮件模板列表
     * GET /api/client-email-templates
     */
    public function index() {
        $templates = $this->templateModel->getActiveTemplates();
        Response::success($templates);
    }
}
