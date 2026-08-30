<?php
/**
 * 客户端IB Dashboard控制器
 * 提供IB代理的Dashboard数据
 */

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/ClientIbPartnerAssignment.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/IbReferralSettings.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Database.php';

class ClientIbDashboardController {
    private $commissionOrderModel;
    private $ibPartnerModel;
    private $clientIbAssignmentModel;
    private $ibPartnerBindModel;

    public function __construct() {
        $this->commissionOrderModel = new IbCommissionOrder();
        $this->ibPartnerModel = new IbPartner();
        $this->clientIbAssignmentModel = new ClientIbPartnerAssignment();
        $this->ibPartnerBindModel = new IbPartnerBind();
    }

    /**
     * 获取当前登录用户ID（支持预览 X-Preview-Token 与 JWT）
     */
    private function getCurrentUserId() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized - No token provided', 401);
        }
        try {
            $payload = JWT::decode($token);
            if (!isset($payload['userId'])) {
                Response::error('Invalid token - No user ID', 401);
            }
            return $payload['userId'];
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Dashboard 只能查看当前登录账号名下已批准的 IB 身份。
     * 传入 ibPartnerId 时必须归属当前账号，避免用户通过 query string 查看别人的 IB 数据。
     */
    private function getDashboardIbPartner($currentUserId) {
        $requestedIbPartnerId = (int)($_GET['ibPartnerId'] ?? 0);
        $ibPartners = $this->ibPartnerModel->getAllByClientId($currentUserId);
        $approvedPartners = array_values(array_filter($ibPartners, function ($ibPartner) {
            return ($ibPartner['status'] ?? '') === IbPartner::STATUS_APPROVED;
        }));

        if (empty($approvedPartners)) {
            Response::error('IB partner not found', 404);
        }

        $selectedId = (int)($approvedPartners[0]['id'] ?? 0);
        if ($requestedIbPartnerId > 0) {
            $selectedId = 0;
            foreach ($approvedPartners as $ibPartner) {
                if ((int)($ibPartner['id'] ?? 0) === $requestedIbPartnerId) {
                    $selectedId = $requestedIbPartnerId;
                    break;
                }
            }
            if ($selectedId <= 0) {
                Response::error('IB partner not found', 404);
            }
        }

        $ibPartner = $this->ibPartnerModel->findById($selectedId);
        if (!$ibPartner || (int)($ibPartner['userId'] ?? 0) !== (int)$currentUserId || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            Response::error('IB partner not found', 404);
        }

        return $ibPartner;
    }

    /**
     * 获取当前账号可切换的 IB 身份列表
     * GET /api/client/ib/dashboard/partners
     */
    public function partners() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartners = $this->ibPartnerModel->getAllByClientId($currentUserId);
            $approvedPartners = [];

            foreach ($ibPartners as $ibPartner) {
                if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
                    continue;
                }
                $approvedPartners[] = [
                    'id' => (int)$ibPartner['id'],
                    'ibCode' => $ibPartner['ibCode'] ?? '',
                    'companyName' => $ibPartner['companyName'] ?? '',
                    'clientAlias' => $ibPartner['clientAlias'] ?? null,
                    'ibType' => $ibPartner['ibType'] ?? '',
                    'tierLevelId' => isset($ibPartner['tierLevelId']) ? (int)$ibPartner['tierLevelId'] : null,
                    'status' => $ibPartner['status'] ?? '',
                    'statusDisplay' => IbPartner::statusToDisplay($ibPartner['status'] ?? ''),
                    'registrationDate' => $ibPartner['registrationDate'] ?? null,
                    'ibName' => $ibPartner['ibName'] ?? '',
                    'email' => $ibPartner['email'] ?? ''
                ];
            }

            Response::success($approvedPartners);
        } catch (Exception $e) {
            Response::error('Failed to fetch IB partners: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 客户端为自己名下的某个 IB 身份设置/清空 clientAlias（"Name IB"）。
     * 只允许修改 status=approved 且 userId 等于当前登录账号的 IB，避免越权改别人的别名。
     * PUT /api/client/ib/dashboard/alias  Body: { ibPartnerId, clientAlias }
     */
    public function updateClientAlias() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $ibPartnerId = (int)($body['ibPartnerId'] ?? 0);
            if ($ibPartnerId <= 0) {
                Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
            }

            $ibPartner = $this->ibPartnerModel->findById($ibPartnerId);
            if (!$ibPartner
                || (int)($ibPartner['userId'] ?? 0) !== (int)$currentUserId
                || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
                Response::error('IB partner not found', 404);
            }

            // 允许传空串/null 表示清空别名；trim 后超过 200 字节按 200 截断由 DB 处理
            $raw = $body['clientAlias'] ?? null;
            $newAlias = null;
            if (is_string($raw)) {
                $trimmed = trim($raw);
                $newAlias = $trimmed === '' ? null : $trimmed;
            }
            if ($newAlias !== null && mb_strlen($newAlias) > 200) {
                Response::validationError(['clientAlias' => 'clientAlias must be 200 characters or fewer']);
            }

            $this->ibPartnerModel->update($ibPartnerId, ['clientAlias' => $newAlias]);

            Response::success([
                'id' => $ibPartnerId,
                'clientAlias' => $newAlias
            ], 'IB alias updated successfully');
        } catch (Exception $e) {
            Response::error('Failed to update IB alias: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取IB Dashboard统计信息
     * GET /api/client/ib/dashboard/statistics
     */
    public function statistics() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);

            $ibPartnerId = $ibPartner['id'];

            // 计算总网络（直接客户 + 下级IB + 间接客户）
            $networkStats = $this->getNetworkStatistics($ibPartnerId);

            // 计算总佣金（终身，来自 ib_commission_order）
            $totalEarned = $this->commissionOrderModel->getTotalCommissionByIbPartner($ibPartnerId);

            $today = new DateTime();
            $firstDayOfMonth = new DateTime($today->format('Y-m-01'));
            $thisMonth = $this->commissionOrderModel->getTotalCommissionByIbPartner(
                $ibPartnerId,
                $firstDayOfMonth->format('Y-m-d'),
                $today->format('Y-m-d')
            );

            $lastMonth = clone $firstDayOfMonth;
            $lastMonth->modify('-1 month');
            $lastMonthEnd = clone $firstDayOfMonth;
            $lastMonthEnd->modify('-1 day');
            $lastMonthCommission = $this->commissionOrderModel->getTotalCommissionByIbPartner(
                $ibPartnerId,
                $lastMonth->format('Y-m-d'),
                $lastMonthEnd->format('Y-m-d')
            );

            // 计算本月变化百分比
            $monthChange = $lastMonthCommission > 0
                ? (($thisMonth - $lastMonthCommission) / $lastMonthCommission) * 100
                : 0;

            $activeClients = $this->commissionOrderModel->getActiveClientsCount($ibPartnerId, date('Y-m-d', strtotime('-30 days')));
            $totalClients = $networkStats['directClients']; // directClients已经包含了所有客户端用户（排除IB代理）
            $activeRate = $totalClients > 0 ? ($activeClients / $totalClients) * 100 : 0;

            // 计算本月网络变化
            $firstDayOfLastMonth = clone $lastMonth;
            $networkStatsLastMonth = $this->getNetworkStatistics($ibPartnerId, $firstDayOfLastMonth->format('Y-m-d'));
            $networkChange = $networkStats['totalNetwork'] - $networkStatsLastMonth['totalNetwork'];

            // 计算终身变化（与上月对比）
            $totalEarnedLastMonth = $this->commissionOrderModel->getTotalCommissionByIbPartner(
                $ibPartnerId,
                null,
                $lastMonthEnd->format('Y-m-d')
            );
            $earnedChange = $totalEarnedLastMonth > 0
                ? (($totalEarned - $totalEarnedLastMonth) / $totalEarnedLastMonth) * 100
                : 0;

            // deposit / withdraw 终身合计：聚合该 IB 树下所有绑定客户（直接+间接）+ IB 自己账号
            $fundUserIds = $this->ibPartnerBindModel->getClientIdsUnderIbTree($ibPartnerId);
            $accountUserId = (int)($ibPartner['userId'] ?? 0);
            if ($accountUserId > 0) {
                $fundUserIds[] = $accountUserId;
            }
            $totalDeposit = $this->sumCompletedAmount('deposits', $fundUserIds);
            $totalWithdraw = $this->sumCompletedAmount('withdrawals', $fundUserIds);
            $netDeposit = $totalDeposit - $totalWithdraw;
            $totalTradingVolume = $this->commissionOrderModel->getTotalTradingVolumeLots($ibPartnerId);

            Response::success([
                'totalNetwork' => $networkStats['totalNetwork'],
                'networkChange' => $networkChange,
                'totalEarned' => (float)$totalEarned,
                'earnedChange' => round($earnedChange, 2),
                'thisMonth' => (float)$thisMonth,
                'monthChange' => round($monthChange, 2),
                'activeClients' => $activeClients,
                'activeRate' => round($activeRate, 1),
                'directClients' => $networkStats['directClients'],
                'subIbs' => $networkStats['subIbs'],
                'tier2Ibs' => $networkStats['tier2Ibs'],
                'tier3Ibs' => $networkStats['tier3Ibs'],
                'totalDeposit' => $totalDeposit,
                'totalWithdraw' => $totalWithdraw,
                'netDeposit' => $netDeposit,
                'totalTradingVolume' => $totalTradingVolume
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取推荐URL和统计（IB Referral URL，与后台 ib_referral_settings 一致）
     * GET /api/client/ib/dashboard/referral-url
     */
    public function referralUrl() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);
            $ibPartnerId = (int)($ibPartner['id'] ?? 0);
            if ($ibPartnerId <= 0) {
                Response::error('IB partner not found', 404);
            }
            $config = require __DIR__ . '/../config/app.php';
            $baseUrl = rtrim($config['client_frontend_url'] ?? '', '/');
            if ($baseUrl === '') {
                $baseUrl = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_HOST'] ?? '';
            }
            $refSettings = new IbReferralSettings();
            $refRow = $refSettings->getOrCreateByIbPartnerId($ibPartnerId);
            $suffix = trim((string)($refRow['referralSuffix'] ?? ''));
            $referralUrl = $baseUrl . '/#/registration/i/' . rawurlencode($suffix);
            $urlClicks = (int)($refRow['urlClicks'] ?? 0);
            $registrationsCount = (int)($refRow['registrationsCount'] ?? 0);
            $conversionRate = $urlClicks > 0 ? round($registrationsCount / $urlClicks * 100, 1) : 0;
            $referralCode = $ibPartner['ibCode'] ?? $ibPartner['referralCode'] ?? '';
            Response::success([
                'referralUrl' => $referralUrl,
                'referralCode' => $referralCode,
                'urlStats' => [
                    'clicks' => $urlClicks,
                    'registrations' => $registrationsCount,
                    'conversionRate' => $conversionRate
                ]
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch referral URL: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取佣金率
     * GET /api/client/ib/dashboard/commission-rates
     */
    public function commissionRates() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);

            // 优先从 ib_partner_rules + ibCommissionRules 获取当前用户规则；若无则回退到 application 自定义规则
            $rules = $this->ibPartnerModel->getCommissionRulesByPartnerRules($ibPartner['id']);
            $fromPartnerRules = !empty($rules);
            if (!$fromPartnerRules) {
                $rules = $this->ibPartnerModel->getCommissionRules($ibPartner['id']);
            }

            $commissionRates = [];
            $ruleNames = [];
            $rulesList = []; // Rule Name, Product, Target Region, Currency, Rate, Fixed Amount

            if ($fromPartnerRules) {
                // 来自 ib_partner_rules：每条规则一行，含 ruleName, product, targetRegion, payoutCurrency, rate, fixed_amount
                foreach ($rules as $rule) {
                    if (!empty($rule['ruleName'])) {
                        $ruleNames[] = $rule['ruleName'];
                    }
                    $productName = $rule['product'] ?? $rule['productName'] ?? '—';
                    $targetRegion = $rule['targetRegion'] ?? '—';
                    $currency = $rule['payoutCurrency'] ?? 'USD';
                    $rateVal = isset($rule['rate']) ? (float)$rule['rate'] : null;
                    $fixedVal = isset($rule['fixed_amount']) ? (float)$rule['fixed_amount'] : null;
                    $rateDisplay = $rateVal !== null && $rateVal !== '' ? (strpos((string)$rule['ruleType'], 'percent') !== false ? number_format($rateVal, 2) . '%' : '$' . number_format($rateVal, 2)) : '—';
                    $fixedAmountDisplay = $fixedVal !== null && $fixedVal !== '' ? '$' . number_format($fixedVal, 2) : '—';
                    $rulesList[] = [
                        'ruleName' => $rule['ruleName'] ?? '—',
                        'product' => $productName,
                        'targetRegion' => $targetRegion,
                        'currency' => $currency,
                        'rate' => $rateDisplay,
                        'fixedAmount' => $fixedAmountDisplay
                    ];
                    $amount = $rateVal !== null && $rateVal !== '' ? $rateVal : ($fixedVal !== null && $fixedVal !== '' ? $fixedVal : 0);
                    $commissionRates[] = [
                        'id' => $rule['id'] ?? uniqid(),
                        'product' => $productName,
                        'amount' => $amount,
                        'type' => $this->formatCommissionType($rule['ruleType'] ?? 'per_lot')
                    ];
                }
            } else {
                foreach ($rules as $rule) {
                    if (!empty($rule['ruleName'])) {
                        $ruleNames[] = $rule['ruleName'];
                    }
                    $targetRegion = $rule['targetRegion'] ?? $rule['target_region'] ?? '—';
                    $currency = $rule['payoutCurrency'] ?? $rule['payout_currency'] ?? 'USD';

                    if (isset($rule['products']) && is_array($rule['products'])) {
                        foreach ($rule['products'] as $product) {
                            $productName = $product['displayName'] ?? $product['productName'] ?? 'Unknown';
                            $amount = (float)($product['commissionRate'] ?? 0);
                            $commissionRates[] = [
                                'id' => $product['id'] ?? uniqid(),
                                'product' => $productName,
                                'amount' => $amount,
                                'type' => $this->formatCommissionType($product['commissionType'] ?? 'per_lot')
                            ];
                            $rateDisplay = '';
                            $fixedAmountDisplay = '';
                            $commType = $product['commissionType'] ?? 'per_lot';
                            if (in_array(strtolower($commType), ['percentage', 'per_lot', 'per_trade'], true)) {
                                $rateDisplay = (float)($product['commissionRate'] ?? 0);
                                if (strtolower($commType) === 'percentage') {
                                    $rateDisplay = number_format($rateDisplay, 2) . '%';
                                } else {
                                    $rateDisplay = '$' . number_format($rateDisplay, 2);
                                }
                            } else {
                                $fixedAmountDisplay = '$' . number_format((float)($product['commissionRate'] ?? 0), 2);
                            }
                            $rulesList[] = [
                                'ruleName' => $rule['ruleName'] ?? '—',
                                'product' => $productName,
                                'targetRegion' => $targetRegion,
                                'currency' => $currency,
                                'rate' => $rateDisplay ?: '—',
                                'fixedAmount' => $fixedAmountDisplay ?: '—'
                            ];
                        }
                    }
                }
            }

            $commissionStats = $this->commissionOrderModel->getCommissionStatsForDashboard($ibPartner['id']);

            Response::success([
                'rates' => $commissionRates,
                'ruleNames' => $ruleNames,
                'rulesList' => $rulesList,
                'commissionStats' => $commissionStats
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch commission rates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取IB Journey时间线
     * GET /api/client/ib/dashboard/journey
     */
    public function journey() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);

            $ibPartnerId = $ibPartner['id'];
            $applicationId = $ibPartner['applicationId'] ?? null;

            $journeyEvents = [];
            $db = Database::getInstance();

            // 1. 获取IB申请信息
            if ($applicationId) {
                $application = $db->fetchOne(
                    "SELECT * FROM ibApplications WHERE id = :applicationId",
                    ['applicationId' => $applicationId]
                );

                if ($application) {
                    // IB申请提交
                    if ($application['applicationDate']) {
                        $journeyEvents[] = [
                            'date' => date('M d, Y', strtotime($application['applicationDate'])),
                            'title' => 'IB Application Submitted',
                            'description' => 'Your IB partnership application was submitted and is under review.',
                            'icon' => 'fas fa-file-alt',
                            'badge' => 'Application Started',
                            'badgeClass' => 'partnership'
                        ];
                    }

                    // IB申请审批通过
                    if ($application['reviewCompletedDate'] && $application['applicationStatus'] === 'approved') {
                        $tierInfo = '';
                        if ($application['assignedTierLevelId']) {
                            $tierLevel = $db->fetchOne(
                                "SELECT tierName FROM ibTierLevels WHERE id = :tierLevelId",
                                ['tierLevelId' => $application['assignedTierLevelId']]
                            );
                            if ($tierLevel) {
                                $tierInfo = " You were assigned {$tierLevel['tierName']} status.";
                            }
                        }

                        $journeyEvents[] = [
                            'date' => date('M d, Y', strtotime($application['reviewCompletedDate'])),
                            'title' => 'IB Application Approved',
                            'description' => "Your IB partnership application was approved. You were assigned IB Code: {$ibPartner['ibCode']}.{$tierInfo}",
                            'icon' => 'fas fa-check-circle',
                            'badge' => 'Partnership Started',
                            'badgeClass' => 'partnership'
                        ];
                    }
                }
            }

            // 2. 获取第一个客户（来自 ib_partner_bind）
            $firstClient = $db->fetchOne(
                "SELECT cu.*, b.createdAt AS bindCreatedAt
                 FROM ib_partner_bind b
                 INNER JOIN clientUsers cu ON b.childClientId = cu.id
                 WHERE b.parentId = :ibPartnerId
                 AND b.isClient = 1
                 AND cu.id NOT IN (SELECT userId FROM ibPartners WHERE userId IS NOT NULL)
                 ORDER BY b.createdAt ASC
                 LIMIT 1",
                ['ibPartnerId' => $ibPartnerId]
            );

            if ($firstClient) {
                $clientName = trim(($firstClient['firstName'] ?? '') . ' ' . ($firstClient['lastName'] ?? '')) ?: $firstClient['email'];
                $firstCommission = $this->commissionOrderModel->getTotalCommissionByClient($firstClient['id'], $ibPartnerId);
                $joinDate = $firstClient['bindCreatedAt'] ?? $firstClient['createdAt'] ?? 'now';
                $journeyEvents[] = [
                    'date' => date('M d, Y', strtotime($joinDate)),
                    'title' => 'First Client Referral',
                    'description' => "Your first referred client \"{$clientName}\" completed registration and started trading. You earned your first commission of $" . number_format($firstCommission, 2) . ".",
                    'icon' => 'fas fa-user-plus',
                    'badge' => 'First Earning',
                    'badgeClass' => 'earning'
                ];
            }

            // 3. 获取第一个下级IB（来自 ib_partner_bind）
            $firstSubIb = $db->fetchOne(
                "SELECT b.id, b.childId AS ibPartnerId, b.parentId AS parentIbPartnerId, b.createdAt AS establishedAt, ib.companyName, ib.ibCode
                 FROM ib_partner_bind b
                 INNER JOIN ibPartners ib ON ib.id = b.childId
                 WHERE b.parentId = :ibPartnerId AND b.isClient = 0
                 ORDER BY b.createdAt ASC
                 LIMIT 1",
                ['ibPartnerId' => $ibPartnerId]
            );

            if ($firstSubIb) {
                $journeyEvents[] = [
                    'date' => date('M d, Y', strtotime($firstSubIb['establishedAt'])),
                    'title' => 'First Sub-IB Recruited',
                    'description' => "Successfully recruited \"{$firstSubIb['companyName']}\" ({$firstSubIb['ibCode']}) as your first sub-IB. Your network expanded to include indirect referrals and multi-level commissions.",
                    'icon' => 'fas fa-handshake',
                    'badge' => 'Milestone Achieved',
                    'badgeClass' => 'milestone'
                ];
            }

            // 4. 获取当前状态
            $networkStats = $this->getNetworkStatistics($ibPartnerId);
            $totalEarned = $this->commissionOrderModel->getTotalCommissionByIbPartner($ibPartnerId);
            $tierInfo = '';
            if ($ibPartner['tierLevelId']) {
                $tierLevel = $db->fetchOne(
                    "SELECT tierName FROM ibTierLevels WHERE id = :tierLevelId",
                    ['tierLevelId' => $ibPartner['tierLevelId']]
                );
                if ($tierLevel) {
                    $tierInfo = $tierLevel['tierName'];
                }
            }

            if (empty($tierInfo)) {
                $tierInfo = 'IB Partner';
            }

            array_unshift($journeyEvents, [
                'date' => 'Current Status',
                'title' => $tierInfo,
                'description' => "You are currently operating as a {$tierInfo} with full recruiting and management permissions. Your network has grown to {$networkStats['totalNetwork']} members with {$networkStats['directClients']} active traders. Total commission earned: $" . number_format($totalEarned, 2) . ".",
                'icon' => 'fas fa-star',
                'badge' => 'Active Since: ' . date('M d, Y', strtotime($ibPartner['registrationDate'] ?? $ibPartner['approvalDate'] ?? 'now')),
                'badgeClass' => ''
            ]);

            // 按日期倒序排列（当前状态在最前面）
            Response::success($journeyEvents);
        } catch (Exception $e) {
            Response::error('Failed to fetch journey: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取IB网络图数据
     * GET /api/client/ib/dashboard/network
     */
    public function network() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);

            $ibPartnerId = $ibPartner['id'];

            // 获取网络成员（直接下级）
            $networkMembers = $this->getNetworkMembers($ibPartnerId);

            Response::success($networkMembers);
        } catch (Exception $e) {
            Response::error('Failed to fetch network: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取网络成员列表（按直接绑定的子IB和Client数量分页，层级展示）
     * GET /api/client/ib/dashboard/network-members
     * 分页标准与客户端 Commission Report 一致：仅对 depth=0 的直接成员分页，子级按层级展示。
     */
    public function networkMembers() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getDashboardIbPartner($currentUserId);

            $ibPartnerId = $ibPartner['id'];
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 10);
            if ($perPage < 1) $perPage = 10;
            if ($perPage > 100) $perPage = 100;
            $search = trim((string)($_GET['search'] ?? ''));
            $type = $_GET['type'] ?? 'all'; // all, ib, client

            // 获取全部网络成员（已按 type/search 过滤，含层级 depth/parentId/hasChildren）
            $members = $this->getAllNetworkMembers($ibPartnerId, $search, $type);

            $rowKey = function ($r) {
                $t = $r['type'] ?? '';
                $id = (int)($r['id'] ?? 0);
                return ($t === 'ib' ? 'ib-' : 'client-') . $id;
            };
            $rowKeyToRootKey = [];
            $byDepth = [];
            foreach ($members as $r) {
                $d = (int)($r['depth'] ?? 0);
                $byDepth[$d][] = $r;
            }
            ksort($byDepth, SORT_NUMERIC);
            foreach ($byDepth as $depth => $rows) {
                foreach ($rows as $r) {
                    $k = $rowKey($r);
                    if ($depth === 0) {
                        $rowKeyToRootKey[$k] = $k;
                    } else {
                        $pid = (int)($r['parentId'] ?? 0);
                        $parentKey = $pid > 0 ? ('ib-' . $pid) : $k;
                        $rowKeyToRootKey[$k] = isset($rowKeyToRootKey[$parentKey]) ? $rowKeyToRootKey[$parentKey] : $parentKey;
                    }
                }
            }

            $directRows = array_values(array_filter($members, function ($r) { return (int)($r['depth'] ?? 0) === 0; }));
            $directKeys = array_map($rowKey, $directRows);
            $totalDirect = count($directKeys);

            $offset = ($page - 1) * $perPage;
            $pageRootKeys = array_slice($directKeys, $offset, $perPage);

            $itemsForPage = array_values(array_filter($members, function ($row) use ($rowKeyToRootKey, $pageRootKeys, $rowKey) {
                $rk = $rowKey($row);
                $rootKey = $rowKeyToRootKey[$rk] ?? $rk;
                return in_array($rootKey, $pageRootKeys, true);
            }));

            Response::paginated($itemsForPage, $totalDirect, $page, $perPage);
        } catch (Exception $e) {
            Response::error('Failed to fetch network members: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取网络统计信息
     * Level 2 / Level 3 IBs 按 ib_partner_bind、ibPartners、ibTierLevels 关联后的 tierLevel 统计数量
     */
    private function getNetworkStatistics($ibPartnerId, $beforeDate = null) {
        // 获取所有下级IB（递归，来自 ib_partner_bind）
        $allSubIbs = $this->ibPartnerBindModel->getAllSubIbs($ibPartnerId, $beforeDate);

        $allIbIds = [$ibPartnerId];
        $subIbPartnerIds = [];
        foreach ($allSubIbs as $subIb) {
            $allIbIds[] = $subIb['ibPartnerId'];
            $subIbPartnerIds[] = $subIb['ibPartnerId'];
        }

        // 按 ibPartners.tierLevelId -> ibTierLevels.tierLevel 统计 Level 2 / Level 3 数量
        $tier2Ibs = 0;
        $tier3Ibs = 0;
        if (!empty($subIbPartnerIds)) {
            $db = Database::getInstance();
            $placeholders = implode(',', array_fill(0, count($subIbPartnerIds), '?'));
            $sql = "SELECT tl.tierLevel, COUNT(*) AS cnt FROM ibPartners ib
                    LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
                    WHERE ib.id IN ({$placeholders})
                    GROUP BY tl.tierLevel";
            $rows = $db->fetchAll($sql, $subIbPartnerIds);
            foreach ($rows as $r) {
                $level = (int)($r['tierLevel'] ?? 0);
                $cnt = (int)($r['cnt'] ?? 0);
                if ($level === 2) $tier2Ibs = $cnt;
                if ($level === 3) $tier3Ibs = $cnt;
            }
        }

        // 统计所有客户端用户（排除是IB代理的用户）
        // 获取所有IB代理的userId
        $ibUserIds = [];
        if (!empty($allIbIds)) {
            $placeholders = [];
            $params = [];
            foreach ($allIbIds as $index => $ibId) {
                $key = "ibId{$index}";
                $placeholders[] = ":{$key}";
                $params[$key] = $ibId;
            }
            $sql = "SELECT DISTINCT userId FROM ibPartners WHERE id IN (" . implode(',', $placeholders) . ") AND userId IS NOT NULL";
            $db = Database::getInstance();
            $ibUsers = $db->fetchAll($sql, $params);
            foreach ($ibUsers as $ibUser) {
                $ibUserIds[] = $ibUser['userId'];
            }
        }

        // 统计所有客户端用户（排除是IB代理的用户，来自 ib_partner_bind）
        $directClients = 0;
        $whereClause = "WHERE b.isClient = 1";
        $params = [];
        $paramIndex = 0;

        if ($beforeDate) {
            $whereClause .= " AND (b.createdAt <= :beforeDate OR b.createdAt IS NULL)";
            $params['beforeDate'] = $beforeDate;
        }

        if (!empty($allIbIds)) {
            $placeholders = [];
            foreach ($allIbIds as $ibId) {
                $key = "ibId{$paramIndex}";
                $placeholders[] = ":{$key}";
                $params[$key] = $ibId;
                $paramIndex++;
            }
            $whereClause .= " AND b.parentId IN (" . implode(',', $placeholders) . ")";
        }

        // 排除是IB代理的用户
        if (!empty($ibUserIds)) {
            $placeholders = [];
            foreach ($ibUserIds as $userId) {
                $key = "userId{$paramIndex}";
                $placeholders[] = ":{$key}";
                $params[$key] = $userId;
                $paramIndex++;
            }
            $whereClause .= " AND b.childClientId NOT IN (" . implode(',', $placeholders) . ")";
        }

        $sql = "SELECT COUNT(DISTINCT b.childClientId) as count
                FROM ib_partner_bind b
                $whereClause";

        $db = Database::getInstance();
        $result = $db->fetchOne($sql, $params);
        $directClients = (int)($result['count'] ?? 0);

        // 获取直接下级IB数
        $subIbs = $this->ibPartnerBindModel->getDirectSubIbsCount($ibPartnerId, $beforeDate);

        return [
            'totalNetwork' => $directClients + count($allSubIbs),
            'directClients' => $directClients, // 所有不是IB代理的客户端用户
            'subIbs' => $subIbs,
            'tier2Ibs' => $tier2Ibs,
            'tier3Ibs' => $tier3Ibs
        ];
    }

    /**
     * 获取网络成员（用于网络图）
     */
    private function getNetworkMembers($ibPartnerId, $maxDepth = 3) {
        $members = [];

        // 获取直接下级
        $directMembers = $this->getDirectNetworkMembers($ibPartnerId);

        foreach ($directMembers as $member) {
            $memberData = [
                'id' => $member['id'],
                'name' => $member['name'],
                'code' => $member['code'] ?? '',
                'initials' => $this->getInitials($member['name']),
                'type' => $member['type'], // 'ib' or 'client'
                'hasChildren' => false,
                'children' => []
            ];

            // 如果是IB，递归获取下级
            if ($member['type'] === 'ib' && $maxDepth > 1) {
                // 从 'ib-111' 格式中提取数字ID
                $subIbId = str_replace('ib-', '', $member['id']);
                $subMembers = $this->getNetworkMembers((int)$subIbId, $maxDepth - 1);
                if (!empty($subMembers)) {
                    $memberData['hasChildren'] = true;
                    $memberData['children'] = $subMembers;
                }
            }

            $members[] = $memberData;
        }

        return $members;
    }

    /**
     * 获取直接网络成员
     */
    private function getDirectNetworkMembers($ibPartnerId) {
        $members = [];

        // 获取直接客户
        $clients = $this->clientIbAssignmentModel->getDirectClients($ibPartnerId);
        foreach ($clients as $client) {
            $members[] = [
                'id' => 'client-' . $client['id'],
                'name' => $client['fullName'] ?? $client['email'] ?? 'Unknown',
                'code' => 'CL-' . $client['id'],
                'initials' => $this->getInitials($client['fullName'] ?? $client['email'] ?? 'Unknown'),
                'type' => 'client'
            ];
        }

        // 获取直接下级IB（名称优先显示关联用户 fullName，其次 companyName）
        $subIbs = $this->ibPartnerBindModel->getDirectSubIbs($ibPartnerId);
        $db = Database::getInstance();
        foreach ($subIbs as $subIb) {
            $ibInfo = $this->ibPartnerModel->findById($subIb['subIbPartnerId']);
            if ($ibInfo) {
                // clientAlias 优先：网络图中下级 IB 节点统一按客户端展示用别名显示
                $displayName = trim($ibInfo['clientAlias'] ?? '') ?: ($ibInfo['companyName'] ?? 'Unknown');
                if (empty(trim($ibInfo['clientAlias'] ?? '')) && !empty($ibInfo['userId'])) {
                    $cu = $db->fetchOne(
                        "SELECT firstName, lastName, email FROM clientUsers WHERE id = :uid LIMIT 1",
                        ['uid' => $ibInfo['userId']]
                    );
                    if ($cu) {
                        $fullName = trim(($cu['firstName'] ?? '') . ' ' . ($cu['lastName'] ?? ''));
                        $displayName = $fullName ?: ($cu['email'] ?? '') ?: $displayName;
                    }
                }
                $tier = (int)($subIb['tier'] ?? 2);
                $members[] = [
                    'id' => 'ib-' . $subIb['subIbPartnerId'],
                    'name' => $displayName,
                    'code' => $ibInfo['ibCode'] ?? '',
                    'initials' => $this->getInitials($displayName),
                    'type' => 'ib',
                    'tier' => 'Tier ' . $tier,
                    'tierIcon' => $tier === 2 ? 'fas fa-star' : ($tier === 3 ? 'fas fa-certificate' : 'fas fa-crown')
                ];
            }
        }

        return $members;
    }

    /**
     * 按层级构建网络成员扁平列表（与 Commission Report 一致：depth, parentId, hasChildren）
     */
    private function buildNetworkMembersFlat($ibPartnerId, $depth = 0, $parentId = 0) {
        $flat = [];
        $children = $this->ibPartnerBindModel->getDirectBindChildren($ibPartnerId);
        foreach ($children as $c) {
            $childId = (int)$c['id'];
            $type = $c['type'] === 'Sub-IB' ? 'ib' : 'client';
            $subChildren = ($type === 'ib') ? $this->ibPartnerBindModel->getDirectBindChildren($childId) : [];
            $hasChildren = count($subChildren) > 0;
            $name = trim($c['referralName'] ?? '') ?: ($c['email'] ?? '—');
            $code = $c['referralCode'] ?? '';
            $flat[] = [
                'id' => $childId,
                'type' => $type,
                'name' => $name,
                'code' => $code,
                'initials' => $this->getInitials($name),
                'depth' => $depth,
                'parentId' => $parentId,
                'hasChildren' => $hasChildren
            ];
            if ($type === 'ib' && $hasChildren) {
                $flat = array_merge($flat, $this->buildNetworkMembersFlat($childId, $depth + 1, $childId));
            }
        }
        return $flat;
    }

    /**
     * 获取所有网络成员（用于列表，带层级 depth/parentId/hasChildren）
     */
    private function getAllNetworkMembers($ibPartnerId, $search = '', $type = 'all') {
        $flat = $this->buildNetworkMembersFlat($ibPartnerId, 0, 0);
        $members = [];

        foreach ($flat as $member) {
            if ($type !== 'all' && $member['type'] !== $type) {
                continue;
            }
            if ($search &&
                stripos($member['name'], $search) === false &&
                stripos($member['code'], $search) === false) {
                continue;
            }

            $memberId = (int)$member['id'];
            $clientIbPartnerId = $ibPartnerId;
            if ($member['type'] === 'client') {
                $db = Database::getInstance();
                $clientAssignment = $db->fetchOne(
                    "SELECT parentId AS ibPartnerId FROM ib_partner_bind WHERE childClientId = :clientId AND isClient = 1 ORDER BY createdAt ASC LIMIT 1",
                    ['clientId' => $memberId]
                );
                if ($clientAssignment) {
                    $clientIbPartnerId = $clientAssignment['ibPartnerId'];
                }
            }

            $commission = $member['type'] === 'ib'
                ? $this->commissionOrderModel->getTotalCommissionByIbPartner($memberId)
                : $this->commissionOrderModel->getTotalCommissionByClient($memberId, $clientIbPartnerId);

            $details = $this->getMemberDetails($member, $memberId, $ibPartnerId);
            $performanceOverview = $member['performanceOverview'] ?? null;

            $members[] = [
                'id' => $member['id'],
                'name' => $member['name'],
                'code' => $member['code'],
                'initials' => $member['initials'],
                'type' => $member['type'],
                'typeLabel' => $member['type'] === 'ib' ? 'Sub-IB' : 'Direct Client',
                'avatarClass' => $member['type'] === 'ib' ? 'tier2-bg' : 'client-bg',
                'badgeClass' => $member['type'] === 'ib' ? 'sub-ib' : 'direct-client',
                'depth' => (int)$member['depth'],
                'parentId' => (int)$member['parentId'],
                'hasChildren' => !empty($member['hasChildren']),
                'commission' => (float)$commission,
                'performance' => $this->getMemberPerformance($member, $memberId, $ibPartnerId),
                'details' => $details,
                'performanceOverview' => $performanceOverview
            ];
        }

        return $members;
    }

    /**
     * 递归获取所有网络成员（保留供 network 图等使用）
     */
    private function getAllNetworkMembersRecursive($ibPartnerId, $visited = []) {
        if (in_array($ibPartnerId, $visited)) {
            return [];
        }
        $visited[] = $ibPartnerId;
        $members = [];
        $directMembers = $this->getDirectNetworkMembers($ibPartnerId);
        foreach ($directMembers as $member) {
            $member['initials'] = $this->getInitials($member['name']);
            $members[] = $member;
            if ($member['type'] === 'ib') {
                $subIbId = str_replace('ib-', '', $member['id']);
                $subMembers = $this->getAllNetworkMembersRecursive($subIbId, $visited);
                $members = array_merge($members, $subMembers);
            }
        }
        return $members;
    }

    /**
     * 获取成员绩效
     * @param array $member 成员信息
     * @param int $memberId 成员ID
     * @param int|null $currentIbPartnerId 当前登录用户的IB ID（用于查询客户端数据）
     */
    private function getMemberPerformance($member, $memberId, $currentIbPartnerId = null) {
        if ($member['type'] === 'ib') {
            // IB代理：显示客户数和下级IB数
            $totalClients = $this->clientIbAssignmentModel->getDirectClientsCount($memberId);
            $subIbsCount = $this->ibPartnerBindModel->getDirectSubIbsCount($memberId);

            return [
                'clients' => $totalClients,
                'subIbs' => $subIbsCount
            ];
        } else {
            // 客户端用户：显示交易量和交易订单数
            $db = Database::getInstance();

            // 获取客户端所属的IB（来自 ib_partner_bind）
            $clientAssignment = $db->fetchOne(
                "SELECT parentId AS ibPartnerId
                 FROM ib_partner_bind
                 WHERE childClientId = :clientId AND isClient = 1
                 ORDER BY createdAt ASC
                 LIMIT 1",
                ['clientId' => $memberId]
            );

            $clientIbPartnerId = $clientAssignment['ibPartnerId'] ?? $currentIbPartnerId;

            if ($clientIbPartnerId) {
                $tradingData = $db->fetchOne(
                    "SELECT
                        SUM(o.volume * o.openprice) AS totalVolume,
                        COUNT(DISTINCT ico.orderId) AS totalTrades
                     FROM ib_commission_order ico
                     INNER JOIN orders o ON ico.orderId = o.id
                     INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
                     INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
                     WHERE ta.userId = :clientId AND ico.ibPartnerId = :ibPartnerId",
                    [
                        'clientId' => $memberId,
                        'ibPartnerId' => $clientIbPartnerId
                    ]
                );

                $volume = (float)($tradingData['totalVolume'] ?? 0);
                $trades = (int)($tradingData['totalTrades'] ?? 0);

                return [
                    'volume' => $volume,
                    'trades' => $trades
                ];
            }

            return [
                'volume' => 0,
                'trades' => 0
            ];
        }
    }

    /**
     * 获取成员详情
     * @param array $member 成员信息（通过引用传递，可以修改performanceOverview）
     * @param int $memberId 成员ID
     * @param int $currentIbPartnerId 当前登录用户的IB ID（用于查询客户端数据）
     * @return array 详情数组
     */
    private function getMemberDetails(&$member, $memberId, $currentIbPartnerId = null) {
        $details = [];

        if ($member['type'] === 'ib') {
            // IB代理的详情
            $ibInfo = $this->ibPartnerModel->findById($memberId);
            if ($ibInfo) {
                // 获取加入日期
                $joinDate = $ibInfo['registrationDate'] ?? $ibInfo['approvalDate'] ?? null;
                if ($joinDate) {
                    $details[] = [
                        'label' => 'Join Date',
                        'value' => date('M d, Y', strtotime($joinDate)),
                        'style' => 'font-size: 16px;'
                    ];
                }

                // 获取总客户数（递归统计所有下级客户）
                $totalClients = $this->clientIbAssignmentModel->getDirectClientsCount($memberId);
                // TODO: 如果需要递归统计所有下级客户，需要实现递归方法
                $details[] = [
                    'label' => 'Total Clients',
                    'value' => (string)$totalClients
                ];

                // 获取本月佣金
                $thisMonth = $this->commissionOrderModel->getTotalCommissionByIbPartner(
                    $memberId,
                    date('Y-m-01'),
                    date('Y-m-t')
                );
                $details[] = [
                    'label' => 'This Month',
                    'value' => '$' . number_format($thisMonth, 2)
                ];

                // 获取总佣金
                $totalEarned = $this->commissionOrderModel->getTotalCommissionByIbPartner($memberId);
                $details[] = [
                    'label' => 'Total Earned',
                    'value' => '$' . number_format($totalEarned, 2)
                ];

                $totalProfitLoss = $this->sumCommissionLinkedProfitLoss($memberId);
                $details[] = [
                    'label' => 'Profit/Loss',
                    'rawAmount' => $totalProfitLoss,
                ];

                // Own-account only (no downline): this Sub-IB's userId deposits − withdrawals
                $details[] = [
                    'label' => 'Net Deposit',
                    'rawAmount' => $this->getMemberOwnNetDeposit((int)($ibInfo['userId'] ?? 0)),
                ];

                // 获取下级IB数
                $subIbsCount = $this->ibPartnerBindModel->getDirectSubIbsCount($memberId);

                // Performance Overview（通过引用传递到$member数组）
                $member['performanceOverview'] = [
                    [
                        'label' => 'Trading Volume',
                        'value' => '$' . number_format($ibInfo['totalTradingVolume'] ?? 0, 0),
                        'color' => '#667eea'
                    ],
                    [
                        'label' => 'Active Rate',
                        'value' => ($totalClients > 0 ? round(($ibInfo['activeClients'] ?? 0) / $totalClients * 100) : 0) . '%',
                        'color' => '#48bb78'
                    ],
                    [
                        'label' => 'Sub-IBs',
                        'value' => (string)$subIbsCount,
                        'color' => '#f59e0b'
                    ]
                ];
            }
        } else {
            // 客户端用户的详情
            $db = Database::getInstance();

            // 获取客户端信息及其所属的IB（来自 ib_partner_bind）
            $clientInfo = $db->fetchOne(
                "SELECT cu.*, b.createdAt AS bindCreatedAt, b.parentId AS ibPartnerId
                 FROM clientUsers cu
                 INNER JOIN ib_partner_bind b ON cu.id = b.childClientId AND b.isClient = 1
                 WHERE cu.id = :clientId
                 ORDER BY b.createdAt ASC
                 LIMIT 1",
                ['clientId' => $memberId]
            );

            if ($clientInfo) {
                $clientIbPartnerId = $clientInfo['ibPartnerId'] ?? $currentIbPartnerId;

                // 获取加入日期
                $joinDate = $clientInfo['bindCreatedAt'] ?? $clientInfo['createdAt'] ?? null;
                if ($joinDate) {
                    $details[] = [
                        'label' => 'Join Date',
                        'value' => date('M d, Y', strtotime($joinDate)),
                        'style' => 'font-size: 16px;'
                    ];
                }

                $tradingVolume = $db->fetchOne(
                    "SELECT SUM(o.volume * o.openprice) AS totalVolume
                     FROM ib_commission_order ico
                     INNER JOIN orders o ON ico.orderId = o.id
                     INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
                     INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
                     WHERE ta.userId = :clientId AND ico.ibPartnerId = :ibPartnerId",
                    [
                        'clientId' => $memberId,
                        'ibPartnerId' => $clientIbPartnerId
                    ]
                );
                $volumeAmount = (float)($tradingVolume['totalVolume'] ?? 0);
                $details[] = [
                    'label' => 'Trading Volume',
                    'value' => '$' . number_format($volumeAmount, 0)
                ];

                // 获取佣金（使用该客户端所属的IB ID）
                $commission = $this->commissionOrderModel->getTotalCommissionByClient($memberId, $clientIbPartnerId);
                $details[] = [
                    'label' => 'Commission',
                    'value' => '$' . number_format($commission, 2)
                ];

                // 账户类型（可以根据需要扩展）
                $details[] = [
                    'label' => 'Account Type',
                    'value' => 'Standard',
                    'style' => 'font-size: 16px;'
                ];

                $totalProfitLoss = $this->sumCommissionLinkedProfitLoss($clientIbPartnerId, $memberId);
                $details[] = [
                    'label' => 'Profit/Loss',
                    'rawAmount' => $totalProfitLoss,
                ];

                // Own-account only: this Direct Client's userId deposits − withdrawals
                $details[] = [
                    'label' => 'Net Deposit',
                    'rawAmount' => $this->getMemberOwnNetDeposit((int)$memberId),
                ];
            }
        }

        return $details;
    }

    /**
     * Net deposit for one client user only (no IB tree): completed deposits − completed withdrawals.
     */
    private function getMemberOwnNetDeposit($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return 0.0;
        }
        $totalDeposit = $this->sumCompletedAmount('deposits', [$userId]);
        $totalWithdraw = $this->sumCompletedAmount('withdrawals', [$userId]);
        return $totalDeposit - $totalWithdraw;
    }

    /**
     * 格式化佣金类型
     */
    private function formatCommissionType($type) {
        $types = [
            'per_lot' => 'per lot',
            'percentage' => 'percentage',
            'per_trade' => 'per trade',
            'cash_back' => 'cash back / lot',
            'hybrid' => 'hybrid'
        ];
        return $types[$type] ?? $type;
    }

    /**
     * 获取姓名首字母
     */
    private function getInitials($name) {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }
        return substr($initials, 0, 2) ?: '??';
    }

    /**
     * All-time commission-linked profit/loss (orders.profit on deduped orderId rows).
     * When $clientUserId is set, scope matches Direct Client Trading Volume in getMemberDetails().
     */
    private function sumCommissionLinkedProfitLoss($ibPartnerId, $clientUserId = null) {
        $db = Database::getInstance();
        $params = ['ibPartnerId' => (int)$ibPartnerId];

        if ($clientUserId !== null) {
            $params['clientId'] = (int)$clientUserId;
            $sql = "SELECT COALESCE(SUM(o.profit), 0) AS totalProfitLoss
                FROM (
                    SELECT ico.orderId
                    FROM ib_commission_order ico
                    INNER JOIN orders o2 ON ico.orderId = o2.id
                    INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o2.trading_login
                    INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
                    WHERE ta.userId = :clientId
                      AND ico.ibPartnerId = :ibPartnerId
                      AND ico.status != 'cancelled'
                      AND ico.orderId IS NOT NULL
                    GROUP BY ico.orderId
                ) x
                INNER JOIN orders o ON o.id = x.orderId";
        } else {
            $sql = "SELECT COALESCE(SUM(o.profit), 0) AS totalProfitLoss
                FROM (
                    SELECT ico.orderId
                    FROM ib_commission_order ico
                    WHERE ico.ibPartnerId = :ibPartnerId
                      AND ico.status != 'cancelled'
                      AND ico.orderId IS NOT NULL
                    GROUP BY ico.orderId
                ) x
                INNER JOIN orders o ON o.id = x.orderId";
        }

        $row = $db->fetchOne($sql, $params);
        return (float)($row['totalProfitLoss'] ?? 0);
    }

    /**
     * 终身合计一组用户 status='completed' 的金额（不带日期过滤）
     * 表名走 'deposits' / 'withdrawals' 白名单，userId 列表为空时返回 0
     */
    private function sumCompletedAmount($table, $userIds) {
        if (!in_array($table, ['deposits', 'withdrawals'], true)) {
            return 0.0;
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array)$userIds),
            function ($id) { return $id > 0; }
        )));
        if (empty($ids)) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $row = Database::getInstance()->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM {$table}
             WHERE userId IN ({$placeholders}) AND status = 'completed'",
            $ids
        );
        return (float)($row['total'] ?? 0);
    }
}
