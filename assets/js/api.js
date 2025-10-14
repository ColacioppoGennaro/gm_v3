/**
 * assets/js/api.js
 * Gestione unificata di tutte le chiamate API
 */

/**
 * Funzione API unificata con gestione errori e auth
 * @param {string} url - URL endpoint API
 * @param {Object|FormData|null} body - Dati da inviare (null per GET)
 * @param {string|null} method - Metodo HTTP (se null viene dedotto: GET senza body, POST con body)
 * @returns {Promise<any>} - Risposta JSON o testo
 */
export async function api(url, body = null, method = null) {
  const opts = {
    method: method || (body ? 'POST' : 'GET'),
    credentials: 'same-origin',
    headers: {}
  };

  if (body) {
    if (body instanceof FormData) {
      opts.body = body;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
  }

  try {
    const res = await fetch(url, opts);

    // Sessione scaduta
    if (res.status === 401) {
      console.warn('⚠️ Sessione scaduta - redirect al login');
      window.S.view = 'login';
      window.S.user = null;
      localStorage.clear();
      window.renderApp?.();
      throw new Error('AUTH_EXPIRED');
    }

    const ct = res.headers.get('content-type') || '';

    if (!res.ok) {
      let errMsg = `HTTP ${res.status}`;
      try {
        if (ct.includes('application/json')) {
          const errJson = await res.json();
          errMsg = errJson.message || errJson.error || errMsg;
        } else {
          const errText = await res.text();
          errMsg = errText || errMsg;
        }
      } catch (_e) { /* ignore parse errors */ }

      const err = new Error(errMsg);
      err.status = res.status;
      throw err;
    }

    return ct.includes('application/json') ? await res.json() : await res.text();

  } catch (e) {
    if (e.message !== 'AUTH_EXPIRED') {
      console.error('❌ API Error:', url, e);
      if (e.status !== 401) {
        alert('Errore di connessione: ' + e.message);
      }
    }
    throw e;
  }
}

/** Helper: oggetto -> FormData */
function objToFormData(obj = {}) {
  const fd = new FormData();
  Object.entries(obj).forEach(([k, v]) => {
    if (v === undefined || v === null) return;
    if (typeof v === 'object' && !(v instanceof Blob) && !(v instanceof File)) {
      fd.append(k, JSON.stringify(v));
    } else {
      fd.append(k, v);
    }
  });
  return fd;
}

/**
 * Helper per chiamate API specifiche
 */
export const API = {
  // ===== AUTH =====
  login: (email, password) => {
    const fd = new FormData();
    fd.append('email', email);
    fd.append('password', password);
    return api('api/auth.php?a=login', fd);
  },

  register: (email, password) => {
    const fd = new FormData();
    fd.append('email', email);
    fd.append('password', password);
    return api('api/auth.php?a=register', fd);
  },

  logout: () => api('api/auth.php?a=logout', {}),

  checkSession: () => api('api/auth.php?a=status'),

  // ===== DOCUMENTS =====
  listDocs: () => api('api/documents.php?a=list'),

  uploadDoc: (file, category = null) => {
    const fd = new FormData();
    fd.append('file', file);
    if (category) fd.append('category', category);
    return api('api/documents.php?a=upload', fd);
  },

  deleteDoc: (docId) => {
    const fd = new FormData();
    fd.append('id', docId);
    return api('api/documents.php?a=delete', fd);
  },

  ocrDoc: (docId) => {
    const fd = new FormData();
    fd.append('id', docId);
    return api('api/documents.php?a=ocr', fd);
  },

  changeDocCategory: (docId, category) => {
    const fd = new FormData();
    fd.append('id', docId);
    fd.append('category', category);
    return api('api/documents.php?a=change_category', fd);
  },

  // ===== CATEGORIES =====
  listCategories: () => api('api/categories.php?a=list'),

  createCategory: (name) => {
    const fd = new FormData();
    fd.append('name', name);
    return api('api/categories.php?a=create', fd);
  },

  deleteCategory: (id) => {
    const fd = new FormData();
    fd.append('id', id);
    return api('api/categories.php?a=delete', fd);
  },

  // ===== CHAT =====
  askDocs: (question, category, adherence = 'balanced', showRefs = true) => {
    const fd = new FormData();
    fd.append('q', question);
    fd.append('category', category || '');
    fd.append('mode', 'docs');
    fd.append('adherence', adherence);
    fd.append('show_refs', showRefs ? '1' : '0');
    return api('api/chat.php', fd);
  },

  askAI: (question, context = null) => {
    const finalQuestion = context
      ? `Contesto: ${context}\n\nDomanda: ${question || 'Continua'}`
      : question;
    const fd = new FormData();
    fd.append('q', finalQuestion);
    fd.append('mode', 'ai');
    return api('api/chat.php', fd);
  },

  // ===== CALENDAR (locale) =====
  listEvents: (start, end) =>
    api(`api/calendar.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`),

  createEvent: (eventData) =>
    api('api/calendar.php', eventData, 'POST'),

  updateEvent: (id, eventData) =>
    api(`api/calendar.php?id=${encodeURIComponent(id)}`, eventData, 'PATCH'),

  deleteEvent: (id) =>
    api(`api/calendar.php?id=${encodeURIComponent(id)}`, null, 'DELETE'),

  // ===== GOOGLE CALENDAR (bridge PHP) =====
  // >>> Il backend vuole calendarId (e id) in QUERYSTRING, non nel body <<<
  listGoogleEvents: (calendarId, start, end) =>
    api(`api/google/events.php?calendarId=${encodeURIComponent(calendarId)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`),

  // CREATE: POST con FormData, calendarId in querystring
  createGoogleEvent: (calendarId, data) => {
    const fd = data instanceof FormData ? data : objToFormData(data);
    const url = `api/google/events.php?calendarId=${encodeURIComponent(calendarId)}`;
    return api(url, fd, 'POST');
  },

  // UPDATE: usare POST (non PATCH) + id in querystring
  updateGoogleEvent: (calendarId, eventId, data) => {
    const fd = data instanceof FormData ? data : objToFormData(data);
    const url = `api/google/events.php?calendarId=${encodeURIComponent(calendarId)}&id=${encodeURIComponent(eventId)}`;
    return api(url, fd, 'POST');
  },

  // DELETE: alcuni hosting non supportano DELETE -> usa POST con _method=DELETE
  deleteGoogleEvent: (calendarId, eventId) => {
    const fd = new FormData();
    fd.append('_method', 'DELETE');
    const url = `api/google/events.php?calendarId=${encodeURIComponent(calendarId)}&id=${encodeURIComponent(eventId)}`;
    return api(url, fd, 'POST');
  },

  // ===== ACCOUNT =====
  loadStats: () => api('api/stats.php'),

  loadAccountInfo: () => api('api/account.php?a=info'),

  upgradePro: (code) => {
    const fd = new FormData();
    fd.append('code', code);
    return api('api/upgrade.php', fd);
  },

  downgrade: () => api('api/account.php?a=downgrade', {}),

  deleteAccount: () => api('api/account.php?a=delete', {}),

  // ===== DASHBOARD EVENTS =====
  getDashboardEvents: (filters = {}) => {
    const params = new URLSearchParams(filters);
    return api(`api/dashboard/events.php?${params}`);
  }
};
