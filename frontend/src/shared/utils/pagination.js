export const getPagedFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return Math.max(1, parseInt(params.get('paged') || '1', 10));
};

export const updatePageInUrl = (page) => {
  const params = new URLSearchParams(window.location.search);
  params.set('paged', String(page));
  window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
};
