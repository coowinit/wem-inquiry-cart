/* WEM Inquiry Cart — front-end behavior. Real state always comes from WordPress. */
(function () {
  'use strict';
  if (typeof wemIC === 'undefined') return;

  const all = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
  const setNotice = (message, parent) => {
    const target = parent && parent.querySelector('[data-wic-notice], .wic-add-status');
    if (target) target.textContent = message || '';
  };
  // Cart-page notices survive item-list re-rendering and clear after four seconds.
  const cartNoticeTimers = new WeakMap();
  function showCartNotice(message, parent, kind = 'success') {
    const target = parent && parent.querySelector('[data-wic-notice]');
    if (!target) return;
    if (cartNoticeTimers.has(target)) clearTimeout(cartNoticeTimers.get(target));
    target.textContent = message || '';
    target.classList.toggle('is-success', !!message && kind === 'success');
    target.classList.toggle('is-error', !!message && kind === 'error');
    if (message) {
      cartNoticeTimers.set(target, setTimeout(() => {
        target.textContent = '';
        target.classList.remove('is-success', 'is-error');
        cartNoticeTimers.delete(target);
      }, 4000));
    } else {
      cartNoticeTimers.delete(target);
    }
  }
  let latestCartHtml = null;
  let pending = 0;

  async function request(action, payload = {}) {
    const params = new URLSearchParams({ action: 'wem_ic_' + action, nonce: wemIC.nonce });
    Object.keys(payload).forEach(key => params.append(key, String(payload[key])));
    const response = await fetch(wemIC.ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(), cache: 'no-store'
    });
    let body;
    try { body = await response.json(); } catch (_) { throw new Error(wemIC.messages.error); }
    if (!body || !body.success) throw new Error((body && body.data && body.data.message) || wemIC.messages.error);
    return body.data;
  }

  function updateViews(data) {
    if (!data) return;
    const count = Number(data.count) || 0;
    all('[data-wic-count]').forEach(el => {
      el.textContent = count;
      el.hidden = count === 0;
    });
    if (typeof data.html === 'string') {
      latestCartHtml = data.html;
      all('[data-wic-items]').forEach(el => { el.innerHTML = latestCartHtml; });
    }
  }

  async function refresh() {
    try { updateViews(await request('state')); }
    catch (error) {
      all('[data-wic-items]').forEach(el => { el.textContent = error.message; });
    }
  }

  async function mutate(action, payload, control, context) {
    if (pending) return;
    pending = 1;
    if (control) control.disabled = true;
    try {
      const data = await request(action, payload);
      updateViews(data);
      if (action === 'update' || action === 'remove') {
        showCartNotice(action === 'update' ? wemIC.messages.updated : wemIC.messages.removed, context);
      } else {
        // The add button itself turns green: do not show duplicate adjacent text.
        setNotice('', context);
      }
      return true;
    } catch (error) {
      if (action === 'update' || action === 'remove') showCartNotice(error.message, context, 'error');
      else setNotice(error.message, context);
      return false;
    } finally {
      pending = 0;
      if (control && control.isConnected) control.disabled = false;
    }
  }

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-wic-add], [data-wic-update], [data-wic-remove]');
    if (!button) return;
    event.preventDefault();

    if (button.hasAttribute('data-wic-add')) {
      const wrap = button.closest('[data-wic-add-wrap]');
      const id = button.getAttribute('data-wic-add');
      const qty = button.getAttribute('data-wic-quantity') || '1';
      const success = await mutate('add', { product_id: id, quantity: qty }, button, wrap);
      if (success) {
        const icon = button.querySelector('.wic-add-button__icon img');
        const label = button.querySelector('.wic-add-button__text');
        button.classList.add('is-added');
        if (icon) icon.src = wemIC.iconAdded;
        if (label) label.textContent = wemIC.messages.added;
        clearTimeout(button._wicTimer);
        button._wicTimer = setTimeout(() => {
          button.classList.remove('is-added');
          if (icon) icon.src = wemIC.iconAdd;
          if (label) label.textContent = 'Add to Cart';
          setNotice('', wrap);
        }, 2000);
      }
      return;
    }

    const row = button.closest('[data-wic-row]');
    if (!row) return;
    const id = row.getAttribute('data-wic-row');
    const parent = button.closest('.wic-cart-ui');
    if (button.hasAttribute('data-wic-remove')) {
      await mutate('remove', { product_id: id }, button, parent);
    } else {
      const input = row.querySelector('[data-wic-qty]');
      const qty = input && input.value.trim();
      if (!/^[1-9][0-9]?$/.test(qty || '')) {
        showCartNotice(wemIC.messages.invalid, parent, 'error');
        if (input) input.focus();
        return;
      }
      await mutate('update', { product_id: id, quantity: qty }, button, parent);
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh);
  else refresh();
  // Restore when a page is brought back from the browser's back-forward cache.
  window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
})();
