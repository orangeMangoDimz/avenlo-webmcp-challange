-- All Sub-IBs and Direct Clients under a root IB (full tree).
-- Change @root_ib_id only (e.g. 123 = Tanadate Songprasit).

SET @root_ib_id = 123;

WITH RECURSIVE tree AS (
  SELECT
    CASE WHEN b.isClient = 1 THEN b.childClientId ELSE b.childId END AS member_id,
    CASE WHEN b.isClient = 1 THEN 'Direct Client' ELSE 'Sub-IB' END AS type,
    b.parentId,
    0 AS depth
  FROM ib_partner_bind b
  WHERE b.parentId = @root_ib_id

  UNION ALL

  SELECT
    CASE WHEN b.isClient = 1 THEN b.childClientId ELSE b.childId END,
    CASE WHEN b.isClient = 1 THEN 'Direct Client' ELSE 'Sub-IB' END,
    b.parentId,
    t.depth + 1
  FROM ib_partner_bind b
  JOIN tree t ON b.parentId = t.member_id AND t.type = 'Sub-IB'
)
SELECT
  t.depth,
  t.type,
  t.member_id,
  t.parentId,
  CASE
    WHEN t.type = 'Sub-IB' THEN ip.ibCode
    ELSE CAST(t.member_id AS CHAR)
  END AS code,
  CASE
    WHEN t.type = 'Sub-IB' THEN NULLIF(TRIM(CONCAT_WS(' ', cu_ib.firstName, cu_ib.lastName)), '')
    ELSE NULLIF(TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)), '')
  END AS name,
  CASE
    WHEN t.type = 'Sub-IB' THEN COALESCE(cu_ib.email, ip.contactEmail)
    ELSE cu.email
  END AS email
FROM tree t
LEFT JOIN ibPartners ip ON t.type = 'Sub-IB' AND ip.id = t.member_id
LEFT JOIN clientUsers cu_ib ON t.type = 'Sub-IB' AND cu_ib.id = ip.userId
LEFT JOIN clientUsers cu ON t.type = 'Direct Client' AND cu.id = t.member_id
ORDER BY t.depth, t.type, t.member_id;
