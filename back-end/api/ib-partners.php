<?php
/**
 * IB Partners API 路由
 * IB合作伙伴管理接口
 */

require_once __DIR__ . '/../controllers/IbPartnerController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibPartnerController = new IbPartnerController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/ib-partners
        if ($method === 'GET') {
            $ibPartnerController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        // /api/ib-partners/statistics
        if ($method === 'GET') {
            $ibPartnerController->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'initial-review') {
        // /api/ib-partners/initial-review 初审列表（仅 Pending Initial Review）
        if ($method === 'GET') {
            $ibPartnerController->initialReviewList();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'risk-review') {
        // /api/ib-partners/risk-review 风险审核列表（仅 Pending Risk Review）
        if ($method === 'GET') {
            $ibPartnerController->riskReviewList();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'final-review') {
        // /api/ib-partners/final-review 终审列表（仅 Pending Final Review）
        if ($method === 'GET') {
            $ibPartnerController->finalReviewList();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'all-list') {
        // /api/ib-partners/all-list 全部状态 IB 列表（Client 分组下 IB List 页）
        if ($method === 'GET') {
            $ibPartnerController->allList();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/ib-partners/export
        if ($method === 'POST') {
            $ibPartnerController->export();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/ib-partners/{id}
        if ($method === 'GET') {
            $ibPartnerController->show($id);
        } elseif ($method === 'PUT') {
            $ibPartnerController->update($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/ib-partners/{id}/{action}
        switch ($action) {
            case 'clients':
                // /api/ib-partners/{id}/clients
                if ($method === 'GET') {
                    $ibPartnerController->getClients($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'commissions':
                // /api/ib-partners/{id}/commissions
                if ($method === 'GET') {
                    $ibPartnerController->getCommissions($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'network':
                // /api/ib-partners/{id}/network
                if ($method === 'GET') {
                    $ibPartnerController->getNetwork($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
            case 'network-stats':
                // /api/ib-partners/{id}/network-stats
                if ($method === 'GET') {
                    $ibPartnerController->getNetworkStats($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'activities':
                // /api/ib-partners/{id}/activities
                if ($method === 'GET') {
                    $ibPartnerController->getActivities($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'submit-initial-review':
                // /api/ib-partners/{id}/submit-initial-review
                if ($method === 'POST') {
                    $ibPartnerController->submitInitialReview($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'reject':
                // /api/ib-partners/{id}/reject
                if ($method === 'POST') {
                    $ibPartnerController->reject($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'approve':
                // /api/ib-partners/{id}/approve 终审通过
                if ($method === 'POST') {
                    $ibPartnerController->approve($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'bindables':
                // /api/ib-partners/{id}/bindables 可绑定列表
                if ($method === 'GET') {
                    $ibPartnerController->getBindables($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'bind':
                // /api/ib-partners/{id}/bind 绑定子级
                if ($method === 'POST') {
                    $ibPartnerController->bind($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'bound-children':
                // /api/ib-partners/{id}/bound-children 已绑定子级列表（Unbind 弹窗）
                if ($method === 'GET') {
                    $ibPartnerController->getBoundChildren($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'unbind':
                // /api/ib-partners/{id}/unbind 解绑子级
                if ($method === 'POST') {
                    $ibPartnerController->unbind($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'submit-risk-review':
                // /api/ib-partners/{id}/submit-risk-review
                if ($method === 'POST') {
                    $ibPartnerController->submitRiskReview($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'tier-change-blockers':
                // /api/ib-partners/{id}/tier-change-blockers 终审通过后改 Tier 前的 IB↔IB 绑定阻碍
                if ($method === 'GET') {
                    $ibPartnerController->getTierChangeBlockers($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'post-approval-update':
                // /api/ib-partners/{id}/post-approval-update 终审通过后修正资料
                if ($method === 'POST') {
                    $ibPartnerController->postApprovalUpdate($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'rules':
                // /api/ib-partners/{id}/rules
                if ($method === 'POST') {
                    if ($subAction === 'save') {
                        // /api/ib-partners/{id}/rules/save
                        $ibPartnerController->saveRules($id);
                    } else {
                        $ibPartnerController->assignRule($id);
                    }
                } elseif ($method === 'DELETE' && $subAction) {
                    // /api/ib-partners/{id}/rules/{ruleId}
                    $ibPartnerController->removeRule($id, $subAction);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'documents':
                // /api/ib-partners/{id}/documents
                if ($method === 'GET') {
                    $ibPartnerController->getDocuments($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'referral-suffix':
                // /api/ib-partners/{id}/referral-suffix 更新 IB Referral URL 后缀
                if ($method === 'POST') {
                    $ibPartnerController->updateReferralSuffix($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            default:
                Response::error('Route not found', 404);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
