/**
 * assets/js/api.js — estratto completo con helper feed
 */
export async function api(url, body = null, method = null) {
  const opts = { method: method || (body ? 'POST' : 'GET'), credentials: 'same-origin', headers: {} };
  if (body) {
    if (body instanceof FormData) opts.body = body; else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  }
  const res = await fetch(url, opts);
  if (res.status === 401) { window.S.view = 'login'; window.S.user = null; localStorage.clear(); window.renderApp?.(); throw new Error('AUTH_EXPIRED'); }
  const ct = res.headers.get('content-type') || '';
  if (!res.ok) { let msg = `HTTP ${res.status}`; try { msg = ct.includes('json') ? (await res.json()).message || (await res.json()).error || msg : (await res.text()) || msg; } catch(_){} const err = new Error(msg); err.status = res.status; throw err; }
  return ct.includes('application/json') ? await res.json() : await res.text();
}

function objToFormData(obj = {}) { const fd = new FormData(); Object.entries(obj).forEach(([k,v])=>{ if (v==null) return; if (typeof v==='object' && !(v instanceof Blob) && !(v instanceof File)) fd.append(k, JSON.stringify(v)); else fd.append(k,v);}); return fd; }

export const API = {
  // ... TUTTO IL RESTO INVARIATO ...

  getDashboardEvents: (filters = {}) => {
    const p = new URLSearchParams();
    Object.entries(filters||{}).forEach(([k,v])=>{ if(v!==null&&v!==undefined&&v!==''&&v!=='null'&&v!=='undefined') p.append(k,v); });
    const qs = p.toString();
    return api(`api/dashboard/events.php${qs ? '?' + qs : ''}`);
  },

  // ✅ Nuovo: feed paginato per scroll su/giù
  getDashboardFeed: ({ anchor, dir='up', rangeDays=30, include_done=false, type=null, category=null, limit=20 } = {}) => {
    const p = new URLSearchParams();
    if (anchor) p.append('anchor', anchor);
    p.append('dir', dir);
    p.append('rangeDays', String(rangeDays));
    p.append('limit', String(limit));
    if (include_done) p.append('include_done', '1');
    if (type) p.append('type', type);
    if (category) p.append('category', category);
    return api(`api/dashboard/events.php?${p.toString()}`);
  }
};
