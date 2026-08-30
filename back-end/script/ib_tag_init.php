<?php
/**
 * IB 标签初始化脚本
 *
 * 创建一个 IB 标签（leadTags），并给目前全部 IB 关联的客户打上该标签。
 *
 * 说明：
 *   - IB 在 ibPartners 表，通过 userId 关联 clientUsers.id；
 *     lead 标签系统（leadTags / leadTagAssignments）正是打在 clientUsers 上的，
 *     所以这里直接复用 lead 标签给每个 IB 关联的客户打标签。
 *   - 幂等：标签已存在则复用、不重复创建；打标签用 bulkAssignTag，内部已去重，可重复跑。
 *   - ibPartners.userId 为 NULL 的 IB（没有关联 clientUser）无法打 lead 标签，会跳过并计数。
 *
 * 用法：
 *   php script/ib_tag_init.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../models/LeadTag.php';
require_once __DIR__ . '/../models/LeadTagAssignment.php';
require_once __DIR__ . '/../models/SearchTag.php';

const IB_TAG_NAME = 'IB';
const IB_TAG_COLOR = '#3b82f6';
const IB_TAG_DESCRIPTION = 'Introducing Broker';

// 快捷搜索标签：点击后用该关键词搜索客户，后端会匹配到 leadTags.tagName，从而筛出所有 IB
const IB_SEARCH_TAG_NAME = 'IB';
const IB_SEARCH_KEYWORDS = 'IB';

function out($message) {
    echo $message . PHP_EOL;
}

$db = Database::getInstance();
$leadTag = new LeadTag();
$assignment = new LeadTagAssignment();
$searchTag = new SearchTag();

try {
    $db->beginTransaction();

    out('=== IB Tag Init ===');

    // 1. 创建或复用 IB 标签
    $tag = $leadTag->findByName(IB_TAG_NAME);
    if ($tag) {
        $tagId = $tag['id'];
        out("标签已存在，复用：{$tag['tagName']} (id={$tagId})");
    } else {
        $tagId = $leadTag->create([
            'tagName' => IB_TAG_NAME,
            'tagColor' => IB_TAG_COLOR,
            'description' => IB_TAG_DESCRIPTION,
            'isSystemTag' => 0,
        ]);
        out("已创建标签：" . IB_TAG_NAME . " (id={$tagId})");
    }

    // 2. 取目前全部 IB 关联的客户
    $rows = $db->fetchAll(
        "SELECT DISTINCT userId FROM ibPartners WHERE userId IS NOT NULL"
    );
    $userIds = array_column($rows, 'userId');

    $totalIb = (int) $db->fetchOne("SELECT COUNT(*) AS c FROM ibPartners")['c'];
    $skipped = $totalIb - count($rows);

    out("IB 总数：{$totalIb}，可打标签（有关联客户）：" . count($userIds) . "，跳过（无关联客户）：{$skipped}");

    // 3. 批量打标签（已分配的会自动跳过）
    $assignedCount = $assignment->bulkAssignTag($userIds, $tagId, null);

    out("打标签完成，本次成功处理：{$assignedCount} 个客户");

    // 4. 创建 IB 快捷搜索标签（已存在则跳过）
    if ($searchTag->isTagNameAvailable(IB_SEARCH_TAG_NAME)) {
        $maxOrder = (int) $db->fetchOne("SELECT COALESCE(MAX(displayOrder), 0) AS m FROM searchTags")['m'];
        $searchTag->create([
            'tagName' => IB_SEARCH_TAG_NAME,
            'searchKeywords' => IB_SEARCH_KEYWORDS,
            'displayOrder' => $maxOrder + 1,
            'isActive' => 1,
        ]);
        out("已创建快捷搜索标签：" . IB_SEARCH_TAG_NAME);
    } else {
        out("快捷搜索标签已存在，跳过：" . IB_SEARCH_TAG_NAME);
    }

    $db->commit();

    out('=== Done ===');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    out('出错，已回滚：' . $e->getMessage());
    exit(1);
}
