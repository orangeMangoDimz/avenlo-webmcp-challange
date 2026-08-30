<?php
/**
 * Country Controller
 * 负责国家数据管理
 */

require_once __DIR__ . '/../models/Country.php';
require_once __DIR__ . '/../models/Countrylist.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class CountryController {
    private $countryModel;

    public function __construct() {
        $this->countryModel = new Country();
    }

    /**
     * 获取启用的国家列表
     * GET /api/countries
     */
    public function list($includeAll = true) {
        $countrylist = new Countrylist();
        $countries = $countrylist->getActiveCountries($includeAll);
        Response::success($countries);
    }

    /**
     * 获取所有国家列表
     * GET /api/transaction-settings/countries
     */
    public function index() {
        try {
            $region = $_GET['region'] ?? null;
            $grouped = isset($_GET['grouped']) && $_GET['grouped'] === 'true';

            if ($grouped) {
                $countries = $this->countryModel->getCountriesByRegion();
            } else {
                $countries = $this->countryModel->getActiveCountries($region);
            }

            Response::success(['data' => $countries]);
        } catch (Exception $e) {
            // Logger::error('Failed to get countries', ['error' => $e->getMessage()]);
            Response::error('Failed to load countries', 500);
        }
    }

    /**
     * 获取国家列表（从 countryList 表，专供 Transaction Settings 入金自动审核等多选用）
     * GET /api/transaction-settings/countries/list-from-country-list
     */
    public function listFromCountryList() {
        try {
            $countrylist = new Countrylist();
            $rows = $countrylist->getActiveCountries(true);
            $countries = array_map(function ($row) {
                return ['code' => $row['code'], 'name' => $row['name']];
            }, $rows);
            $hasAll = false;
            foreach ($countries as $c) {
                if (isset($c['code']) && $c['code'] === 'ALL') {
                    $hasAll = true;
                    break;
                }
            }
            if (!$hasAll) {
                array_unshift($countries, ['code' => 'ALL', 'name' => 'All Countries']);
            }
            Response::success(['data' => $countries]);
        } catch (Exception $e) {
            Response::error('Failed to load countries', 500);
        }
    }

    /**
     * 获取区域列表
     * GET /api/transaction-settings/countries/regions
     */
    public function regions() {
        try {
            $regions = $this->countryModel->getRegions();
            Response::success(['data' => $regions]);
        } catch (Exception $e) {
            // Logger::error('Failed to get regions', ['error' => $e->getMessage()]);
            Response::error('Failed to load regions', 500);
        }
    }

    /**
     * 获取单个国家详情
     * GET /api/transaction-settings/countries/{code}
     */
    public function show($code) {
        try {
            $country = $this->countryModel->findByCode($code);

            if (!$country) {
                Response::notFound('Country not found');
                return;
            }

            Response::success(['data' => $country]);
        } catch (Exception $e) {
            // Logger::error('Failed to get country', [
            //     'code' => $code,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to load country', 500);
        }
    }

    /**
     * 验证国家代码
     * POST /api/transaction-settings/countries/validate
     *
     * Body: {
     *   "codes": ["US", "GB", "CA"]
     * }
     */
    public function validate() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['codes']) || !is_array($data['codes'])) {
                Response::badRequest('codes array is required');
                return;
            }

            $result = $this->countryModel->validateCountryCodes($data['codes']);

            Response::success(['data' => $result]);
        } catch (Exception $e) {
            // Logger::error('Failed to validate country codes', ['error' => $e->getMessage()]);
            Response::error('Failed to validate country codes', 500);
        }
    }
}
