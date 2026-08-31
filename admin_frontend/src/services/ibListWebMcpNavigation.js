export const applyIbListWebMcpNavigation = async ({
  query = {},
  setSearch,
  loadList,
  hasRow,
  expand,
}) => {
  const search =
    query.search != null && String(query.search).trim() !== ""
      ? String(query.search)
      : null;
  const parsedDetailId =
    query.detailId != null && /^\d+$/.test(String(query.detailId))
      ? Number(query.detailId)
      : null;
  const detailId =
    Number.isSafeInteger(parsedDetailId) && parsedDetailId > 0
      ? parsedDetailId
      : null;

  if (search !== null) {
    setSearch(search);
  }
  await loadList();
  if (detailId !== null && hasRow(detailId)) {
    expand(detailId);
  }

  return { search, detailId };
};
