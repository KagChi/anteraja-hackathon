#!/usr/bin/env node
/**
 * Scenario suite for the Anteraja Frozen WhatsApp workflows.
 *
 * The workflows are data, so nothing else can execute their logic. This loads
 * the committed JSON, runs the real node scripts inside a minimal stand-in for
 * the n8n runtime, and asserts the whole reply matrix: every pending state,
 * every accepted reply, and the cases that must NOT act. It also checks the
 * graph wiring, so a re-wire that drops a step fails here instead of in
 * production.
 *
 *   node deploy/n8n/tests/inbound-scenarios.mjs
 *
 * Exit code is non-zero when any scenario fails.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const load = (name) => JSON.parse(readFileSync(resolve(here, '..', 'workflows', name + '.json'), 'utf8'));

const INBOUND = load('anteraja-frozen-inbound');
const OUTBOUND = load('anteraja-frozen-outbound');

const nodeOf = (workflow, name) => {
  const found = workflow.nodes.find((entry) => entry.name === name);
  if (!found) throw new Error('workflow ' + workflow.name + ' has no node "' + name + '"');
  return found;
};

const code = (workflow, name) => nodeOf(workflow, name).parameters.jsCode;

/** Minimal stand-in for the n8n runtime the node scripts expect. */
function run(script, options) {
  const settings = options || {};
  const upstream = settings.nodes || {};
  const input = settings.input || {};
  const today = settings.today || '2026-10-14';

  const $ = (name) => {
    if (!(name in upstream)) throw new Error('node "' + name + '" did not run before this step');
    return { first: () => ({ json: upstream[name] }), item: { json: upstream[name] } };
  };
  const $input = { first: () => ({ json: input }), item: { json: input } };
  // Enough of Luxon for what the nodes use, around a fixed "now" in Jakarta:
  // 2026-10-14 23:30, so every slot today is inside the two-hour lead.
  const jakartaAt = (offset) => {
    const opts = offset || {};
    const day = String(14 + (opts.days || 0)).padStart(2, '0');
    return {
      toFormat: (pattern) => {
        if (pattern === 'yyyyLLddHHmm') {
          return opts.days || opts.hours ? '202610150130' : '202610142330';
        }
        return '2026-10-' + day;
      },
    };
  };
  const $now = { setZone: () => Object.assign(jakartaAt({}), { plus: (opts) => jakartaAt(opts) }) };

  return new Function('$', '$input', '$now', script)($, $input, $now)[0].json;
}

// ---------------------------------------------------------------- assertions

let passed = 0;
let failed = 0;

function scenario(title, body) {
  try {
    body();
    passed += 1;
    console.log('  ok   ' + title);
  } catch (error) {
    failed += 1;
    console.log('  FAIL ' + title);
    console.log('       ' + error.message);
  }
}

function eq(actual, expected, what) {
  if (actual !== expected) {
    throw new Error(what + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual));
  }
}

function contains(haystack, needle) {
  if (!String(haystack).includes(needle)) {
    throw new Error('expected text to contain ' + JSON.stringify(needle) + ', got ' + JSON.stringify(String(haystack).slice(0, 140)));
  }
}

function absent(haystack, needle) {
  if (String(haystack).includes(needle)) {
    throw new Error('expected text NOT to contain ' + JSON.stringify(needle));
  }
}

// ------------------------------------------------------------ inbound runners

const PHONE = '6287865345232';
const RESI = 'AJ-FRZ-0012361';
const MENU = 'angka 1, 2, 3, atau 4';

function normalise(options) {
  const o = options || {};
  const from = o.from || (PHONE + '@c.us');
  const message = o.text === undefined ? 'hai' : o.text;

  return run(code(INBOUND, 'Normalisasi'), {
    nodes: {
      'Webhook WAHA': {
        body: {
          event: 'message',
          payload: {
            from: from,
            fromMe: o.fromMe === true,
            body: message,
            notifyName: null,
            id: o.id === undefined ? null : { _serialized: o.id }
          }
        },
      },
    },
    input: o.pn === undefined ? { pn: null } : { pn: o.pn },
  });
}

function detect(text) {
  const normalised = normalise({ text: text });
  return run(code(INBOUND, 'Deteksi Resi'), { nodes: { Normalisasi: normalised } });
}

function decide(options) {
  const o = options || {};
  const text = o.text === undefined ? 'hai' : o.text;
  const normalised = normalise({ text: text, id: o.id });
  const resi = run(code(INBOUND, 'Deteksi Resi'), { nodes: { Normalisasi: normalised } });

  const context = run(code(INBOUND, 'Siapkan Konteks'), {
    nodes: { Normalisasi: normalised, 'Ambil State': o.state || {}, 'Deteksi Resi': resi },
    input: o.lookup === undefined ? { resi: null } : o.lookup,
  });

  const decision = run(code(INBOUND, 'Putuskan Aksi'), {
    nodes: { 'Siapkan Konteks': context },
    input: { choices: [{ message: { content: JSON.stringify(o.llm || { intent: 'other' }) } }] },
  });

  const upstream = { 'Putuskan Aksi': decision, Normalisasi: normalised };
  if (o.app) upstream['Panggil App'] = o.app;

  const reply = run(code(INBOUND, 'Susun Balasan'), { nodes: upstream });

  return { context: context, decision: decision, reply: reply };
}

const CONTEXT = { window: 'Hari ini, 13:00 - 15:00', client_url: 'https://app/api/client/tok' };
const SCHEDULE_STATE = { phone: PHONE, tracking_number: RESI, client_token: 'tok', last_template: 'schedule_request', context: CONTEXT };
const RESCHEDULED_STATE = { phone: PHONE, tracking_number: RESI, client_token: 'tok', last_template: 'schedule_rescheduled', context: CONTEXT };
const ISSUE_STATE = { phone: PHONE, tracking_number: RESI, client_token: 'tok', last_template: 'issue_options', context: CONTEXT };
const NO_STATE = { phone: PHONE, tracking_number: null, client_token: null, last_template: null, context: null };
const FOUND_REQUEST = {
  found: true,
  tracking_number: RESI,
  client_token: 'tok',
  client_url: 'https://app/api/client/tok',
  template: 'schedule_request',
  status_label: 'Menunggu Konfirmasi',
};

const FOUND = {
  found: true,
  tracking_number: RESI,
  client_token: 'tok',
  client_url: 'https://app/api/client/tok',
  template: 'schedule_request',
  status_label: 'Menunggu Konfirmasi',
};

const APP_OK = { success: true, message: 'Jadwal pengantaran berhasil dikonfirmasi.', data: {} };
const APP_REJECTED = {
  error: { message: '422 - {"success":false,"message":"Jadwal pengantaran tidak dapat diubah atau sudah dikonfirmasi."}' },
};

// ---------------------------------------------------------------- inbound wiring

console.log('Inbound workflow — wiring');

scenario('every step is still chained, lookup included', () => {
  const target = (from, index) => ((INBOUND.connections[from] || {}).main || [])[index || 0][0].node;
  const chain = [
    ['Webhook WAHA', 0, 'Resolusi LID'],
    ['Resolusi LID', 0, 'Normalisasi'],
    ['Normalisasi', 0, 'Pesan Masuk?'],
    ['Pesan Masuk?', 0, 'Catat Pesan Masuk'],
    ['Catat Pesan Masuk', 0, 'Ambil State'],
    ['Ambil State', 0, 'Deteksi Resi'],
    ['Deteksi Resi', 0, 'Ada Resi?'],
    ['Ada Resi?', 0, 'Cari Paket'],
    ['Ada Resi?', 1, 'Siapkan Konteks'],
    ['Cari Paket', 0, 'Siapkan Konteks'],
    ['Siapkan Konteks', 0, 'Klasifikasi LLM'],
    ['Klasifikasi LLM', 0, 'Putuskan Aksi'],
    ['Putuskan Aksi', 0, 'Ada Aksi App?'],
    ['Ada Aksi App?', 0, 'Panggil App'],
    ['Ada Aksi App?', 1, 'Susun Balasan'],
    ['Panggil App', 0, 'Susun Balasan'],
    ['Susun Balasan', 0, 'Balas WAHA'],
    ['Balas WAHA', 0, 'Catat Balasan'],
    ['Catat Balasan', 0, 'Simpan State Pesan'],
  ];

  for (const step of chain) {
    eq(target(step[0], step[1]), step[2], step[0] + ' -> ' + step[2]);
  }
});

scenario('the lookup call carries the shared token', () => {
  const lookup = nodeOf(INBOUND, 'Cari Paket');
  eq(lookup.credentials.httpHeaderAuth.id, 'anterajaAppHook', 'credential');
  contains(lookup.parameters.url, '/api/internal/whatsapp/lookup', 'url');
});

scenario('the reply is addressed by the resolved chat id', () => {
  contains(nodeOf(INBOUND, 'Balas WAHA').parameters.jsonBody, 'chat_id', 'chat id');
});

scenario('the state upsert keeps what it already knew', () => {
  const save = nodeOf(INBOUND, 'Simpan State Pesan');
  contains(save.parameters.query, 'COALESCE(EXCLUDED.tracking_number', 'tracking kept');
  contains(save.parameters.query, 'COALESCE(EXCLUDED.client_token', 'token kept');
  contains(save.parameters.query, 'COALESCE(EXCLUDED.last_template', 'template kept');
  contains(save.parameters.options.queryReplacement, 'values', 'values bound as parameters');
});

// ------------------------------------------------------------- inbound filter

console.log('');
console.log('Inbound — messages that must be ignored');

scenario('groups, status broadcasts, own messages and empty text are filtered', () => {
  eq(normalise({ text: 'hai', from: '120363000000000000@g.us' }).should_process, false, 'group');
  eq(normalise({ text: 'hai', from: 'status@broadcast' }).should_process, false, 'status');
  eq(normalise({ text: 'hai', fromMe: true }).should_process, false, 'fromMe');
  eq(normalise({ text: '   ' }).should_process, false, 'empty');
  eq(normalise({ text: 'hai' }).should_process, true, 'ordinary message');
});

scenario('a LID chat answers the resolved number, and falls back otherwise', () => {
  const lid = normalise({ text: 'hai', from: '104290479829096@lid', pn: '6289636887928@c.us' });
  eq(lid.phone, '6289636887928', 'phone from pn');
  eq(lid.chat_id, '6289636887928@c.us', 'chat id from pn');

  const fallback = normalise({ text: 'hai', from: '628123@c.us' });
  eq(fallback.chat_id, '628123@c.us', 'keeps the address when the lookup has none');
});

scenario('only a resi-shaped token is read as a resi', () => {
  eq(detect('tolong cek AJ-FRZ-0099001 ya').resi, 'AJ-FRZ-0099001', 'embedded');
  eq(detect('aj-frz-0099001').resi, 'AJ-FRZ-0099001', 'lowercase');
  eq(detect('hai').resi, null, 'plain text');
});

// ----------------------------------------------------------- inbound confirm

console.log('');
console.log('Inbound — schedule confirmation');

scenario('"siap menerima" confirms and advances the state', () => {
  const out = decide({ text: 'siap menerima', state: SCHEDULE_STATE });
  eq(out.decision.intent, 'confirm', 'intent');
  contains(out.decision.app_url, '/schedule/confirm', 'app url');
  eq(out.decision.next_template, 'confirmed', 'next template');
  contains(out.reply.text, 'sudah dikonfirmasi', 'reply');
});

scenario('"ya" and "oke" alone also confirm', () => {
  for (const word of ['ya', 'oke', 'bisa', 'setuju']) {
    eq(decide({ text: word, state: SCHEDULE_STATE }).decision.intent, 'confirm', 'intent for ' + word);
  }
});

scenario('"besok" is not misread as the "ok" confirmation', () => {
  eq(decide({ text: 'besok', state: SCHEDULE_STATE }).decision.intent, 'reschedule', 'intent');
});

scenario('"minta jadwal lain" without a window asks for date and time', () => {
  const out = decide({ text: 'minta jadwal lain', state: SCHEDULE_STATE, llm: { intent: 'request_reschedule' } });
  eq(out.decision.intent, 'reschedule', 'intent');
  eq(out.decision.app_url, null, 'no app call yet');
  contains(out.decision.reply_text, '09:00-11:00', 'offers the slots');
});

scenario('"besok 13:00-15:00" reschedules through the API', () => {
  const out = decide({
    text: 'besok 13:00-15:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-15', proposed_slot: '13:00-15:00' },
  });
  eq(out.decision.intent, 'reschedule', 'intent');
  contains(out.decision.app_url, '/schedule/reschedule', 'app url');
  eq(out.decision.app_body.date, '2026-10-15', 'date');
  eq(out.decision.app_body.slot, '13:00-15:00', 'slot');
  eq(out.decision.next_template, 'reschedule_requested', 'next template');
});

scenario('a window outside the offered list is asked for again', () => {
  const out = decide({
    text: 'ubah jadwal',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-15', proposed_slot: '20:00-22:00' },
  });
  eq(out.decision.app_url, null, 'no app call');
  contains(out.reply.text, '09:00-11:00', 'offers the slots');
});

scenario('a bare time means tomorrow, not today', () => {
  const out = decide({
    text: '11:00-13:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: null, proposed_slot: '11:00-13:00' },
  });
  contains(out.decision.app_url, '/schedule/reschedule', 'app call');
  eq(out.decision.app_body.date, '2026-10-15', 'next day');
  eq(out.decision.app_body.slot, '11:00-13:00', 'slot');
  eq(out.decision.next_template, 'reschedule_requested', 'state advanced');
});

scenario('a bare time that still fits today stays today', () => {
  const out = decide({
    text: '17:00-19:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: null, proposed_slot: '09:00-11:00' },
  });
  eq(out.decision.app_body.date, '2026-10-15', 'the stub has no usable slot left today');
});

scenario('"besok" wins over a date the model guessed', () => {
  const out = decide({
    text: 'besok 11:00-13:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-14', proposed_slot: '11:00-13:00' },
  });
  eq(out.decision.app_body.date, '2026-10-15', 'tomorrow');
});

scenario('"lusa" means the day after', () => {
  const out = decide({
    text: 'lusa 11:00-13:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_slot: '11:00-13:00' },
  });
  eq(out.decision.app_body.date, '2026-10-16', 'day after tomorrow');
});

scenario('an explicitly too-soon day is refused, with a way forward', () => {
  const out = decide({
    text: 'hari ini 11:00-13:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-14', proposed_slot: '11:00-13:00' },
  });
  eq(out.decision.app_url, null, 'no app call');
  eq(out.reply.values[3], null, 'state not advanced');
  contains(out.reply.text, 'jadwal besok', 'points at tomorrow');
});

scenario('a window tomorrow still goes through', () => {
  const out = decide({
    text: 'besok 13:00-15:00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-15', proposed_slot: '13:00-15:00' },
  });
  contains(out.decision.app_url, '/schedule/reschedule', 'app call');
  eq(out.decision.next_template, 'reschedule_requested', 'state advanced');
});

scenario('an explicit day and time the model resolved reschedules through the API', () => {
  const out = decide({
    text: 'tanggal 23 jam 13.00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-23', proposed_slot: '13:00-15:00' },
  });
  contains(out.decision.app_url, '/schedule/reschedule', 'app url');
  eq(out.decision.app_body.date, '2026-10-23', 'date from the model');
  eq(out.decision.app_body.slot, '13:00-15:00', 'slot from the model');
  eq(out.decision.next_template, 'reschedule_requested', 'state advanced');
});

scenario('"tanggal 22 jam 17.00" is not rejected for a two-hour lead it never had', () => {
  const out = decide({
    text: 'tanggal 22 jam 17.00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-22', proposed_slot: '17:00-19:00' },
  });
  contains(out.decision.app_url, '/schedule/reschedule', 'app url');
  eq(out.decision.app_body.date, '2026-10-22', 'date');
  eq(out.decision.app_body.slot, '17:00-19:00', 'slot');
});

scenario('the prompt is anchored with today so the model can resolve a bare day', () => {
  const out = decide({ text: 'tanggal 23 jam 13.00', state: SCHEDULE_STATE, llm: { intent: 'request_reschedule' } });
  const system = out.context.llm_body.messages[0].content;
  contains(system, '2026-10-14', 'today is in the prompt');
  contains(system, '2026-10-15', 'tomorrow is in the prompt');
});

scenario('a date the model could not resolve asks for it, never blames the two-hour lead', () => {
  const out = decide({
    text: 'tanggal 23 jam 13.00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: null, proposed_slot: '13:00-15:00' },
  });
  eq(out.decision.app_url, null, 'no app call');
  eq(out.reply.values[3], null, 'state not advanced');
  contains(out.reply.text, '09:00-11:00', 'offers the slots');
  absent(out.reply.text, 'kurang dari 2 jam', 'not the two-hour message');
});

scenario('a date already gone is refused as past, not as too soon', () => {
  const out = decide({
    text: 'tanggal 2 jam 13.00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-10-02', proposed_slot: '13:00-15:00' },
  });
  eq(out.decision.app_url, null, 'no app call');
  contains(out.reply.text, 'sudah lewat', 'named as past');
  absent(out.reply.text, 'kurang dari 2 jam', 'not the two-hour message');
});

scenario('a date past the booking window is refused before the API has to', () => {
  const out = decide({
    text: 'tanggal 30 jam 13.00',
    state: SCHEDULE_STATE,
    llm: { intent: 'request_reschedule', proposed_date: '2026-11-30', proposed_slot: '13:00-15:00' },
  });
  eq(out.decision.app_url, null, 'no app call');
  contains(out.reply.text, '14 hari', 'names the limit');
});

scenario('the rescheduled notice is answered like a first request', () => {
  for (const word of ['siap', 'oke', 'ya']) {
    const out = decide({ text: word, state: RESCHEDULED_STATE });
    eq(out.decision.intent, 'confirm', 'intent for ' + word);
    contains(out.decision.app_url, '/schedule/confirm', 'app url for ' + word);
    eq(out.decision.next_template, 'confirmed', 'state advanced for ' + word);
  }
});

scenario('the rescheduled notice also accepts a new window', () => {
  const out = decide({
    text: 'minta jadwal lain',
    state: RESCHEDULED_STATE,
    llm: { intent: 'request_reschedule' },
  });
  eq(out.decision.intent, 'reschedule', 'intent');
  contains(out.decision.reply_text, '09:00-11:00', 'offers the slots');
});

scenario('an unrelated reply re-asks the rescheduled confirmation with the window', () => {
  const out = decide({ text: 'apa kabar', state: RESCHEDULED_STATE });
  eq(out.decision.intent, 'info:schedule_rescheduled', 'intent');
  contains(out.reply.text, 'Hari ini, 13:00 - 15:00', 'quotes the pending window');
  contains(out.reply.text, 'SIAP', 'offers SIAP');
  absent(out.reply.text, 'Kirimkan nomor resi', 'never the help text');
});

scenario('the same re-ask stands without a stored window', () => {
  const out = decide({ text: 'apa kabar', state: { ...RESCHEDULED_STATE, context: null } });
  contains(out.reply.text, 'masih menunggu konfirmasi jadwal', 'names the pending step');
  absent(out.reply.text, 'konfirmasi jadwal untuk', 'no dangling window phrase');
});

scenario('a resi refreshes a stale stored template instead of falling back to help', () => {
  const out = decide({ text: RESI, state: RESCHEDULED_STATE, lookup: FOUND_REQUEST });
  eq(out.context.last_template, 'schedule_request', 'template refreshed from the app');
  contains(out.reply.text, 'masih menunggu konfirmasi jadwal', 'asks for the confirmation');
  absent(out.reply.text, 'Kirimkan nomor resi', 'never the help text');
});

scenario('an unrelated message re-asks the confirmation, with no issue menu', () => {
  const out = decide({ text: 'apa kabar', state: SCHEDULE_STATE });
  eq(out.decision.intent, 'info:schedule_request', 'intent');
  contains(out.reply.text, 'SIAP', 'offers SIAP');
  contains(out.reply.text, 'JADWAL LAIN', 'offers JADWAL LAIN');
  absent(out.reply.text, MENU, 'no issue menu');
});

scenario('a rejected confirmation is relayed and the state is not advanced', () => {
  const out = decide({ text: 'siap', state: SCHEDULE_STATE, app: APP_REJECTED });
  contains(out.reply.text, 'tidak dapat diubah atau sudah dikonfirmasi', 'app message relayed');
  eq(out.reply.values[3], null, 'last_template untouched');
});

scenario('a successful confirmation persists the package and the new step', () => {
  const out = decide({ text: 'siap', state: SCHEDULE_STATE, app: APP_OK });
  eq(out.reply.values[1], RESI, 'tracking');
  eq(out.reply.values[2], 'tok', 'token');
  eq(out.reply.values[3], 'confirmed', 'last_template');
});

// ------------------------------------------------------ inbound API rejection

console.log('');
console.log('Inbound — an API rejection is surfaced');

scenario('the API message is relayed however the error is wrapped', () => {
  const cases = [
    [{ error: { message: '422 - {"message":"Waktu pengantaran minimal 2 jam dari sekarang.","errors":{"slot":["x"]}}' } }, 'Waktu pengantaran minimal 2 jam dari sekarang.'],
    [{ error: { message: { message: 'Status paket tidak dapat dijadwalkan ulang.' } } }, 'Status paket tidak dapat dijadwalkan ulang.'],
    [{ error: { context: { data: '{"message":"Tidak ada jadwal yang menunggu konfirmasi."}' } } }, 'Tidak ada jadwal yang menunggu konfirmasi.'],
    [{ error: { body: '{"errors":{"slot":["Waktu pengantaran minimal 2 jam dari sekarang."]}}' } }, 'Waktu pengantaran minimal 2 jam dari sekarang.'],
    [{ error: { message: 'Request failed with status code 422' } }, 'Request failed with status code 422'],
  ];

  for (const pair of cases) {
    const out = decide({ text: 'siap', state: SCHEDULE_STATE, app: pair[0] });
    contains(out.reply.text, pair[1], 'relayed message');
    eq(out.reply.values[3], null, 'state not advanced after a rejection');
  }
});

scenario('a rejection never leaves the customer without an explanation', () => {
  const out = decide({ text: 'siap', state: SCHEDULE_STATE, app: { error: { message: 'boom' } } });
  contains(out.reply.text, 'hubungi layanan pelanggan', 'falls back to a next step');
});

// ------------------------------------------------------------ inbound choice

console.log('');
console.log('Inbound — issue options');

scenario('1 to 4 record the matching resolution', () => {
  const expected = [['1', 1], ['2', 2], ['3', 3], ['4', 4]];
  for (const pair of expected) {
    const out = decide({ text: pair[0], state: ISSUE_STATE });
    eq(out.decision.option, pair[1], 'option for ' + pair[0]);
    contains(out.decision.app_url, '/issue/choice', 'app url for ' + pair[0]);
    eq(out.decision.next_template, 'issue_choice_recorded', 'next template for ' + pair[0]);
  }
});

scenario('the option wording maps to the same numbers', () => {
  const expected = [['kirim ulang', 2], ['retur', 1], ['kompensasi', 3], ['mau diskusi', 4]];
  for (const pair of expected) {
    eq(decide({ text: pair[0], state: ISSUE_STATE }).decision.option, pair[1], 'option for ' + pair[0]);
  }
});

scenario('a number outside the menu is not a choice', () => {
  const out = decide({ text: '9', state: ISSUE_STATE });
  eq(out.decision.app_url, null, 'no app call');
  contains(out.reply.text, MENU, 'menu re-sent');
});

scenario('a number with no menu open is never a choice', () => {
  for (const template of ['delivered', 'on_the_way', 'schedule_request', null]) {
    const out = decide({ text: '1', state: { ...SCHEDULE_STATE, last_template: template } });
    eq(out.decision.app_url, null, 'no app call for ' + template);
  }
});

scenario('option 2 only promises a replacement when one was created', () => {
  const withReplacement = decide({
    text: '2',
    state: ISSUE_STATE,
    app: { success: true, data: { choice: 'RESEND', replacement_tracking_number: 'AJ-FRZ-0012400' } },
  });
  contains(withReplacement.reply.text, 'AJ-FRZ-0012400', 'names the replacement resi');
  contains(withReplacement.reply.text, 'konfirmasi jadwal', 'asks for the new window');

  const withoutReplacement = decide({
    text: '2',
    state: ISSUE_STATE,
    app: { success: true, data: { choice: 'RESEND', replacement_tracking_number: null } },
  });
  absent(withoutReplacement.reply.text, 'resi pengganti', 'no phantom replacement');
  contains(withoutReplacement.reply.text, 'diteruskan ke tim operasional', 'hands it to the ops team');
});

// --------------------------------------------------- inbound informative states

console.log('');
console.log('Inbound — steps that only inform');

scenario('notification-only states answer without a menu', () => {
  const expected = [
    ['on_the_way', 'sedang dalam pengiriman'],
    ['courier_arrived', 'sudah tiba di lokasi'],
    ['delivered', 'sudah diterima'],
    ['issue_decision_update:RETURN', 'ditangani tim operasional'],
  ];
  for (const pair of expected) {
    const out = decide({ text: 'apa kabar', state: { ...SCHEDULE_STATE, last_template: pair[0] } });
    eq(out.decision.app_url, null, 'no action for ' + pair[0]);
    contains(out.reply.text, pair[1], 'marker for ' + pair[0]);
    absent(out.reply.text, MENU, 'no menu for ' + pair[0]);
  }
});

scenario('a decided issue is described for what was decided', () => {
  const expected = [
    ['RETURN', 'dikembalikan ke pengirim'],
    ['RESEND', 'sedang disiapkan tim kami'],
    ['RECHECK', 'diperiksa ulang'],
    ['PROCEED', 'dilanjutkan sesuai keputusan tim kami'],
  ];

  for (const pair of expected) {
    const out = decide({
      text: 'oke',
      state: { ...SCHEDULE_STATE, last_template: 'issue_decision_update:' + pair[0], context: { decision: pair[0], client_url: CONTEXT.client_url } },
    });
    eq(out.decision.app_url, null, 'no action for ' + pair[0]);
    contains(out.reply.text, pair[1], 'wording for ' + pair[0]);
  }
});

scenario('informational replies carry the window, never a link', () => {
  const way = decide({ text: 'oke', state: { ...SCHEDULE_STATE, last_template: 'on_the_way' } });
  contains(way.reply.text, 'Estimasi penerimaan: Hari ini, 13:00 - 15:00', 'quotes the window');
  absent(way.reply.text, 'http', 'no URL in the reply');

  const decided = decide({
    text: 'oke',
    state: { ...SCHEDULE_STATE, last_template: 'issue_decision_update:RETURN', context: { decision: 'RETURN' } },
  });
  absent(decided.reply.text, 'http', 'no URL in the decision reply');
});

scenario('a parcel with nothing pending answers with its status', () => {
  const out = decide({
    text: RESI,
    state: { ...SCHEDULE_STATE, last_template: null, context: null },
    lookup: {
      found: true,
      tracking_number: RESI,
      client_token: 'tok',
      template: 'none',
      status_label: 'Jadwal Dikonfirmasi',
      window: 'Besok, 13:00 - 15:00',
    },
  });
  contains(out.reply.text, 'statusnya: Jadwal Dikonfirmasi', 'names the status');
  contains(out.reply.text, 'Besok, 13:00 - 15:00', 'names the window');
  absent(out.reply.text, 'http', 'no URL');
  absent(out.reply.text, 'Kirimkan nomor resi', 'not the help text');
});

scenario('no reply in the matrix ever carries a URL', () => {
  const cases = [
    { text: 'hai', state: NO_STATE },
    { text: 'siap', state: SCHEDULE_STATE },
    { text: '2', state: ISSUE_STATE },
    { text: 'oke', state: { ...SCHEDULE_STATE, last_template: 'on_the_way' } },
    { text: 'oke', state: { ...SCHEDULE_STATE, last_template: 'delivered' } },
    { text: RESI, state: NO_STATE, lookup: FOUND_REQUEST },
  ];

  for (const options of cases) {
    const out = decide(options);
    absent(out.reply.text, 'http', 'URL for ' + JSON.stringify(options.text));
    absent(out.reply.text, 'client/', 'token path for ' + JSON.stringify(options.text));
  }
});

// ------------------------------------------------------- inbound unknown chat

console.log('');
console.log('Inbound — unknown conversations and resi attach');

scenario('an unknown sender is asked for a resi, never for a menu', () => {
  const out = decide({ text: 'hai', state: NO_STATE });
  eq(out.decision.intent, 'info:none', 'intent');
  contains(out.reply.text, 'AJ-FRZ-0012345', 'example resi');
  absent(out.reply.text, MENU, 'no menu');
});

scenario('an unknown sender saying "ya" is not confirmed', () => {
  const out = decide({ text: 'ya', state: NO_STATE, llm: { intent: 'confirm_schedule' } });
  eq(out.decision.app_url, null, 'no app call');
});

scenario('a resi that resolves attaches the package and answers its pending step', () => {
  const out = decide({ text: RESI, state: NO_STATE, lookup: FOUND });
  eq(out.context.lookup_found, true, 'lookup found');
  eq(out.context.tracking_number, RESI, 'tracking attached');
  eq(out.context.last_template, 'schedule_request', 'template attached');
  eq(out.reply.values[1], RESI, 'tracking persisted');
  eq(out.reply.values[2], 'tok', 'token persisted');
  contains(out.reply.text, RESI, 'names the package');
});

scenario('a resi the sender does not own is reported back', () => {
  const out = decide({ text: 'AJ-FRZ-0000000', state: NO_STATE, lookup: { found: false } });
  eq(out.decision.intent, 'info:none', 'intent');
  contains(out.reply.text, 'tidak terdaftar untuk nomor WhatsApp ini', 'not found text');
  contains(out.reply.text, 'nomor pembeli yang terdaftar', 'explains the phone rule');
});

scenario('a stored conversation is kept when the resi matches it', () => {
  const out = decide({ text: RESI, state: SCHEDULE_STATE, lookup: FOUND });
  eq(out.context.last_template, 'schedule_request', 'state kept');
  eq(out.context.tracking_number, RESI, 'tracking kept');
});

// ------------------------------------------------------------------- staff

console.log('');
console.log('Inbound — courier and admin replies');

const COURIER_PHONE = '6281200000099';
const COURIER_STATE = {
  phone: COURIER_PHONE,
  tracking_number: RESI,
  client_token: null,
  last_template: 'task_assigned',
  context: { recipient_type: 'COURIER', window: 'Hari ini, 13:00 - 15:00' }
};
const ADMIN_STATE = {
  phone: COURIER_PHONE,
  tracking_number: RESI,
  client_token: null,
  last_template: 'issue_reported',
  context: { recipient_type: 'ADMIN' }
};
const FOUND_COURIER = {
  found: true,
  role: 'COURIER',
  courier_name: 'Budi Santoso',
  tracking_number: RESI,
  template: 'task_assigned',
  status_label: 'Kurir Ditugaskan',
  window: 'Hari ini, 13:00 - 15:00'
};
const FOUND_ADMIN = {
  found: true,
  role: 'ADMIN',
  tracking_number: RESI,
  template: 'admin_decision',
  status_label: 'Pengiriman Terkendala'
};

scenario('a courier accepts the task from a WhatsApp reply', () => {
  const out = decide({ text: 'terima', state: COURIER_STATE });

  eq(out.decision.app_url, 'http://anteraja-hackathon-service/api/internal/whatsapp/action', 'action endpoint');
  eq(out.decision.app_body.action, 'accept', 'action');
  eq(out.decision.app_body.resi, RESI, 'resi from the conversation');
  eq(out.decision.next_template, 'courier_accepted', 'next step');
  eq(out.decision.intent, 'staff:accept', 'intent');
});

scenario('the courier keywords map to the actions the app knows', () => {
  const cases = [
    ['TOLAK, motor masuk bengkel', 'reject', null],
    ['berangkat sekarang', 'depart', null],
    ['sudah tiba di lokasi', 'arrived', null],
    ['selesai, diterima Bu Rina', 'deliver', null],
    ['kemasan BOCOR di sisi kiri', 'issue', 'kemasan_bocor'],
    ['kemasan basah kena hujan', 'issue', 'kemasan_basah'],
    ['suhu naik, es mulai meleleh', 'issue', 'suhu_naik']
  ];

  for (const entry of cases) {
    const out = decide({ text: entry[0], state: COURIER_STATE });
    eq(out.decision.app_body.action, entry[1], 'action for ' + entry[0]);
    eq(out.decision.app_body.category, entry[2], 'category for ' + entry[0]);
    eq(out.decision.app_body.phone, PHONE, 'phone for ' + entry[0]);
  }
});

scenario('a courier reply the app cannot place asks for one it can', () => {
  const out = decide({ text: 'hmm nanti dulu deh', state: COURIER_STATE });

  eq(out.decision.app_url, null, 'no action call');
  contains(out.reply.text, 'TERIMA', 'lists the commands');
  contains(out.reply.text, 'BOCOR', 'lists the issue command');
});

scenario('an admin decision reply is handed to the app', () => {
  const cases = [
    ['kembalikan ' + RESI, 'return', 'admin_decision:RETURN'],
    ['kirim ulang saja', 'resend', 'admin_decision:RESEND'],
    ['periksa ulang dulu', 'recheck', 'admin_decision:RECHECK'],
    ['lanjutkan dengan kompensasi', 'proceed', 'admin_decision:PROCEED'],
    ['jadwalkan ulang', 'reschedule', 'admin_decision:RESCHEDULE']
  ];

  for (const entry of cases) {
    const out = decide({ text: entry[0], state: ADMIN_STATE });
    eq(out.decision.app_body.action, entry[1], 'action for ' + entry[0]);
    eq(out.decision.next_template, entry[2], 'next step for ' + entry[0]);
    eq(out.decision.app_body.note, entry[0], 'reason for ' + entry[0]);
    contains(out.decision.app_url, '/api/internal/whatsapp/action', 'action endpoint for ' + entry[0]);
  }
});

scenario('an admin reply the app cannot place lists the decisions', () => {
  const out = decide({ text: 'lihat dulu saja', state: ADMIN_STATE });

  eq(out.decision.app_url, null, 'no action call');
  contains(out.reply.text, 'KEMBALIKAN', 'lists the decisions');
});

scenario('the lookup tells the flow it is talking to a courier', () => {
  const out = decide({ text: RESI, state: NO_STATE, lookup: FOUND_COURIER });

  eq(out.context.role, 'COURIER', 'role from the app');
  eq(out.context.tracking_number, RESI, 'resi attached');
  eq(out.decision.intent, 'staff:unknown', 'the resi alone is not an action');
  contains(out.reply.text, 'TERIMA', 'courier help instead of a buyer menu');
});

scenario('an admin holding an open issue is recognised from the resi', () => {
  const out = decide({ text: RESI, state: NO_STATE, lookup: FOUND_ADMIN });

  eq(out.context.role, 'ADMIN', 'role from the app');
  contains(out.reply.text, 'KEMBALIKAN', 'admin help');
  absent(out.reply.text, MENU, 'never the buyer options menu');
});

scenario('the courier action carries the WhatsApp message id', () => {
  const out = decide({ text: 'BERANGKAT', state: COURIER_STATE, id: 'wamid-42' });

  eq(out.decision.app_body.message_id, 'wamid-42', 'message id forwarded');
  eq(out.decision.app_body.text, 'BERANGKAT', 'raw text forwarded');
});

scenario('a buyer conversation is never treated as staff', () => {
  const buyerLookup = {
    found: true,
    tracking_number: RESI,
    client_token: 'tok',
    client_url: 'https://app/api/client/tok',
    template: 'issue_options',
    status_label: 'Pengiriman Terkendala'
  };

  const out = decide({ text: '1', state: ISSUE_STATE, lookup: buyerLookup });

  eq(out.decision.intent, 'choice', 'buyer action kept');
  contains(out.decision.app_url, '/api/guest/', 'still the guest API');
  eq(out.decision.app_body.choice, 1, 'buyer choice recorded');
});

scenario('the courier action node carries the shared token', () => {
  eq(nodeOf(INBOUND, 'Panggil App').credentials.httpHeaderAuth.id, 'anterajaAppHook', 'credential');
});

scenario('the message history is wired for both directions', () => {
  const target = (from, index) => ((INBOUND.connections[from] || {}).main || [])[index || 0][0].node;

  eq(target('Pesan Masuk?', 0), 'Catat Pesan Masuk', 'inbound log');
  eq(target('Catat Pesan Masuk', 0), 'Ambil State', 'log then state');
  eq(target('Balas WAHA', 0), 'Catat Balasan', 'reply log');
  eq(target('Catat Balasan', 0), 'Simpan State Pesan', 'log then state save');

  contains(nodeOf(INBOUND, 'Catat Pesan Masuk').parameters.url, '/api/internal/whatsapp/messages', 'inbound url');
  contains(nodeOf(INBOUND, 'Catat Balasan').parameters.url, '/api/internal/whatsapp/messages', 'reply url');
  contains(nodeOf(OUTBOUND, 'Catat Pesan Keluar').parameters.url, '/api/internal/whatsapp/messages', 'outbound url');
});


// ----------------------------------------------------------------- outbound

console.log('');
console.log('Outbound — buyer notifications');

function render(template, payload, decision) {
  const body = {
    template: template,
    payload: Object.assign({
      recipient_phone: '6289636887928',
      recipient_name: 'Rina',
      tracking_number: 'AJ-FRZ-0012345',
      address: 'Jl. Melati 14',
      client_url: 'https://app/api/client/tok',
      window: 'Besok 13:00-15:00',
    }, payload || {}, decision ? { decision: decision } : {}),
  };

  return run(code(OUTBOUND, 'Susun Pesan'), { input: { body: body } });
}

scenario('every buyer step renders and sends', () => {
  const expected = [
    ['schedule_request', 'Balas *SIAP*'],
    ['schedule_rescheduled', 'Admin telah mengusulkan jadwal baru'],
    ['issue_options', '1. Pengembalian Paket ke Pengirim'],
    ['on_the_way', 'sedang diantar'],
    ['courier_arrived', 'sudah tiba di lokasi'],
    ['delivered', 'sudah diterima'],
  ];
  for (const pair of expected) {
    const out = render(pair[0]);
    eq(out.should_send, true, 'should_send for ' + pair[0]);
    eq(out.chat_id, '6289636887928@c.us', 'chat id for ' + pair[0]);
    contains(out.text, pair[1], 'marker for ' + pair[0]);
    contains(out.text, 'AJ-FRZ-0012345', 'resi for ' + pair[0]);
    eq(out.template, pair[0], 'stored template for ' + pair[0]);
  }
});

scenario('the decision update carries which decision was made', () => {
  const expected = [['RETURN', 'dikembalikan'], ['RESEND', 'pengiriman ulang'], ['PROCEED', 'kompensasi']];
  for (const pair of expected) {
    const out = render('issue_decision_update', { issue_label: 'Kemasan bocor' }, pair[0]);
    eq(out.should_send, true, 'should_send for ' + pair[0]);
    contains(out.text, pair[1], 'marker for ' + pair[0]);
  }
});

scenario('the resend notice carries the replacement resi and asks nothing', () => {
  const out = render('issue_decision_update', { issue_label: 'Kemasan bocor', message: 'Pengiriman ulang disiapkan. Resi pengganti: AJ-FRZ-0012399.' }, 'RESEND');
  eq(out.should_send, true, 'should_send');
  contains(out.text, 'AJ-FRZ-0012399', 'names the replacement resi');
  absent(out.text, 'Balas *SIAP*', 'asks for no confirmation');
  absent(out.text, 'Estimasi Penerimaan Baru', 'shows no window it does not have');
});

scenario('a recheck decision reaches the buyer', () => {
  const out = render('issue_decision_update', { issue_label: 'Kemasan bocor', message: 'Paket sedang diperiksa ulang oleh tim operasional kami.' }, 'RECHECK');
  eq(out.should_send, true, 'should_send');
  contains(out.text, 'diperiksa ulang', 'says what is happening');
  eq(out.template, 'issue_decision_update:RECHECK', 'stored template');
});

scenario('courier steps reach the courier', () => {
  const assigned = render('task_assigned', {
    scheduled_window: 'Besok 13:00-15:00',
    handling_instructions: 'Simpan dalam cold box, jangan dibalik.',
    items: [
      { name: 'Nugget Ayam', quantity: 2 },
      { name: 'Es Krim Vanilla', quantity: 1 }
    ]
  });
  eq(assigned.should_send, true, 'should_send for task_assigned');
  eq(assigned.chat_id, '6289636887928@c.us', 'courier chat id');
  contains(assigned.text, 'Nomor Resi: AJ-FRZ-0012345', 'names the resi');
  contains(assigned.text, 'Nugget Ayam x2', 'lists the items');
  contains(assigned.text, 'Instruksi penanganan: Simpan dalam cold box', 'carries the handling instructions');
  contains(assigned.text, 'Balas *TERIMA*', 'asks the courier to answer');
  contains(assigned.text, '*TOLAK*', 'offers the refusal');
  eq(assigned.template, 'task_assigned', 'stored template');

  const reminder = render('condition_check_reminder');
  eq(reminder.should_send, true, 'should_send for condition_check_reminder');
  contains(reminder.text, 'checklist pemeriksaan kemasan', 'asks for the checklist');

  const decision = render('issue_decision', {
    decision_reason: 'Segel terbuka saat pemeriksaan.',
    instructions: 'Tarik paket ke DC untuk klaim retur.'
  }, 'RETURN');
  eq(decision.should_send, true, 'should_send for issue_decision');
  contains(decision.text, 'Keputusan: Paket dikembalikan ke pengirim', 'names the decision');
  contains(decision.text, 'Alasan: Segel terbuka', 'carries the reason');
  contains(decision.text, 'Instruksi: Tarik paket ke DC', 'carries the instruction');
  eq(decision.template, 'issue_decision:RETURN', 'stored template');
});

scenario('admin steps reach the admin dashboard owner', () => {
  const reschedule = render('reschedule_requested', {
    recipient_name: 'Dimas',
    proposed_window: 'Besok, 13:00 - 15:00 WIB'
  });
  eq(reschedule.should_send, true, 'should_send for reschedule_requested');
  contains(reschedule.text, 'meminta jadwal penerimaan lain', 'says what happened');
  contains(reschedule.text, 'Jadwal yang diminta: Besok, 13:00 - 15:00 WIB', 'names the requested window');

  const overdue = render('confirmation_overdue', { recipient_name: 'Dimas', age_minutes: 135 });
  eq(overdue.should_send, true, 'should_send for confirmation_overdue');
  contains(overdue.text, 'belum menjawab', 'says the buyer has not answered');
  contains(overdue.text, '135 menit', 'states how long it has been');

  const reported = render('issue_reported', {
    recipient_name: 'Dimas',
    courier_name: 'Budi Santoso',
    issue_label: 'Kemasan bocor'
  });
  eq(reported.should_send, true, 'should_send for issue_reported');
  contains(reported.text, 'Budi Santoso', 'names the courier');
  contains(reported.text, 'Kendala: Kemasan bocor', 'names the problem');

  const rejected = render('task_rejected', {
    recipient_name: 'Dimas',
    courier_name: 'Budi Santoso',
    reason: 'Motor sedang masuk bengkel'
  });
  eq(rejected.should_send, true, 'should_send for task_rejected');
  contains(rejected.text, 'menolak penugasan', 'says the task was refused');
  contains(rejected.text, 'Motor sedang masuk bengkel', 'carries the reason');

  const choice = render('buyer_issue_choice', {
    recipient_name: 'Dimas',
    choice: 1,
    choice_label: 'Pengembalian Paket ke Pengirim',
    issue_label: 'Kemasan bocor'
  });
  eq(choice.should_send, true, 'should_send for buyer_issue_choice');
  contains(choice.text, 'Pilihan pembeli: Pengembalian Paket ke Pengirim', 'names the buyer choice');

  const resend = render('resend_requested', {
    recipient_name: 'Dimas',
    replacement_tracking_number: 'AJ-FRZ-0012399'
  });
  eq(resend.should_send, true, 'should_send for resend_requested');
  contains(resend.text, 'Resi pengganti: AJ-FRZ-0012399', 'names the replacement');
});

scenario('the daily report carries every count the brief asks for', () => {
  const out = render('daily_report', {
    recipient_name: 'Dimas',
    report_date: '16 Oktober 2026',
    total_shipments: 12,
    delivered_count: 7,
    in_process_count: 4,
    issue_count: 1,
    decision_count: 2,
    unanswered_count: 3
  });

  eq(out.should_send, true, 'should_send');
  contains(out.text, 'Laporan Harian Anteraja Frozen', 'opens as a report');
  contains(out.text, 'Tanggal: 16 Oktober 2026', 'names the day');
  contains(out.text, 'Total paket: 12', 'totals');
  contains(out.text, 'Selesai (diterima): 7', 'delivered');
  contains(out.text, 'Dalam proses: 4', 'in process');
  contains(out.text, 'Kendala hari ini: 1', 'issues');
  contains(out.text, 'Keputusan admin: 2', 'decisions');
  contains(out.text, 'Pembeli belum merespons: 3', 'unanswered');
});

scenario('a report with no numbers still renders zeroes', () => {
  const out = render('daily_report', { recipient_name: 'Dimas' });
  eq(out.should_send, true, 'should_send');
  contains(out.text, 'Total paket: 0', 'no undefined leaks into the message');
  absent(out.text, 'undefined', 'never prints undefined');
});

scenario('every operational message stands alone without a link', () => {
  const templates = [
    'schedule_request', 'schedule_rescheduled', 'issue_options', 'on_the_way', 'courier_arrived', 'delivered',
    'task_assigned', 'condition_check_reminder', 'issue_decision', 'reschedule_requested',
    'confirmation_overdue', 'issue_reported', 'task_rejected', 'buyer_issue_choice', 'resend_requested',
    'daily_report'
  ];
  for (const template of templates) {
    const out = render(template, { issue_label: 'Kemasan bocor', choice_label: 'Pengembalian Paket ke Pengirim' });
    eq(out.should_send, true, 'should_send for ' + template);
    absent(out.text, 'http', 'no link in ' + template);
    absent(out.text, 'undefined', 'no missing field in ' + template);
    contains(out.text, 'Anteraja', 'names the service in ' + template);
  }
});

scenario('an unusable recipient phone is never sent', () => {
  const out = render('delivered', { recipient_phone: '' });
  eq(out.should_send, false, 'should_send');
  eq(out.response.reason, 'missing_recipient_phone', 'reason');
});

scenario('a local phone number is normalised before sending', () => {
  eq(render('delivered', { recipient_phone: '089636887928' }).chat_id, '6289636887928@c.us', 'chat id');
});

// ------------------------------------------------------------------ summary

console.log('');
console.log(passed + ' scenarios passed, ' + failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
