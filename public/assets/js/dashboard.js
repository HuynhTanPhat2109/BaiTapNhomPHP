/* Progressive enhancement of the GET filter form. All data/scope decisions stay on the server. */
(() => {
  'use strict';
  const form = document.querySelector('[data-dashboard-filters]');
  if (!form) return;
  const period = form.querySelector('[name="period"]');
  const submit = form.querySelector('button[type="submit"]');
  const submitLabel = submit.querySelector('[data-submit-label]');
  for (const input of form.querySelectorAll('input[type="date"]')) input.addEventListener('change', () => { period.value = 'custom'; });
  const company = form.querySelector('[name="company_id"]');
  const facility = form.querySelector('[name="facility_id"]');
  const updateFacilities = () => {
    if (!company || !facility) return;
    for (const option of facility.options) {
      const outside = Boolean(company.value && option.dataset.company && option.dataset.company !== company.value);
      option.hidden = outside;
      option.disabled = outside;
      if (outside && option.selected) facility.value = '';
    }
  };
  company?.addEventListener('change', updateFacilities);
  updateFacilities();
  form.addEventListener('submit', () => {
    form.setAttribute('aria-busy', 'true');
    submit.disabled = true;
    submitLabel.textContent = 'Đang tải…';
  });
  window.addEventListener('pageshow', () => {
    form.removeAttribute('aria-busy');
    submit.disabled = false;
    submitLabel.textContent = 'Áp dụng';
  });

  const points = document.querySelectorAll('[data-dashboard-tooltip]');
  if (!points.length) return;
  const tooltip = document.createElement('div');
  tooltip.id = 'dashboard-tooltip';
  tooltip.className = 'dashboard-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.hidden = true;
  document.body.append(tooltip);
  for (const point of points) {
    const hide = () => { tooltip.hidden = true; point.removeAttribute('aria-describedby'); };
    const show = () => {
      tooltip.textContent = point.dataset.dashboardTooltip;
      tooltip.hidden = false;
      point.setAttribute('aria-describedby', tooltip.id);
      const anchor = point.getBoundingClientRect();
      const box = tooltip.getBoundingClientRect();
      tooltip.style.left = `${Math.max(8, Math.min(innerWidth - box.width - 8, anchor.x + anchor.width / 2 - box.width / 2))}px`;
      tooltip.style.top = `${Math.max(8, anchor.top > box.height + 16 ? anchor.top - box.height - 10 : Math.min(innerHeight - box.height - 8, anchor.bottom + 10))}px`;
    };
    point.addEventListener('pointerenter', show);
    point.addEventListener('focus', show);
    point.addEventListener('pointerleave', hide);
    point.addEventListener('blur', hide);
    point.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
  }
  window.addEventListener('scroll', () => { tooltip.hidden = true; }, { passive: true });
  window.addEventListener('resize', () => { tooltip.hidden = true; });
})();
