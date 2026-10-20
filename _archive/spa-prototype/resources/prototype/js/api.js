/* Laravel-backed data layer. Replaces the prototype's localStorage store:
   boot loads the authorized snapshot from /api/bootstrap, mutations go through
   JSON endpoints, and the server is authoritative for workflow and validation. */
let loginAccounts = [];
let pendingRecord = null;
let saveTimer = null;

const csrfToken = () => {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
};
const apiBase = () => String((window.__EIPCR__ && window.__EIPCR__.base) || '').replace(/\/+$/, '');

function onSessionLost() {
  user = null;
  db = null;
  pendingRecord = null;
  clearTimeout(saveTimer);
  if (location.hash === '#login') render();
  else location.hash = 'login';
}

async function api(method, path, body) {
  let response;
  try {
    response = await fetch(apiBase() + path, {
      method,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (e) {
    throw new Error('Cannot reach the server. Check your connection and try again.');
  }

  let data = null;
  try { data = await response.json(); } catch (e) { data = null; }

  if (response.status === 401) {
    const hadSession = !!user;
    onSessionLost();
    if (hadSession) toast('Your session has ended. Please sign in again.');
    throw new Error((data && data.message) || 'Your session has ended. Please sign in again.');
  }

  if (!response.ok) {
    throw new Error((data && data.message) || 'The request could not be completed.');
  }

  return data;
}

async function fetchLoginAccounts() {
  try {
    loginAccounts = await api('GET', '/api/login-accounts');
  } catch (e) {
    loginAccounts = [];
  }
}

/** Initial page load: public account list, then the authorized snapshot. */
async function bootApp() {
  await fetchLoginAccounts();
  try {
    db = await api('GET', '/api/bootstrap');
    user = db.user;
    render();
  } catch (e) {
    if (!user) {
      if (location.hash === '#login') render();
      else location.hash = 'login';
    }
  }
}

async function refreshDb() {
  const data = await api('GET', '/api/bootstrap');
  db = data;
  user = data.user;
  return data;
}

function recordPayload(r) {
  return {
    name: r.name,
    position: r.position,
    period: r.period,
    commitment: r.commitment,
    development: r.development,
    rateeDate: r.rateeDate || '',
    discussionDate: r.discussionDate || '',
    mfo: r.mfo || null,
    rows: r.rows,
  };
}

/** Debounced editor autosave (prototype persist() replacement). */
function scheduleSave(r) {
  pendingRecord = r;
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => { flushSave(); }, 700);
}

async function flushSave() {
  clearTimeout(saveTimer);
  const r = pendingRecord;
  if (!r) return true;
  pendingRecord = null;

  try {
    const data = await api('PUT', '/api/records/' + encodeURIComponent(r.id), recordPayload(r));
    if (data && data.record) r.updated = data.record.updated;
    const status = $('#save-status');
    if (status) status.textContent = 'Saved just now';
    return true;
  } catch (e) {
    const status = $('#save-status');
    if (status) status.textContent = 'Not saved';
    toast(e.message, 'error');
    return false;
  }
}

/** Fire-and-forget flush when the tab is hidden or closing. */
function flushSaveBeacon() {
  clearTimeout(saveTimer);
  const r = pendingRecord;
  if (!r) return;
  pendingRecord = null;
  try {
    fetch(apiBase() + '/api/records/' + encodeURIComponent(r.id), {
      method: 'PUT',
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(recordPayload(r)),
    }).catch(() => {});
  } catch (e) { /* best effort */ }
}

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') flushSaveBeacon();
});
window.addEventListener('beforeunload', flushSaveBeacon);

/** Prototype newRecord(): server creates the Draft, then we navigate to it. */
async function createRecord() {
  const assigned = db.periods.filter(p => user.periodIds && user.periodIds.includes(p.id));
  if (!assigned.length) {
    shell(heading('No evaluation period assigned', 'Ask the administrator to assign an evaluation period before creating an IPCR.') + link('My IPCRs', 'records'));
    return null;
  }

  try {
    const data = await api('POST', '/api/records', { period: (assigned.find(p => p.active) || assigned[0]).id });
    db.records.push(data.record);
    currentEdit = data.record.id;
    location.hash = 'edit/Strategic/' + data.record.id;
    return data.record;
  } catch (e) {
    toast(e.message, 'error');
    return null;
  }
}

/** Prototype transition(): submit / approve / return on the server. */
async function apiTransition(r, action, comment = '') {
  const data = await api('POST', '/api/records/' + encodeURIComponent(r.id) + '/transition', { action, comment });
  const index = db.records.findIndex(x => x.id === r.id);
  if (index >= 0) db.records[index] = data.record;
  else db.records.push(data.record);
  db.notifications = data.notifications;
  return data.record;
}
