-- Direct Clients bound directly under one Sub-IB (one tree level).
-- Change @sub_ib_id only (e.g. 124).

SET @sub_ib_id = 124;

SELECT
  b.childClientId AS client_id,
  NULLIF(TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)), '') AS client_name,
  cu.email AS client_email,
  b.parentId AS sub_ib_id,
  ip.ibCode AS sub_ib_code,
  NULLIF(TRIM(CONCAT_WS(' ', cu_ib.firstName, cu_ib.lastName)), '') AS sub_ib_name,
  b.createdAt AS bound_at
FROM ib_partner_bind b
JOIN clientUsers cu ON cu.id = b.childClientId
JOIN ibPartners ip ON ip.id = b.parentId
LEFT JOIN clientUsers cu_ib ON cu_ib.id = ip.userId
WHERE b.parentId = @sub_ib_id
  AND b.isClient = 1
ORDER BY b.createdAt ASC;
