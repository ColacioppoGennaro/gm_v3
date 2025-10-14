/**
 * assets/js/api.js — VERSIONE COMPLETA CORRETTA
 */
export async function api(url, body = null, method = null) {
  const opts = { method: method || (body ? 'POST' : 'GET'), credentials: 'same-origin', headers: {} };
  if (body) {
    if (body instanceof FormData) opts.body = body; 
    else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  }
  const res = await fetch(url, opts);
  if (res.status === 401) { 
    window.S.view = 'login'; 
    window.S.user = null; 
    localStorage.clear(); 
    window.renderApp?.(); 
    throw new Error('AUTH_EXPIRED'); 
  }
  const ct = res.headers.get('content-type') || '';
  if (!res.ok) { 
    let msg = `HTTP ${res.status}`; 
    try { 
      msg = ct.includes('json') ? (await res.json()).message || (await res.json()).error || msg : (await res.text()) || msg; 
    } catch(_){} 
    const err = new Error(msg); 
    err.status = res.status; 
    throw err; 
  }
  return ct.includes('application/json') ? await res.json() : await res.text();
}

function objToFormData(obj = {}) { 
  const fd = new FormData(); 
  Object.entries(obj).forEach(([k,v])=>{ 
    if (v==null) return; 
    if (typeof v==='object' && !(v instanceof Blob) && !(v instanceof File)) fd.append(k, JSON.stringify(v)); 
    else fd.append(k,v);
  }); 
  return fd; 
}

export const API = {
  // ==================== AUTH ====================
  login: (email, password) => api('api/auth.php?a=login', { email, password }),
  register: (email, password) => api('api/auth.php?a=register', { email, password }),
  logout: () => api('api/auth.php?a=logout', {}),
  checkSession: () => api('api/auth.php?a=status'),
  
  // ==================== DOCUMENTS ====================
  listDocs: () => api('api/documents.php?a=list'),
  uploadDoc: (file, category) => {
    const fd = new FormData();
    fd.append('file', file);
    if (category) fd.append('category', category);
    return api('api/documents.php?a=upload', fd);
  },
  deleteDoc: (id) => api('api/documents.php?a=delete', { id }),
  ocrDoc: (id) => api('api/documents.php?a=ocr', { id }),
  changeDocCategory: (id, category) => api('api/documents.php?a=change_category', { id, category }),
  
  // ==================== CATEGORIES ====================
  listCategories: () => api('api/categories.php?a=list'),
  createCategory: (name) => api('api/categories.php?a=create', { name }),
  deleteCategory: (id) => api('api/categories.php?a=delete', { id }),
  
  // ==================== CHAT ====================
  askDocs: (question, category, adherence = 'balanced', showRefs = true) => 
    api('api/chat.php', { q: question, category, mode: 'docs', adherence, show_refs: showRefs }),
  askAI: (question, context = null) => 
    api('api/chat.php', { q: question, mode: 'ai', context }),
  
  // ==================== ACCOUNT ====================
  loadAccountInfo: () => api('api/account.php?a=info'),
  upgradePro: (code) => api('api/upgrade.php', { code }),
  downgrade: () => api('api/account.php?a=downgrade', {}),
  deleteAccount: () => api('api/account.php?a=delete', {}),
  
  // ==================== STATS ====================
  loadStats: () => api('api/stats.php'),
  
  // ==================== GOOGLE CALENDAR ====================
  listGoogleEvents: (calendarId, start, end) => 
    api(`api/google/events.php?calendarId=${calendarId}&start=${start}&end=${end}`),
  
  createGoogleEvent: (calendarId, formData) => {
    formData.append('calendarId', calendarId);
    return api(`api/google/events.php?calendarId=${calendarId}`, formData);
  },
  
  updateGoogleEvent: (calendarId, eventId, formData) => {
    formData.append('_method', 'PATCH');
    formData.append('calendarId', calendarId);
    return api(`api/google/events.php?calendarId=${calendarId}&id=${eventId}`, formData);
  },
  
  deleteGoogleEvent: (calendarId, eventId) => {
    const fd = new FormData();
    fd.append('_method', 'DELETE');
    fd.append('calendarId', calendarId);
    return api(`api/google/events.php?calendarId=${calendarId}&id=${eventId}`, fd);
  },
  
  // ==================== DASHBOARD EVENTS ====================
  getDashboardEvents: (filters = {}) => {
    const p = new URLSearchParams();
    Object.entries(filters||{}).forEach(([k,v])=>{ 
      if(v!==null&&v!==undefined&&v!==''&&v!=='null'&&v!=='undefined') p.append(k,v); 
    });
    const qs = p.toString();
    return api(`api/dashboard/events.php${qs ? '?' + qs : ''}`);
  },

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
