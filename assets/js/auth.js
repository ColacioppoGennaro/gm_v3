/**
 * assets/js/auth.js
 * Gestione autenticazione e sessione
 */

import { API } from './api.js';

const LS_USER_KEY = 'gmv3_user';

/**
 * Effettua il login
 */
export async function doLogin() {
  const email = document.getElementById('email')?.value;
  const password = document.getElementById('password')?.value;
  const err = document.getElementById('loginError');

  err?.classList.add('hidden');

  if (!email || !password) {
    err.textContent = 'Inserisci email e password';
    err?.classList.remove('hidden');
    return;
  }

  try {
    const r = await API.login(email, password);
    
    if (r.success) {
      window.S.user = { email, role: r.role || 'free' };
      localStorage.setItem(LS_USER_KEY, JSON.stringify(window.S.user));
      window.S.view = 'app';
      window.renderApp();
      setTimeout(() => window.route(), 0);
    } else {
      err.textContent = r.message || 'Errore durante il login';
      err?.classList.remove('hidden');
    }
  } catch (e) {
    if (e.message !== 'AUTH_EXPIRED') {
      err.textContent = 'Errore di connessione durante il login.';
      err?.classList.remove('hidden');
    }
  }
}

/**
 * Registrazione nuovo utente
 */
export async function doRegister() {
  const email = document.getElementById('regEmail')?.value;
  const pass = document.getElementById('regPass')?.value;
  const passConfirm = document.getElementById('regPassConfirm')?.value;
  const err = document.getElementById('regError');
  const success = document.getElementById('regSuccess');

  err?.classList.add('hidden');
  success?.classList.add('hidden');

  if (!email || !pass || !passConfirm) {
    err.textContent = 'Compila tutti i campi';
    err?.classList.remove('hidden');
    return;
  }

  if (pass !== passConfirm) {
    err.textContent = 'Le password non coincidono';
    err?.classList.remove('hidden');
    return;
  }

  if (pass.length < 6) {
    err.textContent = 'La password deve essere di almeno 6 caratteri';
    err?.classList.remove('hidden');
    return;
  }

  try {
    const r = await API.register(email, pass);
    
    if (r.success) {
      success.textContent = '✓ Registrazione completata! Ora puoi accedere.';
      success?.classList.remove('hidden');
      setTimeout(() => {
        window.S.view = 'login';
        window.renderApp();
      }, 2000);
    } else {
      err.textContent = r.message || 'Errore durante la registrazione';
      err?.classList.remove('hidden');
    }
  } catch (e) {
    err.textContent = 'Errore di connessione.';
    err?.classList.remove('hidden');
  }
}

/**
 * Logout utente
 */
export async function doLogout() {
  try {
    await API.logout();
  } catch (e) {
    console.error("Logout fallito ma procedo con la pulizia del client", e);
  } finally {
    window.S.user = null;
    window.S.view = 'login';
    localStorage.clear();
    window.renderApp();
  }
}

/**
 * Password dimenticata (placeholder)
 */
export async function doForgot() {
  const email = document.getElementById('forgotEmail')?.value;
  const err = document.getElementById('forgotError');
  const success = document.getElementById('forgotSuccess');

  err?.classList.add('hidden');
  success?.classList.add('hidden');

  if (!email) {
    err.textContent = 'Inserisci la tua email';
    err?.classList.remove('hidden');
    return;
  }

  success.textContent = '✓ Se l\'email esiste, riceverai un link per reimpostare la password.';
  success?.classList.remove('hidden');
}

/**
 * Verifica sessione corrente al caricamento
 */
export async function checkSession() {
  try {
    const stored = localStorage.getItem(LS_USER_KEY);
    if (stored) {
      window.S.user = JSON.parse(stored);
    }
  } catch (e) {
    console.warn('Errore parsing localStorage user');
  }

  try {
    const r = await API.checkSession();
    
    if (r?.success && r.account) {
      window.S.user = { 
        email: r.account.email, 
        role: r.account.role 
      };
      localStorage.setItem(LS_USER_KEY, JSON.stringify(window.S.user));
      return true;
    } else {
      throw new Error('Sessione non valida');
    }
  } catch (e) {
    window.S.user = null;
    localStorage.removeItem(LS_USER_KEY);
    return false;
  }
}

/**
 * Helper per determinare la view iniziale
 */
export function getInitialView() {
  return window.S.user ? 'app' : 'login';
}
