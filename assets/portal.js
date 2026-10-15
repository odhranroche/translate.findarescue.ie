// ==========================================================================
// Translation portal: the page's behaviour (shared by every language).
// Language- and project-specific values come from api.php?action=config,
// i.e. languages/<id>/language.json. Nothing in this file names a language.
// ==========================================================================
(() => {
"use strict";
const API = "api.php";
const $ = id => document.getElementById(id);
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]));
const icon = (name, cls = "") => `<svg class="i ${cls}" aria-hidden="true"><use href="#i-${name}"/></svg>`;
const store = { get(k){ try { return localStorage.getItem(k); } catch { return null; } }, set(k, v){ try { localStorage.setItem(k, v); } catch {} } };

// ---------- language settings ----------
// Everything that differs between languages/projects comes from
// languages/<id>/language.json, served by api.php?action=config. The wording
// below is the English default for the portal's own interface; a language can
// override any line under "ui" in its language.json. {tokens} are filled in
// from the settings: {project} {language} {native} {base} {source} {code} {block}.
const DEFAULT_UI = {
  pageTitle: "Translate · {project}",
  brandAria: "{project} Translate, back to top",
  brandLong: "{project} Translate",
  brandShort: "Translate",
  brandSub: "{language} translation workspace",
  resyncTitle: "Re-read {source}",
  heroTitle: "Help translate {project}",
  heroText: "Review each phrase, accept the best {language} translation, or suggest a better one. Every change saves as you go.",
  searchPlaceholder: "Search {base}, {language} or key",
  welcomeTitleFirst: "Welcome",
  welcomeTitle: "How it works",
  welcomeIntro: "Thanks for helping translate {project} into {language}. Here's how it works:",
  welcomeStep1Title: "Review the translation",
  welcomeStep1Text: "Each {base} phrase comes with an AI translation to start from.",
  welcomeStep2Title: "Accept or reject",
  welcomeStep2Text: "If more people accept a translation than reject it, it becomes the accepted translation.",
  welcomeStep3Title: "Suggest your own",
  welcomeStep3Text: "Add a better translation, with a note to explain your choice if you like.",
  namePlaceholder: "e.g. Alex Smith",
  exportTitle: "Export {language}",
  exportIntro: "This builds the {language} section of {source}. Each key gets its accepted translation. Keys without one keep their current value. Paste it over the `{block}: { … }` block.",
  exportAria: "Exported {language} block",
  bannerFetchFailed: "Couldn't read {source}, so new or changed text may be missing. Check TRANSLATIONS_SOURCE in config.php.",
  bannerParseFailed: "{source} was read but no text was found in it. Nothing was changed.",
  resyncOk: "Up to date with {source}",
  resyncFail: "Resync didn't complete",
  allDoneText: "Every piece of text has an accepted {language} translation. Thank you!",
  noTextYet: "Nothing was found in {source}.",
  olderBase: "Older {base}",
  baseChanged: "{base} changed {ago}",
  copyBase: "Copy {base}",
  copyBaseAria: "Copy {base} text",
  baseCopied: "{base} copied",
  composerLabel: "Your {language} translation",
  composerPlaceholder: "Write the translation here…",
  composerEmpty: "Please write the {language} translation."
};
let CFG = null, UI = DEFAULT_UI, TOK = {}, LOCALE = "en", SECTION_STRIP = [], K = { name: "tp_name", filter: "tp_filter2" };
const ui = (key, extra = {}) => String(UI[key] ?? key).replace(/\{(\w+)\}/g, (m, k) => k in extra ? extra[k] : k in TOK ? TOK[k] : m);
const rich = s => esc(s).replace(/`([^`]+)`/g, "<code>$1</code>");   // `code` → <code>

function applyConfig(c){
  CFG = c;
  const L = c.language, B = c.base_language, P = c.project, src = c.source;
  UI = { ...DEFAULT_UI, ...(Array.isArray(c.ui) ? {} : c.ui) };
  TOK = { project: P.name, language: L.name, native: L.native_name, base: B.name, source: src.file_name, code: L.code, block: src.target_block };
  LOCALE = c.ui_locale || "en";
  SECTION_STRIP = (c.section_strip || []).flatMap(p => { try { return [new RegExp(p, "i")]; } catch { return []; } });
  K = { name: c.storage_prefix + "name", filter: c.storage_prefix + "filter2" };

  document.title = ui("pageTitle");
  const colour = /^#[0-9a-f]{3,8}$/i.test(c.theme?.color || "") ? c.theme.color : "#0b6e4f";
  $("themeColor").content = colour;
  $("favicon").href = "data:image/svg+xml," + encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='${colour}'/>` +
    `<text x='16' y='22' font-family='Arial' font-weight='700' font-size='15' fill='white' text-anchor='middle'>${esc(L.logo)}</text></svg>`);
  $("logoText").textContent = L.logo;

  // Optional colour overrides: CSS custom properties for light and dark mode.
  const vars = o => Object.entries(Array.isArray(o) ? {} : o || {}).filter(([k, v]) => /^--[\w-]+$/.test(k) && typeof v === "string")
    .map(([k, v]) => `${k}:${v.replace(/[;{}<>]/g, "")}`).join(";");
  const light = vars(c.theme?.light), dark = vars(c.theme?.dark);
  if (light || dark) {
    const st = document.createElement("style");
    st.textContent = (light ? `:root{${light}}` : "") + (dark ? `@media (prefers-color-scheme:dark){:root{${dark}}}` : "");
    document.head.appendChild(st);
  }

  // Fill the page's fixed wording (data-ui* attributes in index.html).
  document.querySelectorAll("[data-ui]").forEach(el => { el.textContent = ui(el.dataset.ui); });
  document.querySelectorAll("[data-ui-rich]").forEach(el => { el.innerHTML = rich(ui(el.dataset.uiRich)); });
  document.querySelectorAll("[data-ui-placeholder]").forEach(el => { el.placeholder = ui(el.dataset.uiPlaceholder); });
  document.querySelectorAll("[data-ui-title]").forEach(el => { el.title = ui(el.dataset.uiTitle); });
  document.querySelectorAll("[data-ui-aria]").forEach(el => { el.setAttribute("aria-label", ui(el.dataset.uiAria)); });
}

// ---------- state ----------
let S = { strings: [], byId: new Map(), trByString: new Map(), votesByTr: new Map(), notesByString: new Map() };
let filter = "todo", q = "", section = "", me = "";
const touched = new Set();       // cards changed since the filter last changed stay in view
const composing = new Set();     // cards with the "suggest translation" form open
const notesOpen = new Set();     // cards with the notes panel open
const drafts = new Map();        // sid -> {text, note}; survives re-renders
const noteDrafts = new Map();    // sid -> text

// ---------- helpers ----------
const sameName = (a, b) => String(a).trim().localeCompare(String(b).trim(), undefined, { sensitivity: "base" }) === 0;
function ago(iso){
  const t = Date.parse(iso); if (!t) return "";
  const s = (Date.now() - t) / 1000;
  if (s < 45) return "just now";
  if (s < 3600) return `${Math.round(s / 60)} min ago`;
  if (s < 86400) return `${Math.round(s / 3600)} h ago`;
  if (s < 86400 * 7) { const d = Math.round(s / 86400); return d === 1 ? "yesterday" : `${d} days ago`; }
  return new Date(t).toLocaleDateString(LOCALE, { day: "numeric", month: "short", year: "numeric" });
}
const full = iso => new Date(iso).toLocaleString(LOCALE, { dateStyle: "medium", timeStyle: "short" });
const initials = n => (String(n).trim().split(/\s+/).map(w => w[0]).join("").slice(0, 2) || "?").toUpperCase();
const HUES = [158, 200, 262, 18, 330, 38, 120, 285];
const hue = n => { let h = 0; for (const c of String(n).toLowerCase()) h = (h * 31 + c.charCodeAt(0)) >>> 0; return HUES[h % HUES.length]; };
const avatar = (n, sm = true) => `<span class="avatar ${sm ? "sm" : ""}" style="background:hsl(${hue(n)} 55% 42%)" aria-hidden="true">${esc(initials(n))}</span>`;

function score(t){
  const vs = S.votesByTr.get(t.id) || [];
  const up = vs.filter(v => v.vote > 0), down = vs.filter(v => v.vote < 0);
  const mine = me ? (vs.find(v => sameName(v.voter, me))?.vote || 0) : 0;
  return { up, down, net: up.length - down.length, mine };
}
const trs = s => S.trByString.get(s.id) || [];
const aiOf = s => trs(s).find(t => t.source === "ai");
const humansOf = s => trs(s).filter(t => t.source === "human");
// The accepted translation: highest net score above zero; the newest wins a tie (same rule as Export).
function acceptedOf(s){
  let best = null, bestNet = 0;
  for (const t of trs(s)) {
    const n = score(t).net;
    if (n > bestNet || (n === bestNet && n > 0 && (t.created_at > best.created_at || (t.created_at === best.created_at && t.id > best.id)))) { best = t; bestNet = n; }
  }
  return best;
}
const isTodo = s => !acceptedOf(s);
const active = () => S.strings.filter(s => s.active === 1);
// Section names come from comments in the translations file; language.json ("section_strip") lists patterns to drop from the displayed name.
const secOf = s => s.section || "Other";
const secLabel = k => { let t = k; for (const re of SECTION_STRIP) t = t.replace(re, ""); return t.length > 48 ? t.slice(0, 46) + "…" : t; };

// ---------- data ----------
async function load({ resync = false, quiet = false } = {}){
  try {
    const r = await fetch(`${API}?action=list${resync ? "&resync=1" : ""}`, { cache: "no-store" });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || "Couldn't load");
    index(d);
    const msg = { fetch_failed: ui("bannerFetchFailed"), parse_failed: ui("bannerParseFailed") }[d.sync];
    $("banner").hidden = !msg; $("bannerText").textContent = msg || "";
    buildSections();
    render();
    if (resync) toast(d.sync === "synced" ? ui("resyncOk") : ui("resyncFail"), d.sync !== "synced");
  } catch (e) {
    if (!quiet) { $("list").innerHTML = emptyState("alert", "Couldn't load translations", esc(e.message), `<button class="btn btn-primary" data-act="retry">Try again</button>`); }
    else toast("Couldn't refresh: " + e.message, true);
  }
}
function index(d){
  const num = (o, ks) => ks.forEach(k => o[k] = o[k] === null ? null : Number(o[k]));
  d.strings.forEach(s => num(s, ["id", "sort_order", "file_ga_todo", "active"]));
  d.translations.forEach(t => num(t, ["id", "string_id"]));
  d.votes.forEach(v => num(v, ["translation_id", "vote"]));
  d.notes.forEach(n => num(n, ["id", "string_id"]));
  const trByString = new Map(), votesByTr = new Map(), notesByString = new Map(), byId = new Map();
  const push = (m, k, v) => { if (!m.has(k)) m.set(k, []); m.get(k).push(v); };
  d.strings.forEach(s => byId.set(s.id, s));
  d.translations.forEach(t => push(trByString, t.string_id, t));   // newest first from the API
  d.votes.forEach(v => push(votesByTr, v.translation_id, v));
  d.notes.forEach(n => push(notesByString, n.string_id, n));
  S = { strings: d.strings, byId, trByString, votesByTr, notesByString };
}
async function post(action, payload){
  const r = await fetch(`${API}?action=${action}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-Requested-With": "translate-portal" },
    body: JSON.stringify(payload)
  });
  const d = await r.json().catch(() => ({ ok: false, error: `Server error (${r.status})` }));
  if (!d.ok) throw new Error(d.error || "Save failed");
  return d;
}

// ---------- rendering ----------
function buildSections(){
  const secs = [...new Set(active().map(secOf))];
  const sel = $("section"), cur = section;
  sel.innerHTML = `<option value="">All sections</option>` + secs.map(x => `<option value="${esc(x)}">${esc(secLabel(x))}</option>`).join("");
  sel.value = secs.includes(cur) ? cur : ""; section = sel.value;
}
function visible(){
  const ql = q.trim().toLowerCase();
  return active().filter(s => {
    if (section && secOf(s) !== section) return false;
    if (filter === "todo" && !touched.has(s.id) && !isTodo(s)) return false;
    if (!ql) return true;
    return [s.str_key, s.en_text, ...trs(s).map(t => t.text), ...(S.notesByString.get(s.id) || []).map(n => n.text)]
      .some(x => String(x).toLowerCase().includes(ql));
  });
}
function emptyState(ic, title, text, cta = ""){
  return `<div class="empty"><div class="art">${icon(ic)}</div><h3>${title}</h3><p>${text}</p>${cta}</div>`;
}
function render(){
  updateStats();
  const list = visible();
  if (!list.length) {
    const ql = q.trim();
    $("list").innerHTML = ql || section
      ? emptyState("search", "No matches", "Try a different word, or clear the search and section filter.", `<button class="btn btn-secondary" data-act="clear">Clear filters</button>`)
      : filter === "todo"
        ? emptyState("party", "Everything's translated", esc(ui("allDoneText")), `<button class="btn btn-secondary" data-act="show-all">Review all text</button>`)
        : emptyState("search", "No text yet", esc(ui("noTextYet")));
    return;
  }
  let html = "", sec = null;
  const counts = {};
  list.forEach(s => { const k = secOf(s); counts[k] = (counts[k] || 0) + 1; });
  list.forEach((s, i) => {
    const k = secOf(s);
    if (k !== sec) { sec = k; html += `<div class="sec-head"><h2 title="${esc(k)}">${esc(secLabel(k))}</h2><span class="count">${counts[k]}</span><span class="line"></span></div>`; }
    html += cardHTML(s, i);
  });
  $("list").innerHTML = html;
}
function updateStats(){
  const a = active(), done = a.filter(s => !isTodo(s)).length, total = a.length, todo = total - done;
  const pct = total ? Math.round(done / total * 100) : 0;
  $("pDone").textContent = done; $("pTotal").textContent = total; $("pTodo").textContent = todo;
  $("pPct").textContent = `${pct}%`;
  $("pBar").setAttribute("aria-valuenow", pct); $("pBar").firstElementChild.style.width = pct + "%";
  $("cTodo").textContent = todo; $("cAll").textContent = total;
  $("nextBtn").disabled = todo === 0;
  document.querySelectorAll(".seg button").forEach(b => b.setAttribute("aria-pressed", String(b.dataset.f === filter)));
}
function optHTML(s, t, acc){
  const sc = score(t), isAcc = acc && acc.id === t.id;
  const cls = isAcc ? "accepted" : sc.net < 0 ? "rejected" : sc.net > 0 ? "leaning" : "";
  const outdated = t.en_snapshot !== s.en_text;
  let who;
  if (t.source === "ai") who = `<span class="avatar sm ai" aria-hidden="true">${icon("spark","sm")}</span><span><b>${esc(CFG.ai.label)}</b> · <time datetime="${esc(t.created_at)}" title="${esc(full(t.created_at))}">${ago(t.created_at)}</time></span>`;
  else who = `${avatar(t.translator)}<span><b>${esc(t.translator)}</b> · <time datetime="${esc(t.created_at)}" title="${esc(full(t.created_at))}">${ago(t.created_at)}</time></span>`;
  const upNames = sc.up.map(v => v.voter).join(", "), downNames = sc.down.map(v => v.voter).join(", ");
  return `<div class="opt ${cls}">
    <div class="opt-top"><p class="target-text" lang="${esc(CFG.language.code)}">${esc(t.text)}</p>${isAcc ? `<span class="badge-accepted">${icon("check","sm")}Accepted</span>` : ""}</div>
    ${t.note ? `<p class="opt-note">${esc(t.note)}</p>` : ""}
    <div class="opt-bot">
      <div class="who">${who}</div>
      ${outdated ? `<span class="chip warn" title="Translated from: ${esc(t.en_snapshot)}">${icon("alert","sm")}${esc(ui("olderBase"))}</span>` : ""}
      <div class="acts">
        <button class="vote accept" data-act="vote" data-v="1" data-t="${t.id}" data-s="${s.id}" aria-pressed="${sc.mine > 0}"
          title="${upNames ? "Accepted by " + esc(upNames) : "Accept this translation"}" aria-label="Accept${sc.up.length ? `, ${sc.up.length} so far` : ""}">${icon("check","sm")}<span>Accept</span>${sc.up.length ? `<span class="n">${sc.up.length}</span>` : ""}</button>
        <button class="vote reject" data-act="vote" data-v="-1" data-t="${t.id}" data-s="${s.id}" aria-pressed="${sc.mine < 0}"
          title="${downNames ? "Rejected by " + esc(downNames) : "Reject this translation"}" aria-label="Reject${sc.down.length ? `, ${sc.down.length} so far` : ""}">${icon("x","sm")}${sc.down.length ? `<span class="n">${sc.down.length}</span>` : `<span class="sr-only">Reject</span>`}</button>
        <button class="del" data-act="del-tr" data-t="${t.id}" data-s="${s.id}" title="Delete this translation" aria-label="Delete this translation">${icon("trash","sm")}</button>
      </div>
    </div>
  </div>`;
}
function cardHTML(s, i = 0){
  const acc = acceptedOf(s), ai = aiOf(s), hs = humansOf(s);
  const notes = S.notesByString.get(s.id) || [];
  const d = drafts.get(s.id) || { text: "", note: "" };
  const enChanged = s.en_changed_at && s.prev_en_text !== null;
  const delay = Math.min(i, 8) * 30;
  return `<article class="card ${acc ? "is-done" : ""}" data-sid="${s.id}" id="s-${s.id}" style="animation-delay:${delay}ms" aria-labelledby="en-${s.id}">
    <div class="src">
      ${acc ? `<span class="status done">${icon("check","sm")}Accepted</span>` : `<span class="status todo"><i class="dot" style="background:currentColor;margin:0"></i>To translate</span>`}
      <p class="en" id="en-${s.id}" lang="${esc(CFG.base_language.code)}">${esc(s.en_text)}</p>
      <div class="keyrow">
        <span class="key">${esc(s.str_key)}</span>
        <button class="copy" data-act="copy-en" data-s="${s.id}" title="${esc(ui("copyBase"))}" aria-label="${esc(ui("copyBaseAria"))}">${icon("copy","sm")}</button>
        ${enChanged ? `<span class="chip warn" title="Previously: ${esc(s.prev_en_text)}">${icon("alert","sm")}${esc(ui("baseChanged", { ago: ago(s.en_changed_at) }))}</span>` : ""}
      </div>
    </div>
    <div class="tgt">
      <div class="group-label">${icon("spark","sm")}AI translation</div>
      ${ai ? optHTML(s, ai, acc) : `<div class="empty-opt">No AI translation for this text.</div>`}
      <div class="group-label">Human translations ${hs.length ? `<span class="count">${hs.length}</span>` : ""}</div>
      ${hs.length ? hs.map(h => optHTML(s, h, acc)).join("") : ""}
      ${composing.has(s.id) ? `
        <form class="composer" data-form="tr" data-s="${s.id}">
          <div><label class="label" for="t-${s.id}">${esc(ui("composerLabel"))}</label>
            <textarea class="field" id="t-${s.id}" name="text" lang="${esc(CFG.language.code)}" maxlength="2000" rows="3" placeholder="${esc(ui("composerPlaceholder"))}" data-draft="text">${esc(d.text)}</textarea></div>
          <div><label class="label" for="n-${s.id}">Note <span class="hint">(optional)</span></label>
            <input class="field" id="n-${s.id}" name="note" maxlength="1000" placeholder="Why this wording? Any context for others?" value="${esc(d.note)}" data-draft="note"></div>
          <div class="formerr" role="alert"></div>
          <div class="foot"><span class="hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> to save</span>
            <button type="button" class="btn btn-ghost btn-sm" data-act="cancel" data-s="${s.id}">Cancel</button>
            <button class="btn btn-primary btn-sm">${icon("check","sm")}Save translation</button></div>
        </form>` : ""}
      ${notesOpen.has(s.id) ? `
        <div class="notes" id="notes-${s.id}">
          ${notes.length ? notes.map(n => `<div class="note">${avatar(n.author)}<div class="body"><div class="head"><b>${esc(n.author)}</b> · <time datetime="${esc(n.created_at)}" title="${esc(full(n.created_at))}">${ago(n.created_at)}</time><button class="del" data-act="del-note" data-n="${n.id}" data-s="${s.id}" title="Delete this note" aria-label="Delete note by ${esc(n.author)}">${icon("trash","sm")}</button></div><p>${esc(n.text)}</p></div></div>`).join("")
                         : `<p class="hint" style="margin:0">No discussion yet. Ask a question or add context for other translators.</p>`}
          <form class="note-form" data-form="note" data-s="${s.id}">
            <label class="sr-only" for="nt-${s.id}">Add a note</label>
            <textarea class="field" id="nt-${s.id}" name="text" maxlength="1000" rows="2" placeholder="Add a note…" data-draft="notetext" style="min-height:60px">${esc(noteDrafts.get(s.id) || "")}</textarea>
            <div class="formerr" role="alert"></div>
            <div class="foot"><span class="hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> to post</span><button class="btn btn-secondary btn-sm">Post note</button></div>
          </form>
        </div>` : ""}
      <div class="row-actions">
        ${composing.has(s.id) ? "" : `<button class="btn btn-ghost btn-sm add-btn" data-act="compose" data-s="${s.id}">${icon("plus","sm")}Suggest a translation</button>`}
        <button class="btn btn-ghost btn-sm" data-act="notes" data-s="${s.id}" aria-expanded="${notesOpen.has(s.id)}" aria-controls="notes-${s.id}">${icon("chat","sm")}${notesOpen.has(s.id) ? "Hide notes" : notes.length ? `Notes <span class="count">${notes.length}</span>` : "Add note"}</button>
      </div>
    </div>
  </article>`;
}
function rerenderCard(sid, focusSel){
  const el = document.querySelector(`.card[data-sid="${sid}"]`), s = S.byId.get(sid);
  if (!el || !s) return render();
  const tmp = document.createElement("div"); tmp.innerHTML = cardHTML(s);
  const card = tmp.firstElementChild; card.style.animation = "none";
  el.replaceWith(card);
  updateStats();
  if (focusSel) { const f = card.querySelector(focusSel); if (f) { f.focus(); if (f.setSelectionRange) f.setSelectionRange(f.value.length, f.value.length); } }
  return card;
}

// ---------- toasts ----------
function toast(msg, err = false, action = null){
  const t = document.createElement("div");
  t.className = "toast" + (err ? " err" : "");
  t.innerHTML = `${icon(err ? "alert" : action ? "trash" : "check")}<span>${esc(msg)}</span>`
    + (action ? `<button class="undo">${esc(action.label)}</button>` : "")
    + (err || action ? `<button class="x" aria-label="Dismiss">${icon("x","sm")}</button>` : "");
  $("toasts").appendChild(t);
  let done = false;
  const close = () => { if (done) return; done = true; t.classList.add("out"); setTimeout(() => t.remove(), 260); };
  t.querySelector(".x")?.addEventListener("click", close);
  t.querySelector(".undo")?.addEventListener("click", () => { close(); action.fn(); });
  setTimeout(close, err ? 7000 : action ? 8000 : 2200);
}

// ---------- identity ----------
function paintMe(){
  $("meName").textContent = me || "Set your name";
  const a = $("meAvatar");
  a.textContent = me ? initials(me) : "?";
  a.style.background = me ? `hsl(${hue(me)} 55% 42%)` : "var(--muted)";
}
function openWelcome(first){
  $("wName").value = me; $("wErr").textContent = "";
  $("wTitle").textContent = first ? ui("welcomeTitleFirst") : ui("welcomeTitle");
  $("wSubmit").textContent = first ? "Get started" : "Save";
  $("wCancel").hidden = first;
  $("welcome").showModal(); $("wName").focus();
}
$("welcomeForm").addEventListener("submit", e => {
  const n = $("wName").value.trim().replace(/\s+/g, " ").slice(0, 80);
  if (!n) { e.preventDefault(); $("wErr").textContent = "Please enter your name so others can see who translated what."; $("wName").focus(); return; }
  me = n; store.set(K.name, me); paintMe(); render();
});

$("wCancel").onclick = () => $("welcome").close();
$("meBtn").onclick = () => openWelcome(false);
$("helpBtn").onclick = () => openWelcome(!me);
const needName = () => { if (me) return true; openWelcome(true); return false; };

// ---------- interactions ----------
$("list").addEventListener("click", async e => {
  const b = e.target.closest("[data-act]"); if (!b) return;
  const sid = Number(b.dataset.s);
  switch (b.dataset.act) {
    case "vote": {
      if (!needName()) return;
      const tid = Number(b.dataset.t), want = Number(b.dataset.v);
      const s = S.byId.get(sid), t = trs(s).find(x => x.id === tid);
      const before = S.votesByTr.get(tid) || [];
      const vote = score(t).mine === want ? 0 : want;             // clicking your current vote again clears it
      const wasDone = !isTodo(s);
      const next = before.filter(v => !sameName(v.voter, me));
      if (vote) next.push({ translation_id: tid, voter: me, vote });
      S.votesByTr.set(tid, next); touched.add(sid);
      const card = rerenderCard(sid);
      card?.querySelector(`.vote[data-t="${tid}"][data-v="${want}"]`)?.classList.add("bump");
      card?.querySelector(`.vote[data-t="${tid}"][data-v="${want}"]`)?.focus();
      try {
        await post("vote", { translation_id: tid, name: me, vote });
        const nowDone = !isTodo(s);
        toast(nowDone && !wasDone ? "Accepted, nice one!" : !nowDone && wasDone ? "Back on the to-translate list" : vote === 0 ? "Vote removed" : vote > 0 ? "Accepted" : "Rejected");
      } catch (err) {
        S.votesByTr.set(tid, before); rerenderCard(sid);
        toast("Not saved: " + err.message, true);
      }
      break;
    }
    case "compose":
      if (!needName()) return;
      composing.add(sid); rerenderCard(sid, "textarea[data-draft=text]"); break;
    case "cancel":
      composing.delete(sid); drafts.delete(sid); rerenderCard(sid, "[data-act=compose]"); break;
    case "notes":
      if (notesOpen.has(sid)) { notesOpen.delete(sid); rerenderCard(sid, "[data-act=notes]"); }
      else { notesOpen.add(sid); rerenderCard(sid, "textarea[data-draft=notetext]"); }
      break;
    case "del-tr":
      removeItem("translation", Number(b.dataset.t), sid); break;
    case "del-note":
      removeItem("note", Number(b.dataset.n), sid); break;
    case "copy-en":
      try { await navigator.clipboard.writeText(S.byId.get(sid).en_text); toast(ui("baseCopied")); } catch { toast("Couldn't copy", true); }
      break;
    case "retry": load(); break;
    case "reload": location.reload(); break;
    case "clear": q = ""; section = ""; $("q").value = ""; $("section").value = ""; render(); break;
    case "show-all": setFilter("all"); break;
  }
});
// Keep drafts across re-renders
$("list").addEventListener("input", e => {
  const el = e.target, form = el.closest("form"); if (!form || !el.dataset.draft) return;
  const sid = Number(form.dataset.s);
  if (el.dataset.draft === "notetext") { noteDrafts.set(sid, el.value); return; }
  const d = drafts.get(sid) || { text: "", note: "" }; d[el.dataset.draft] = el.value; drafts.set(sid, d);
});
$("list").addEventListener("keydown", e => {
  const form = e.target.closest("form[data-form]"); if (!form) return;
  if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
  if (e.key === "Escape" && form.dataset.form === "tr") { e.preventDefault(); form.querySelector("[data-act=cancel]").click(); }
});
$("list").addEventListener("submit", async e => {
  const form = e.target.closest("form[data-form]"); if (!form) return;
  e.preventDefault();
  if (!needName()) return;
  const sid = Number(form.dataset.s), err = form.querySelector(".formerr"), btn = form.querySelector("button:not([type=button])");
  err.textContent = "";
  try {
    if (form.dataset.form === "tr") {
      const text = form.elements.text.value.trim(), note = form.elements.note.value.trim();
      if (!text) { err.textContent = ui("composerEmpty"); form.elements.text.focus(); return; }
      btn.disabled = true;
      await post("add_translation", { string_id: sid, text, name: me, note });
      composing.delete(sid); drafts.delete(sid); touched.add(sid);
      toast("Translation saved");
    } else {
      const text = form.elements.text.value.trim();
      if (!text) { err.textContent = "Please write a note."; form.elements.text.focus(); return; }
      btn.disabled = true;
      await post("add_note", { string_id: sid, text, name: me });
      noteDrafts.delete(sid); touched.add(sid);
      toast("Note posted");
    }
    await load({ quiet: true });
    document.querySelector(`.card[data-sid="${sid}"]`)?.querySelector("[data-act=compose],[data-act=notes]")?.focus({ preventScroll: true });
  } catch (x) {
    err.textContent = "Not saved: " + x.message; btn.disabled = false;
  }
});

// Delete a translation or note straight away, with an Undo in the toast.
async function removeItem(type, id, sid){
  if (!needName()) return;
  const map = type === "translation" ? S.trByString : S.notesByString;
  const list = map.get(sid) || [], idx = list.findIndex(x => x.id === id);
  if (idx < 0) return;
  const [item] = list.splice(idx, 1);
  touched.add(sid);
  const card = rerenderCard(sid);
  card?.querySelector(type === "translation" ? "[data-act=compose]" : "textarea[data-draft=notetext]")?.focus({ preventScroll: true });
  const label = type === "translation" ? "Translation deleted" : "Note deleted";
  try {
    await post("delete", { type, id, name: me });
    toast(label, false, { label: "Undo", fn: async () => {
      try { await post("delete", { type, id, name: me, restore: true }); await load({ quiet: true }); toast(type === "translation" ? "Translation restored" : "Note restored"); }
      catch (e) { toast("Couldn't restore: " + e.message, true); }
    }});
  } catch (e) {
    list.splice(idx, 0, item); rerenderCard(sid);
    toast("Not deleted: " + e.message, true);
  }
}

function setFilter(f){ filter = f; store.set(K.filter, f); touched.clear(); render(); window.scrollTo({ top: $("list").offsetTop - 140, behavior: "smooth" }); }
document.querySelectorAll(".seg button").forEach(b => b.addEventListener("click", () => setFilter(b.dataset.f)));
let qt; $("q").addEventListener("input", e => { clearTimeout(qt); qt = setTimeout(() => { q = e.target.value; touched.clear(); render(); }, 120); });
$("section").addEventListener("change", e => { section = e.target.value; touched.clear(); render(); });
document.addEventListener("keydown", e => {
  if (e.key === "/" && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.querySelector("dialog[open]")) { e.preventDefault(); $("q").focus(); }
});
$("nextBtn").onclick = () => {
  const cards = [...document.querySelectorAll(".card")];
  const todoCards = cards.filter(c => isTodo(S.byId.get(Number(c.dataset.sid))));
  if (!todoCards.length) { setFilter("todo"); return; }
  const line = window.innerHeight * 0.3;
  const target = todoCards.find(c => c.getBoundingClientRect().top > line) || todoCards[0];
  target.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
  target.querySelector(".vote, [data-act=compose]")?.focus({ preventScroll: true });
};
$("resyncBtn").onclick = async () => { const b = $("resyncBtn"); b.disabled = true; b.firstElementChild.style.animation = "spin 0.8s linear infinite"; await load({ resync: true, quiet: true }); b.disabled = false; b.firstElementChild.style.animation = ""; };
$("exportBtn").onclick = async () => {
  $("exportText").value = "Building…"; $("exportDlg").showModal();
  try { const r = await fetch(`${API}?action=export`, { cache: "no-store" }); $("exportText").value = await r.text(); }
  catch (e) { $("exportText").value = "Export failed: " + e.message; }
};
$("eClose").onclick = () => $("exportDlg").close();
$("eCopy").onclick = async () => { try { await navigator.clipboard.writeText($("exportText").value); toast("Copied to clipboard"); } catch { $("exportText").select(); document.execCommand("copy"); toast("Copied"); } };
$("eDownload").onclick = () => {
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([$("exportText").value], { type: "text/javascript" }));
  a.download = CFG.export.file_name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
};
const totop = $("totop");
addEventListener("scroll", () => totop.classList.toggle("show", scrollY > 900), { passive: true });
totop.onclick = () => window.scrollTo({ top: 0, behavior: "smooth" });

// Refresh with other translators' changes when coming back to the tab,
// unless you're in the middle of typing.
document.addEventListener("visibilitychange", () => {
  if (document.hidden || document.querySelector("dialog[open]")) return;
  if (composing.size || [...noteDrafts.values()].some(Boolean)) return;
  load({ quiet: true });
});

// ---------- start ----------
async function boot(){
  try {
    const r = await fetch(`${API}?action=config`, { cache: "no-store" });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || "Couldn't load the portal settings");
    applyConfig(d.config);
  } catch (e) {
    document.body.removeAttribute("data-booting");
    $("list").innerHTML = emptyState("alert", "Couldn't start the portal", esc(e.message), `<button class="btn btn-primary" data-act="reload">Try again</button>`);
    return;
  }
  filter = store.get(K.filter) === "all" ? "all" : "todo";
  me = store.get(K.name) || "";
  document.body.removeAttribute("data-booting");
  paintMe();
  load();
  if (!me) openWelcome(true);
}
boot();
})();
