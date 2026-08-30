<?php
/**
 * 用户管理控制器
 */

require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminRole.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/AdminUserProfile.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Position.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/OperationLog/AccountsOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/AdminUserLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class UserController {
    private $userModel;
    private $roleModel;
    private $permissionModel;
    private $profileModel;
    private $departmentModel;
    private $positionModel;

    public function __construct() {
        $this->userModel = new AdminUser();
        $this->roleModel = new AdminRole();
        $this->permissionModel = new AdminPermission();
        $this->profileModel = new AdminUserProfile();
        $this->departmentModel = new Department();
        $this->positionModel = new Position();
    }

    /**
     * 获取用户列表
     * GET /api/users
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $status = $_GET['status'] ?? null;
        $roleId = $_GET['role_id'] ?? null;
        $search = $_GET['search'] ?? null;

        // 构建查询条件
        $conditions = ['deletedAt' => null];

        if ($status) {
            $conditions['status'] = $status;
        }

        if ($roleId) {
            $conditions['roleId'] = $roleId;
        }

        // 如果有搜索关键词
        if ($search) {
            $result = $this->userModel->search($search, $page, $perPage);
        } else {
            $result = $this->userModel->paginate($page, $perPage, $conditions, 'createdAt DESC');
        }

        // 获取每个用户的角色信息
        foreach ($result['items'] as &$user) {
            $userInfo = $this->userModel->getUserFullInfo($user['id']);
            $user = array_merge($user, $userInfo);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个用户
     * GET /api/users/{id}
     */
    public function show($id) {
        $userInfo = $this->userModel->getUserFullInfo($id);

        if (!$userInfo) {
            Response::notFound('User not found');
        }

        // 获取用户权限
        $permissions = $this->permissionModel->getUserPermissions($id);
        $userInfo['permissions'] = $permissions;

        Response::success($userInfo);
    }

    /**
     * 创建用户
     * POST /api/users/create (新路由)
     * POST /api/users (向后兼容)
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $input = AccountsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        // 获取当前用户ID
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $operatorId = (int) ($payload['userId'] ?? 0);

        if (!is_array($data)) {
            AccountsOperationLog::logFailure(
                $input,
                'add',
                'adminUserCreateFailure',
                'Invalid JSON body',
                null,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        // 验证输入
        $errors = Validator::validateData($data, [
            'username' => 'required|min:3|unique:adminUsers,username',
            'email' => 'required|email|unique:adminUsers,email',
            'password' => 'required|min:8',
            'fullName' => 'required',
            'roleId' => 'required|numeric',
            'departmentId' => 'nullable|numeric',
            'positionId' => 'nullable|numeric'
        ]);
        if (!empty($errors)) {
            AccountsOperationLog::logFailure(
                $input,
                'add',
                'adminUserCreateFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                null,
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        $createdBy = $operatorId;

        // 生成头像缩写
        $nameParts = explode(' ', $data['fullName']);
        $initials = strtoupper(
            substr($nameParts[0], 0, 1) .
            substr($nameParts[count($nameParts) - 1] ?? '', 0, 1)
        );

        // 随机头像颜色
        $colors = [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'
        ];

        // 创建用户
        $userId = $this->userModel->create([
            'username' => $data['username'],
            'email' => $data['email'],
            'passwordHash' => $this->userModel->hashPassword($data['password']),
            'fullName' => $data['fullName'],
            'avatarInitials' => $initials,
            'avatarColor' => $colors[array_rand($colors)],
            'roleId' => $data['roleId'],
            'departmentId' => (isset($data['departmentId']) && $data['departmentId'] !== 0 && $data['departmentId'] !== '') ? $data['departmentId'] : null,
            'positionId' => (isset($data['positionId']) && $data['positionId'] !== 0 && $data['positionId'] !== '') ? $data['positionId'] : null,
            'status' => $data['status'] ?? 'active',
            'createdBy' => $createdBy
        ]);

        $newUser = $this->userModel->getUserFullInfo($userId);
        $state = AdminUserLogSnapshot::fromUserInfo(
            is_array($newUser) ? $newUser : [],
            $this->resolveAdminUserLabels(is_array($newUser) ? $newUser : [])
        );
        AccountsOperationLog::logCreateSuccess($input, $state, $userId, $operatorId);

        Response::success($newUser, 'User created successfully', 201);
    }

    /**
     * 更新用户
     * PUT /api/users/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $input = AccountsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        // 获取当前用户ID
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $operatorId = (int) ($payload['userId'] ?? 0);
        $updatedBy = $operatorId;

        if (!is_array($data)) {
            AccountsOperationLog::logFailure(
                $input,
                'edit',
                'adminUserUpdateFailure',
                'Invalid JSON body',
                $id,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        // 验证用户是否存在
        $user = $this->userModel->findById($id);
        if (!$user) {
            AccountsOperationLog::logFailure(
                $input,
                'edit',
                'adminUserUpdateFailure',
                'User not found',
                $id,
                $operatorId
            );
            Response::notFound('User not found');
        }

        $beforeInfo = $this->userModel->getUserFullInfo($id);
        $beforeLabels = $this->resolveAdminUserLabels(is_array($beforeInfo) ? $beforeInfo : []);
        $beforeState = AdminUserLogSnapshot::fromUserInfo(
            is_array($beforeInfo) ? $beforeInfo : [],
            $beforeLabels
        );

        // 构建更新数据
        $updateData = [];

        if (isset($data['fullName'])) {
            $updateData['fullName'] = $data['fullName'];

            // 更新头像缩写
            $nameParts = explode(' ', $data['fullName']);
            $updateData['avatarInitials'] = strtoupper(
                substr($nameParts[0], 0, 1) .
                substr($nameParts[count($nameParts) - 1] ?? '', 0, 1)
            );
        }

        if (isset($data['email'])) {
            // 检查邮箱是否已被使用
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing && $existing['id'] != $id) {
                AccountsOperationLog::logFailure(
                    $input,
                    'edit',
                    'adminUserUpdateFailure',
                    'Email already in use',
                    $id,
                    $operatorId
                );
                Response::error('Email already in use', 400);
            }
            $updateData['email'] = $data['email'];
        }

        if (isset($data['roleId'])) {
            $updateData['roleId'] = $data['roleId'];
        }

        if (isset($data['departmentId'])) {
            // 如果为0、空字符串或null，转换为null；否则使用原值
            $updateData['departmentId'] = ($data['departmentId'] === 0 || $data['departmentId'] === '' || $data['departmentId'] === null) ? null : $data['departmentId'];
        }

        if (isset($data['positionId'])) {
            // 如果为0、空字符串或null，转换为null；否则使用原值
            $updateData['positionId'] = ($data['positionId'] === 0 || $data['positionId'] === '' || $data['positionId'] === null) ? null : $data['positionId'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        // 处理密码更新
        $passwordChanged = false;
        if (isset($data['password']) && !empty($data['password'])) {
            // 验证密码长度
            if (strlen($data['password']) < 8) {
                AccountsOperationLog::logFailure(
                    $input,
                    'edit',
                    'adminUserUpdateFailure',
                    'Password must be at least 8 characters',
                    $id,
                    $operatorId
                );
                Response::error('Password must be at least 8 characters', 400);
            }
            $passwordChanged = true;
            $updateData['passwordHash'] = $this->userModel->hashPassword($data['password']);
            $updateData['passwordChangedAt'] = date('Y-m-d H:i:s');
        }

        $updateData['updatedBy'] = $updatedBy;

        // 更新用户
        $this->userModel->update($id, $updateData);

        $updatedUser = $this->userModel->getUserFullInfo($id);
        $afterLabels = $this->resolveAdminUserLabels(is_array($updatedUser) ? $updatedUser : []);
        $afterState = AdminUserLogSnapshot::fromUserInfo(
            is_array($updatedUser) ? $updatedUser : [],
            $afterLabels,
            $passwordChanged
        );
        AccountsOperationLog::logUpdateSuccess($input, $beforeState, $afterState, $id, $operatorId);

        Response::success($updatedUser, 'User updated successfully');
    }

    /**
     * 删除用户（软删除）
     * DELETE /api/users/{id}
     */
    public function delete($id) {
        $input = AccountsOperationLog::inputFromRequest();

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $operatorId = (int) ($payload['userId'] ?? 0);

        $user = $this->userModel->findById($id);

        if (!$user) {
            AccountsOperationLog::logFailure(
                $input,
                'delete',
                'adminUserDeleteFailure',
                'User not found',
                $id,
                $operatorId
            );
            Response::notFound('User not found');
        }
        // 软删除
        $userInfo = $this->userModel->getUserFullInfo($id);
        $state = AdminUserLogSnapshot::fromUserInfo(
            is_array($userInfo) ? $userInfo : [],
            $this->resolveAdminUserLabels(is_array($userInfo) ? $userInfo : [])
        );

        $this->userModel->softDelete($id);

        AccountsOperationLog::logDeleteSuccess($input, $state, $id, $operatorId);

        Response::success(null, 'User deleted successfully');
    }

    /**
     * 锁定/解锁用户
     * POST /api/users/{id}/toggle-lock
     */
    public function toggleLock($id) {
        $user = $this->userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $isLocked = $user['isLocked'];

        if ($isLocked) {
            $this->userModel->unlockAccount($id);
            $message = 'User unlocked successfully';
        } else {
            $this->userModel->lockAccount($id);
            $message = 'User locked successfully';
        }

        Response::success(['isLocked' => !$isLocked], $message);
    }

    /**
     * 重置用户密码
     * POST /api/users/{id}/reset-password
     */
    public function resetPassword($id) {
        $user = $this->userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'newPassword' => 'required|min:8'
        ]);

        // 更新密码
        $newPasswordHash = $this->userModel->hashPassword($data['newPassword']);
        $this->userModel->update($id, [
            'passwordHash' => $newPasswordHash,
            'passwordChangedAt' => date('Y-m-d H:i:s'),
            'mustChangePassword' => $data['mustChangePassword'] ?? 1
        ]);

        Response::success(null, 'Password reset successfully');
    }

    /**
     * 更新用户资料
     * POST /api/users/{id}/profile
     */
    public function updateProfile($id) {
        // 验证用户只能更新自己的资料，或者是管理员
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $currentUserId = $payload['userId'];

        if ($currentUserId != $id) {
            // TODO: 检查是否有管理员权限
            Response::forbidden('You can only update your own profile');
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // 更新主要用户信息
        $updateData = [];

        if (isset($data['fullName'])) {
            $updateData['fullName'] = $data['fullName'];

            // 更新头像缩写
            $nameParts = explode(' ', $data['fullName']);
            $updateData['avatarInitials'] = strtoupper(
                substr($nameParts[0], 0, 1) .
                substr($nameParts[count($nameParts) - 1] ?? '', 0, 1)
            );
        }

        if (isset($data['email'])) {
            // 获取当前用户信息
            $currentUser = $this->userModel->findById($id);

            // 如果邮箱已更改，需要验证验证码
            if ($currentUser && $data['email'] !== $currentUser['email']) {
                // 检查是否提供了验证码
                if (!isset($data['emailVerificationCode']) || empty($data['emailVerificationCode'])) {
                    Response::error('Email verification code is required when changing email address.', 400);
                }

                // 验证验证码
                require_once __DIR__ . '/../models/EmailOtpVerification.php';
                $emailOtpModel = new EmailOtpVerification();

                // 确定用户类型（管理员）
                $userType = 'admin';

                // 验证OTP
                $verifyResult = $emailOtpModel->verifyOTP($id, $userType, $data['emailVerificationCode'], $data['email']);

                if (!$verifyResult['success']) {
                    Response::error($verifyResult['error'] ?? 'Invalid verification code', 400);
                }
            }

            // 检查邮箱是否已被其他用户使用
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing && $existing['id'] != $id) {
                Response::error('Email already in use', 400);
            }
            $updateData['email'] = $data['email'];
        }

        // 处理 departmentId（更新到 adminUsers 表）
        if (isset($data['departmentId'])) {
            $departmentId = $data['departmentId'];
            // 如果 departmentId 为 0 或空，设置为 null
            if ($departmentId === 0 || $departmentId === '' || $departmentId === null) {
                $updateData['departmentId'] = null;
            } else {
                $updateData['departmentId'] = (int)$departmentId;
            }
        }

        // 更新用户基本信息
        if (!empty($updateData)) {
            $this->userModel->update($id, $updateData);
        }

        // 更新扩展资料（phone, phoneCountryCode, department）
        $profileData = [];
        $errors = [];

        // 验证必填字段：phone 和 phoneCountryCode
        // 如果字段在请求中，必须提供非空值
        if (array_key_exists('phone', $data)) {
            $phone = is_string($data['phone']) ? trim($data['phone']) : $data['phone'];
            if ($phone === null || $phone === '') {
                $errors['phone'] = 'Phone is required';
            } else {
                // 验证长度
                if (strlen($phone) > 30) {
                    $errors['phone'] = 'Phone must not exceed 30 characters';
                } else {
                    $profileData['phone'] = $phone;
                }
            }
        }

        if (array_key_exists('phoneCountryCode', $data)) {
            $phoneCountryCode = is_string($data['phoneCountryCode']) ? trim($data['phoneCountryCode']) : $data['phoneCountryCode'];
            if ($phoneCountryCode === null || $phoneCountryCode === '') {
                $errors['phoneCountryCode'] = 'Phone country code is required';
            } else {
                $profileData['phoneCountryCode'] = $phoneCountryCode;
            }
        }

        // 如果有验证错误，返回错误信息
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // 处理其他可选字段（不再处理 department，因为已经更新到 adminUsers.departmentId）
        if (isset($data['timezone'])) {
            $profileData['timezone'] = $data['timezone'];
        }

        if (isset($data['language'])) {
            $profileData['language'] = $data['language'];
        }

        // 更新或创建用户资料
        if (!empty($profileData)) {
            $this->profileModel->updateOrCreate($id, $profileData);
        }

        // 获取更新后的完整用户信息
        $updatedUser = $this->userModel->getUserFullInfo($id);

        Response::success($updatedUser, 'Profile updated successfully');
    }

    /**
     * 获取部门列表
     * GET /api/users/getdepartments
     */
    public function getDepartments() {
        $departments = $this->departmentModel->getActiveDepartments();
        Response::success($departments);
    }

    /**
     * 获取职位列表
     * GET /api/users/getpositions
     */
    public function getPositions() {
        $positions = $this->positionModel->getActivePositions();
        Response::success($positions);
    }

    /**
     * @param array<string,mixed> $userInfo
     * @return array{roleLabelZh:string,roleLabelEn:string,departmentName:string,positionName:string}
     */
    private function resolveAdminUserLabels(array $userInfo) {
        $roleLabelZh = trim((string) ($userInfo['roleDisplayName'] ?? ''));
        if ($roleLabelZh === '') {
            $roleLabelZh = trim((string) ($userInfo['roleName'] ?? ''));
        }
        $roleLabelEn = trim((string) ($userInfo['roleName'] ?? ''));
        if ($roleLabelEn === '') {
            $roleLabelEn = trim((string) ($userInfo['roleDisplayName'] ?? ''));
        }

        $departmentName = '';
        $departmentId = $userInfo['departmentId'] ?? null;
        if ($departmentId !== null && $departmentId !== '' && $departmentId !== 0 && $departmentId !== '0') {
            $department = $this->departmentModel->findById((int) $departmentId);
            $departmentName = trim((string) ($department['name'] ?? ''));
        }

        $positionName = '';
        $positionId = $userInfo['positionId'] ?? null;
        if ($positionId !== null && $positionId !== '' && $positionId !== 0 && $positionId !== '0') {
            $position = $this->positionModel->findById((int) $positionId);
            $positionName = trim((string) ($position['name'] ?? ''));
        }

        return [
            'roleLabelZh' => $roleLabelZh,
            'roleLabelEn' => $roleLabelEn,
            'departmentName' => $departmentName,
            'positionName' => $positionName,
        ];
    }
}
