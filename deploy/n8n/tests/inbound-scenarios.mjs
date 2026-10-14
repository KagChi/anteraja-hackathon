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
  const $now = { setZone: () => ({ toFormat: () => today }) };

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
        body: { event: 'message', payload: { from: from, fromMe: o.fromMe === true, body: message, notifyName: null } },
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
  const normalised = normalise({ text: text });
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

const SCHEDULE_STATE = { phone: PHONE, tracking_number: RESI, client_token: 'tok', last_template: 'schedule_request' };
const ISSUE_STATE = { phone: PHONE, tracking_number: RESI, client_token: 'tok', last_template: 'issue_options' };
const NO_STATE = { phone: PHONE, tracking_number: null, client_token: null, last_template: null };

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
    ['Pesan Masuk?', 0, 'Ambil State'],
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
    ['Balas WAHA', 0, 'Simpan State Pesan'],
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
  contains(out.reply.text, 'tidak kami temukan', 'not found text');
});

scenario('a stored conversation is kept when the resi matches it', () => {
  const out = decide({ text: RESI, state: SCHEDULE_STATE, lookup: FOUND });
  eq(out.context.last_template, 'schedule_request', 'state kept');
  eq(out.context.tracking_number, RESI, 'tracking kept');
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
    ['schedule_rescheduled', 'Berhasil menjadwalkan ulang'],
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

scenario('courier and admin steps stay in the app', () => {
  const templates = ['task_assigned', 'task_rejected', 'issue_reported', 'reschedule_requested', 'issue_decision'];
  for (const template of templates) {
    const out = render(template);
    eq(out.should_send, false, 'should_send for ' + template);
    eq(out.response.reason, 'unmapped', 'reason for ' + template);
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
