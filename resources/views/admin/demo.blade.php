{{-- Interactive demo for presentations (client request, 2026-11-26): a standalone page with
     fictitious data (fictional hotel « Les Palmiers »), held in the browser only — it reads and
     writes nothing in the database. Public (shareable link), outside the auth group. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<link rel="shortcut icon" href="{{ asset('app/images/favicon.png') }}" type="image/png">
@verbatim
<title>Ekwatech HMS Démo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js"></script>
<style>
/* Layout: the app's « mode simple » — sticky top bar + content, bottom tab bar on phones. Single light theme, like the real app. */
:root {
  color-scheme: light;
  --ek-primary: #2b4a85; --ek-primary-rgb: 43, 74, 133; --ek-primary-700: #1b2f57; --ek-primary-50: #eef3fb; --ek-primary-100: #d6e1f3;
  --ek-gold: #c9a24b; --ek-gold-rgb: 201, 162, 75; --ek-gold-50: #fbf6e9;
  --ek-sidebar-from: #15264a; --ek-sidebar-to: #233c6e;
  --ek-ink: #141c2e; --ek-text: #3c4659; --ek-muted: #737d90; --ek-border: #e2e7ef; --ek-bg: #f3f5f9; --ek-surface: #ffffff; --ek-soft: #f6f8fb;
  --ek-success: #1f9d6b; --ek-success-rgb: 31, 157, 107;
  --ek-danger: #dc4c5a; --ek-danger-rgb: 220, 76, 90;
  --ek-warning: #eea23a; --ek-warning-rgb: 238, 162, 58;
  --ek-info: #3497d3; --ek-info-rgb: 52, 151, 211;
  --ek-secondary: #8794a0; --ek-secondary-rgb: 135, 148, 160;
  --ek-mode-heure: #6d44c2; --ek-mode-journee: #f5c518; --ek-mode-nuitee: #343a96; --ek-mode-sejour: #0f7c76;
  --ek-radius: 14px; --ek-radius-sm: 10px;
  --ek-shadow: 0 1px 2px rgba(15,27,45,.04), 0 4px 16px rgba(15,27,45,.06);
  --ek-shadow-lg: 0 12px 40px rgba(15,27,45,.18);
  --ek-font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --ek-font-display: 'Gilda Display', Georgia, 'Times New Roman', serif;
}
* { box-sizing: border-box; }
html, body { background: var(--ek-bg); }
body { font-family: var(--ek-font); color: var(--ek-text); font-size: 15px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
h1, h2, h3, h4, h5, h6 { color: var(--ek-ink); margin: 0; }
a { color: var(--ek-primary); text-decoration: none; }
a:hover { color: var(--ek-primary-700); }
button { font-family: inherit; }
:focus-visible { outline: 3px solid rgba(var(--ek-primary-rgb), .35); outline-offset: 2px; }
.muted { color: var(--ek-muted); }
.small { font-size: .85rem; }
.nowrap { white-space: nowrap; }
.num { font-variant-numeric: tabular-nums; }
.text-end { text-align: right; }
.text-danger { color: #b53441; }
.fw { font-weight: 700; }

/* Demo strip */
.demo-strip { background: linear-gradient(90deg, var(--ek-sidebar-from), var(--ek-sidebar-to)); color: #fff; font-size: .85rem; }
.demo-strip__in { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; padding-block: 8px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }
.demo-strip__tag { background: var(--ek-gold); color: var(--ek-sidebar-from); font-weight: 800; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; padding: 2px 8px; border-radius: 6px; }
.demo-strip__text { color: rgba(255,255,255,.85); flex: 1 1 260px; }
.demo-strip button { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); color: #fff; border-radius: 8px; padding: 4px 10px; font-size: .8rem; font-weight: 600; cursor: pointer; }
.demo-strip button:hover { background: rgba(255,255,255,.22); }

/* Top bar */
body::before { content: ""; position: fixed; inset: 0 0 auto; height: env(safe-area-inset-top, 0px); z-index: 10; background: var(--ek-surface); }
.topbar { position: sticky; top: env(safe-area-inset-top, 0px); z-index: 50; min-height: 66px; display: flex; align-items: center; gap: 10px; padding-block: 10px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); background: rgba(255,255,255,.9); backdrop-filter: saturate(160%) blur(10px); -webkit-backdrop-filter: saturate(160%) blur(10px); border-bottom: 1px solid var(--ek-border); }
.brand { display: inline-flex; align-items: center; gap: 10px; color: var(--ek-ink); font-family: var(--ek-font-display); font-size: 1.2rem; white-space: nowrap; margin-right: 6px; }
.brand:hover { color: var(--ek-ink); }
.brand__mark { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-family: var(--ek-font); font-size: .8rem; font-weight: 800; color: var(--ek-sidebar-from); background: linear-gradient(135deg, #e7c76f, var(--ek-gold)); }
.nav { display: flex; align-items: center; gap: 4px; min-width: 0; }
.nav a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 13px; border-radius: 10px; color: var(--ek-text); font-weight: 600; font-size: .9rem; white-space: nowrap; }
.nav a:hover { background: var(--ek-primary-50); color: var(--ek-primary); }
.nav a.active { background: var(--ek-primary); color: #fff; }
.nav__sep { width: 1px; height: 24px; background: var(--ek-border); margin: 0 6px; }
.spacer { flex: 1; }
.duty-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; background: rgba(var(--ek-success-rgb), .1); border: 1px solid rgba(var(--ek-success-rgb), .35); color: #13704b; font-size: .8rem; font-weight: 700; white-space: nowrap; }
.user { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--ek-ink); font-size: .9rem; white-space: nowrap; }
.user svg { font-size: 1.5rem; color: var(--ek-primary); }

.tabbar { display: none; }

/* Content */
.content { width: 100%; max-width: 1440px; margin: 0 auto; padding-block: 26px 40px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }
@media (min-width: 900px) { .content { padding-left: max(28px, env(safe-area-inset-left, 0px)); padding-right: max(28px, env(safe-area-inset-right, 0px)); } .topbar { padding-left: max(28px, env(safe-area-inset-left, 0px)); padding-right: max(28px, env(safe-area-inset-right, 0px)); } }
.page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.page-header h1 { font-family: var(--ek-font-display); font-weight: 400; font-size: 1.75rem; display: flex; align-items: center; gap: 10px; text-wrap: balance; }
.page-header h1 svg { color: var(--ek-primary); font-size: 1.3rem; }
.subtitle { color: var(--ek-muted); font-size: .92rem; margin-top: 2px; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; }

.card { background: var(--ek-surface); border: 1px solid var(--ek-border); border-radius: var(--ek-radius); padding: 20px; box-shadow: var(--ek-shadow); }
.card--flush { padding: 0; overflow: hidden; }
.card h5 { font-size: 1.02rem; font-weight: 700; margin-bottom: 14px; }

/* Explainer note (demo only) */
.demo-note { display: flex; gap: 12px; align-items: flex-start; padding: 12px 16px; margin-bottom: 18px; border-radius: 12px; background: var(--ek-gold-50); border: 1px solid rgba(var(--ek-gold-rgb), .45); color: #5d4614; font-size: .9rem; }
.demo-note svg { color: #a47e26; margin-top: 3px; flex-shrink: 0; }
.demo-note strong { color: #3f2f0a; }
.hide-notes .demo-note { display: none; }
.phone-only { display: none; }
@media (max-width: 600px) {
  .hide-phone { display: none; }
  .phone-only { display: inline; }
  .demo-strip__in { flex-wrap: nowrap; gap: 8px; }
  .demo-strip__text { flex: 1 1 auto; min-width: 0; }
  .demo-strip button { padding: 4px 8px; font-size: .74rem; white-space: nowrap; }
  #toggleNotes .lbl-long { display: none; }
}

/* Buttons */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 8px 14px; border-radius: var(--ek-radius-sm); border: 1.5px solid transparent; font-weight: 600; font-size: .9rem; line-height: 1.3; cursor: pointer; background: none; color: var(--ek-ink); white-space: nowrap; transition: background .15s, border-color .15s, color .15s; }
.btn-primary { background: var(--ek-primary); border-color: var(--ek-primary); color: #fff; }
.btn-primary:hover { background: var(--ek-primary-700); border-color: var(--ek-primary-700); color: #fff; }
.btn-success { background: var(--ek-success); border-color: var(--ek-success); color: #fff; }
.btn-success:hover { background: #17845a; color: #fff; }
.btn-danger { background: var(--ek-danger); border-color: var(--ek-danger); color: #fff; }
.btn-outline { border-color: #c9d1dd; background: var(--ek-surface); color: var(--ek-ink); }
.btn-outline:hover { border-color: var(--ek-primary); color: var(--ek-primary); }
.btn-outline-primary { border-color: var(--ek-primary); color: var(--ek-primary); background: var(--ek-surface); }
.btn-outline-primary:hover { background: var(--ek-primary); color: #fff; }
.btn-sm { padding: 4px 10px; font-size: .8rem; border-radius: 8px; }
.btn-lg { padding: 12px 18px; font-size: 1rem; }
.btn-block { width: 100%; }

/* Badges */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: .72rem; font-weight: 700; color: #fff; white-space: nowrap; line-height: 1.3; }
.bg-success { background: var(--ek-success); } .bg-danger { background: var(--ek-danger); } .bg-warning { background: var(--ek-warning); color: #3d2600; }
.bg-info { background: var(--ek-info); } .bg-secondary { background: var(--ek-secondary); } .bg-primary { background: var(--ek-primary); }

/* Forms */
.label { display: block; font-weight: 600; font-size: .85rem; color: var(--ek-ink); margin-bottom: 5px; }
.label span { font-weight: 400; color: var(--ek-muted); }
.input, .select { width: 100%; padding: 8px 11px; border: 1px solid #ccd4e0; border-radius: var(--ek-radius-sm); font: inherit; font-size: .92rem; color: var(--ek-ink); background: var(--ek-surface); }
.input:focus, .select:focus { outline: none; border-color: var(--ek-primary); box-shadow: 0 0 0 3px rgba(var(--ek-primary-rgb), .18); }
.input[readonly] { background: var(--ek-soft); color: var(--ek-muted); }
.input.is-invalid { border-color: var(--ek-danger); }
.form-text { font-size: .78rem; color: var(--ek-muted); margin-top: 4px; }
.grid { display: grid; gap: 12px; }
.g2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.section-label { text-transform: uppercase; color: var(--ek-muted); font-size: .75rem; font-weight: 800; letter-spacing: .06em; margin: 0 0 10px; }
.alert { padding: 10px 14px; border-radius: 10px; font-size: .88rem; }
.alert-danger { background: rgba(var(--ek-danger-rgb), .1); color: #8f2632; border: 1px solid rgba(var(--ek-danger-rgb), .3); }
.alert-info { background: rgba(var(--ek-info-rgb), .1); color: #1d5f88; border: 1px solid rgba(var(--ek-info-rgb), .3); }

/* Mode buttons */
.modes { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 6px; }
.mode-btn { border: 1.5px solid #c9d1dd; background: var(--ek-surface); border-radius: 10px; padding: 9px 8px; font-weight: 600; font-size: .86rem; color: var(--ek-ink); cursor: pointer; display: inline-flex; gap: 6px; align-items: center; justify-content: center; }
.mode-btn svg { color: var(--ek-muted); }
.mode-btn:hover { background: var(--ek-bg); }
.mode-btn.is-on { color: #fff; border-color: var(--m); background: var(--m); box-shadow: 0 8px 18px -8px var(--m); }
.mode-btn.is-on svg { color: inherit; }
.mode-btn.is-on.mode--journee { color: #2e2300; }
.mode--heure { --m: var(--ek-mode-heure); } .mode--journee { --m: var(--ek-mode-journee); } .mode--nuitee { --m: var(--ek-mode-nuitee); } .mode--sejour { --m: var(--ek-mode-sejour); }
.mode-icon.mode--heure { color: var(--ek-mode-heure); } .mode-icon.mode--journee { color: #a87b00; } .mode-icon.mode--nuitee { color: var(--ek-mode-nuitee); } .mode-icon.mode--sejour { color: var(--ek-mode-sejour); }
.mode-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 6px; font-size: .75rem; font-weight: 700; background: var(--m); color: #fff; }
.mode-badge.mode--journee { color: #2e2300; }

/* Home */
.hello { margin-bottom: 20px; }
.hello h1 { font-family: var(--ek-font-display); font-weight: 400; font-size: 1.9rem; }
.home-layout { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; align-items: start; }
.home-layout > * { min-width: 0; }
.home-side { position: sticky; top: calc(env(safe-area-inset-top, 0px) + 84px); }
@media (max-width: 1099.98px) { .home-layout { grid-template-columns: minmax(0, 1fr); } .home-side { position: static; } }

.my-cash { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; padding: 18px 20px; margin-bottom: 18px; border-radius: var(--ek-radius); background: linear-gradient(135deg, var(--ek-sidebar-from), var(--ek-sidebar-to)); color: #fff; }
.my-cash__label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.75); }
.my-cash__label svg { color: var(--ek-gold); margin-right: 4px; }
.my-cash__value { font-size: 1.9rem; font-weight: 800; line-height: 1.15; font-variant-numeric: tabular-nums; display: block; }
.my-cash__value small { font-size: .9rem; font-weight: 600; color: rgba(255,255,255,.7); }
.my-cash__hint { font-size: .85rem; color: rgba(255,255,255,.72); display: block; }
.my-cash__sub { font-size: .82rem; color: rgba(255,255,255,.85); display: block; margin-top: 2px; }
.my-cash__sub strong { color: #fff; }
.my-cash__methods { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.my-cash__methods li { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,.1); font-size: .82rem; min-width: 0; }
.my-cash__methods li strong { font-size: 1.05rem; font-variant-numeric: tabular-nums; }
.my-cash__methods li.is-empty { opacity: .55; }
.my-cash__foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; font-size: .85rem; color: rgba(255,255,255,.85); }
.my-cash__foot strong { color: #fff; }
.btn-light { background: #fff; color: var(--ek-ink); border-color: #fff; }

.stat { display: flex; flex-direction: column; gap: 2px; padding: 16px 18px; border-radius: var(--ek-radius); background: var(--ek-surface); border: 1px solid var(--ek-border); box-shadow: var(--ek-shadow); margin-bottom: 18px; }
.stat__label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ek-muted); }
.stat__label svg { margin-right: 4px; color: var(--ek-primary); }
.stat__value { font-size: 1.55rem; font-weight: 800; color: var(--ek-ink); font-variant-numeric: tabular-nums; }
.stat__value small { font-size: .8rem; color: var(--ek-muted); font-weight: 600; }
.stat__hint { font-size: .8rem; color: var(--ek-muted); }
.occ-bar { height: 8px; border-radius: 4px; background: var(--ek-bg); overflow: hidden; margin-top: 8px; }
.occ-bar span { display: block; height: 100%; background: var(--ek-danger); border-radius: 4px; transition: width .4s ease; }

.tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
@container (max-width: 520px) {
  .tiles { grid-template-columns: minmax(0, 1fr); gap: 10px; }
  .tile { display: grid; grid-template-columns: auto minmax(0, 1fr); column-gap: 14px; row-gap: 2px; align-items: center; min-height: 0; padding: 14px 16px; border-top-width: 1px; border-left: 4px solid var(--c); }
  .tile__icon { grid-row: 1 / 3; margin: 0; }
  .tile__label { font-size: 1rem; }
  .tile__count { font-size: .85rem; }
}
.home-main { container-type: inline-size; }
.tile { --c: var(--ek-primary); --c-rgb: var(--ek-primary-rgb); display: flex; flex-direction: column; gap: 6px; min-height: 150px; padding: 20px; border-radius: var(--ek-radius); background: var(--ek-surface); border: 1px solid var(--ek-border); border-top: 4px solid var(--c); box-shadow: var(--ek-shadow); color: var(--ek-ink); cursor: pointer; text-align: left; font: inherit; transition: transform .15s, box-shadow .15s; }
.tile:hover { transform: translateY(-2px); box-shadow: var(--ek-shadow-lg); color: var(--ek-ink); }
.tile__icon { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 1.2rem; color: var(--c); background: rgba(var(--c-rgb), .12); margin-bottom: 6px; }
.tile__label { font-size: 1.1rem; font-weight: 800; }
.tile__count { color: var(--ek-muted); font-weight: 500; font-size: .9rem; }
.tile--success { --c: var(--ek-success); --c-rgb: var(--ek-success-rgb); }
.tile--warning { --c: var(--ek-warning); --c-rgb: var(--ek-warning-rgb); }
.tile--danger { --c: var(--ek-danger); --c-rgb: var(--ek-danger-rgb); }
.tile--info { --c: var(--ek-info); --c-rgb: var(--ek-info-rgb); }
.tile--gold { --c: var(--ek-gold); --c-rgb: var(--ek-gold-rgb); }

.home-rooms { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; margin-top: 18px; padding: 14px 18px; border-radius: var(--ek-radius); background: var(--ek-surface); border: 1px solid var(--ek-border); }
.home-rooms__title { font-weight: 800; color: var(--ek-ink); }
.home-rooms__item { display: inline-flex; align-items: center; gap: 6px; color: var(--ek-text); background: none; border: 0; padding: 0; cursor: pointer; font: inherit; }
.home-rooms__item:hover { color: var(--ek-primary); }
.home-rooms__item strong { color: var(--ek-ink); }

.home-services { margin-top: 18px; }
.svc-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
@container (max-width: 520px) { .svc-row { grid-template-columns: minmax(0, 1fr); } }
.svc { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; background: var(--ek-surface); border: 1px solid var(--ek-border); color: var(--ek-ink); }
.svc:hover { border-color: var(--ek-primary-100); color: var(--ek-ink); box-shadow: var(--ek-shadow); }
.svc__icon { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: var(--ek-primary-50); color: var(--ek-primary); flex-shrink: 0; }
.svc__name { font-weight: 700; font-size: .92rem; display: block; }
.svc__meta { font-size: .8rem; color: var(--ek-muted); display: block; }

.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot--danger { background: var(--ek-danger); } .dot--success { background: var(--ek-success); } .dot--info { background: var(--ek-info); }
.dot--warning { background: var(--ek-warning); } .dot--secondary { background: var(--ek-secondary); } .dot--primary { background: var(--ek-primary); }

.free { padding: 16px 18px; border-radius: var(--ek-radius); background: var(--ek-surface); border: 1px solid var(--ek-border); border-left: 4px solid var(--ek-success); }
.free__head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 12px; margin-bottom: 10px; }
.free__title { font-size: 1.05rem; font-weight: 800; }
.free__title svg { color: var(--ek-success); margin-right: 4px; }
.free__count { font-weight: 700; color: var(--ek-success); }
.free__all { margin-left: auto; font-size: .875rem; font-weight: 600; background: none; border: 0; color: var(--ek-primary); cursor: pointer; padding: 0; }
.free__type { padding: 10px 0; border-top: 1px solid var(--ek-border); }
.free__type-name { font-weight: 700; color: var(--ek-ink); margin-bottom: 6px; }
.free__rate { display: block; font-size: .8rem; font-weight: 500; color: var(--ek-muted); }
.free__rooms { display: flex; flex-wrap: wrap; gap: 6px; }
.free__room { min-width: 72px; padding: 10px 12px; border-radius: 8px; text-align: center; font-weight: 700; font-size: 1.02rem; color: var(--ek-success); background: rgba(var(--ek-success-rgb), .1); border: 1px solid rgba(var(--ek-success-rgb), .3); cursor: pointer; font-family: inherit; }
.free__room:hover, .free__room:focus-visible { background: var(--ek-success); color: #fff; }
.free__empty { margin: 0; color: var(--ek-muted); }

/* Rooms board */
.rate-grid { margin-bottom: 18px; padding: 14px 20px; }
.rate-grid summary { cursor: pointer; font-weight: 700; color: var(--ek-ink); list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.rate-grid summary::-webkit-details-marker { display: none; }
.rate-grid summary::after { content: "Afficher"; margin-left: auto; font-size: .8rem; color: var(--ek-primary); font-weight: 600; }
.rate-grid[open] summary::after { content: "Masquer"; }
.room-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.room-tab { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px; background: var(--ek-surface); border: 1px solid var(--ek-border); box-shadow: var(--ek-shadow); font-size: .86rem; font-weight: 600; color: var(--ek-text); cursor: pointer; }
.room-tab strong { color: var(--ek-ink); }
.room-tab:hover { border-color: var(--ek-primary-100); color: var(--ek-primary-700); }
.room-tab.is-active { background: var(--ek-primary); border-color: var(--ek-primary); color: #fff; box-shadow: 0 8px 18px -8px rgba(var(--ek-primary-rgb), .9); }
.room-tab.is-active strong { color: #fff; }
.room-tab.is-active .dot { box-shadow: 0 0 0 2px rgba(255,255,255,.85); }
.room-tab:disabled { opacity: .5; cursor: default; box-shadow: none; }
.type-heading { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 12px; margin: 18px 0 10px; font-size: 1rem; font-weight: 800; }
.type-heading:first-child { margin-top: 0; }
.type-heading__count { font-size: .8rem; font-weight: 700; color: var(--ek-success); }
.type-heading__rate { font-size: .8rem; font-weight: 500; color: var(--ek-muted); }
.board { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
.room { --rc: var(--ek-secondary); --rc-rgb: var(--ek-secondary-rgb); position: relative; overflow: hidden; background: var(--ek-surface); border: 1px solid var(--ek-border); border-radius: var(--ek-radius); padding: 18px 18px 16px; box-shadow: var(--ek-shadow); transition: transform .18s, box-shadow .18s, border-color .18s; }
.room::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 5px; background: var(--rc); }
.room::after { content: ''; position: absolute; right: -26px; bottom: -26px; width: 90px; height: 90px; border-radius: 50%; background: rgba(var(--rc-rgb), .08); pointer-events: none; }
.room > * { position: relative; z-index: 1; }
.room--libre { --rc: var(--ek-success); --rc-rgb: var(--ek-success-rgb); }
.room--occupee { --rc: var(--ek-danger); --rc-rgb: var(--ek-danger-rgb); }
.room--nettoyage { --rc: var(--ek-warning); --rc-rgb: var(--ek-warning-rgb); }
.room--reservee { --rc: var(--ek-info); --rc-rgb: var(--ek-info-rgb); }
.room--hors_service { --rc: var(--ek-secondary); --rc-rgb: var(--ek-secondary-rgb); }
.room.is-clickable { cursor: pointer; }
.room.is-clickable:hover { transform: translateY(-4px); border-color: rgba(var(--rc-rgb), .45); box-shadow: 0 16px 32px -14px rgba(var(--rc-rgb), .55); }
.room.is-clickable:hover .room__number { color: var(--rc); }
.room__number { font-size: 1.45rem; font-weight: 800; color: var(--ek-ink); letter-spacing: -.01em; transition: color .15s; }
.room__type { font-size: .8rem; color: var(--ek-muted); margin-bottom: 10px; }
.room__stay { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--ek-border); font-size: .8rem; }
.room__time { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; line-height: 1.75; }
.room__time svg { width: 14px; color: var(--ek-muted); }
.room__time strong { color: var(--ek-ink); font-variant-numeric: tabular-nums; }
.room__time.is-overdue strong, .room__time.is-overdue svg { color: #b53441; }
.room__links { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px; margin-top: 10px; font-size: .82rem; font-weight: 600; }
.room__rates { list-style: none; margin: 10px 0 0; padding: 8px 0 0; border-top: 1px dashed var(--ek-border); display: grid; gap: 3px; font-size: .8rem; }
.room__rates li { display: flex; justify-content: space-between; gap: 8px; }
.room__rates strong { color: var(--ek-ink); white-space: nowrap; }
.room__note { margin-top: 10px; font-size: .8rem; color: var(--ek-muted); }

/* Tables */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: .9rem; }
th { text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--ek-muted); font-weight: 700; padding: 12px 16px; background: var(--ek-soft); border-bottom: 1px solid var(--ek-border); white-space: nowrap; }
td { padding: 12px 16px; border-bottom: 1px solid var(--ek-border); vertical-align: middle; }
tbody tr:last-child td { border-bottom: 0; }
tbody tr:hover td { background: #fafbfd; }
.file-ref { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .78rem; font-weight: 700; color: var(--ek-ink); white-space: nowrap; }
.rate-table th, .rate-table td { padding: 10px 12px; }

.kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.kpi { padding: 16px 18px; }
.kpi__label { font-size: .82rem; color: var(--ek-muted); }
.kpi__value { font-size: 1.45rem; font-weight: 800; color: var(--ek-ink); font-variant-numeric: tabular-nums; }
.kpi__value small { font-size: .8rem; color: var(--ek-muted); font-weight: 600; }

.tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--ek-border); margin-bottom: 16px; overflow-x: auto; }
.tabs button { background: none; border: 0; border-bottom: 3px solid transparent; padding: 10px 14px; font-weight: 600; font-size: .9rem; color: var(--ek-muted); cursor: pointer; white-space: nowrap; }
.tabs button:hover { color: var(--ek-ink); }
.tabs button.is-active { color: var(--ek-primary); border-bottom-color: var(--ek-primary); }
.tabs button span { font-size: .75rem; background: var(--ek-bg); border-radius: 999px; padding: 1px 7px; margin-left: 4px; color: var(--ek-text); }
.alert-warning { background: rgba(var(--ek-warning-rgb), .14); color: #6b4300; border: 1px solid rgba(var(--ek-warning-rgb), .45); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 16px; }
.btn-warning { background: var(--ek-warning); border-color: var(--ek-warning); color: #3d2600; }
.btn-warning:hover { background: #d98e26; color: #3d2600; }
.btn-outline-danger { border-color: rgba(var(--ek-danger-rgb), .6); color: #b53441; background: var(--ek-surface); }
.btn-outline-danger:hover { background: var(--ek-danger); color: #fff; }
.row-actions { display: inline-flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
.filter-row { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 16px; border-bottom: 1px solid var(--ek-border); }
.filter-row .input { width: auto; max-width: 200px; }
.req { padding: 12px 0; border-top: 1px solid var(--ek-border); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; align-items: center; }
.req:first-child { border-top: 0; padding-top: 0; }

/* Sale form */
.sale-layout { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 22px; align-items: start; }
.sale-layout > * { min-width: 0; }
.sale-side { position: sticky; top: calc(env(safe-area-inset-top, 0px) + 84px); }
.cat-title { text-transform: uppercase; color: var(--ek-muted); font-size: .75rem; font-weight: 800; letter-spacing: .06em; margin: 18px 0 8px; }
.cat-title:first-of-type { margin-top: 0; }
.sale-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
.sale-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border: 1px solid var(--ek-border); border-radius: 10px; background: var(--ek-surface); transition: border-color .15s, background .15s; }
.sale-item.is-picked { border-color: rgba(var(--ek-primary-rgb), .5); background: var(--ek-primary-50); }
.sale-item__name { display: flex; flex-direction: column; font-weight: 600; font-size: .88rem; color: var(--ek-ink); min-width: 0; overflow-wrap: anywhere; }
.sale-item__price { font-weight: 400; font-size: .82rem; color: var(--ek-muted); }
.qty { display: flex; flex-shrink: 0; border: 1px solid #ccd4e0; border-radius: 8px; overflow: hidden; background: var(--ek-surface); }
.qty button { width: 32px; border: 0; background: var(--ek-soft); font-size: 1rem; font-weight: 700; cursor: pointer; color: var(--ek-ink); }
.qty button:hover { background: var(--ek-primary-50); color: var(--ek-primary); }
.qty input { width: 42px; border: 0; text-align: center; font: inherit; font-weight: 700; font-size: .9rem; color: var(--ek-ink); padding: 5px 0; -moz-appearance: textfield; }
.qty input::-webkit-inner-spin-button { -webkit-appearance: none; }
.pay-box { padding: 16px; border-radius: var(--ek-radius); background: var(--ek-soft); border: 1px solid var(--ek-border); }
.pay-total { font-size: 1.05rem; font-weight: 700; color: var(--ek-ink); padding: 10px 14px; border-left: 4px solid var(--pay, var(--ek-primary)); background: var(--ek-surface); border-radius: 8px; margin-bottom: 14px; }
.pay-total small { display: block; font-weight: 500; color: var(--ek-muted); font-size: .82rem; }
.pay-amount { display: inline-block; padding: 1px 9px; border-radius: 7px; background: var(--pay, var(--ek-primary)); color: var(--pay-on, #fff); white-space: nowrap; font-variant-numeric: tabular-nums; }
.sale-lines { list-style: none; margin: 0 0 12px; padding: 0; font-size: .85rem; display: grid; gap: 4px; }
.sale-lines li { display: flex; justify-content: space-between; gap: 8px; }

/* Modal */
.modal-root { position: fixed; inset: 0; z-index: 200; display: grid; place-items: center; padding: 16px; padding-top: calc(16px + env(safe-area-inset-top, 0px)); padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px)); background: rgba(15, 27, 45, .5); }
.modal { width: 100%; max-width: 560px; max-height: 100%; display: flex; flex-direction: column; background: var(--ek-surface); border-radius: 16px; box-shadow: var(--ek-shadow-lg); overflow: hidden; animation: pop .18s ease; }
.modal--xl { max-width: 1040px; }
.modal--sm { max-width: 400px; }
@keyframes pop { from { transform: translateY(8px) scale(.98); opacity: .6; } to { transform: none; opacity: 1; } }
.modal__head { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--ek-border); }
.modal__title { font-size: 1.1rem; font-weight: 800; flex: 1; }
.modal__close { background: none; border: 0; font-size: 1.2rem; color: var(--ek-muted); cursor: pointer; width: 34px; height: 34px; border-radius: 8px; }
.modal__close:hover { background: var(--ek-bg); color: var(--ek-ink); }
.modal__body { padding: 20px; overflow-y: auto; }
.modal__foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--ek-border); background: var(--ek-soft); }
.assign-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.assign-grid > * { min-width: 0; }
.dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px 16px; font-size: .9rem; margin: 0; }
.dl dt { color: var(--ek-muted); }
.dl dd { margin: 0; font-weight: 600; color: var(--ek-ink); }

/* Receipt */
.receipt { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .8rem; color: #111; background: #fff; border: 1px dashed #b8c0cc; border-radius: 6px; padding: 18px 16px; max-width: 300px; margin: 0 auto; }
.receipt__center { text-align: center; }
.receipt__name { font-size: 1rem; font-weight: 700; }
.receipt hr { border: 0; border-top: 1px dashed #999; margin: 10px 0; }
.receipt__row { display: flex; justify-content: space-between; gap: 8px; }
.receipt__total { font-size: .95rem; font-weight: 700; }
.receipt-wrap { background: #e9edf3; padding: 18px; }

/* Toasts */
.toasts { position: fixed; z-index: 300; right: max(16px, env(safe-area-inset-right, 0px)); bottom: calc(16px + env(safe-area-inset-bottom, 0px)); display: grid; gap: 8px; max-width: min(380px, calc(100vw - 32px)); }
.toast { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: var(--ek-ink); color: #fff; box-shadow: var(--ek-shadow-lg); font-size: .9rem; animation: pop .2s ease; }
.toast svg { color: #6fe0b2; margin-top: 3px; }

/* Mobile cards: under 900 px they replace the tables (one card per row, built for each list) */
.mob-only { display: none; }
.m-list { gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 330px), 1fr)); align-items: start; }
.m-card { display: flex; flex-direction: column; gap: 10px; min-width: 0; padding: 14px 16px; background: var(--ek-surface); border: 1px solid var(--ek-border); border-radius: var(--ek-radius); box-shadow: var(--ek-shadow); }
.m-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.m-card__head > div { min-width: 0; }
.m-card__title { font-weight: 700; font-size: 1rem; color: var(--ek-ink); overflow-wrap: anywhere; line-height: 1.35; }
.m-card__sub { font-size: .8rem; color: var(--ek-muted); margin-top: 2px; overflow-wrap: anywhere; }
.m-card__amount { font-weight: 800; color: var(--ek-ink); white-space: nowrap; font-variant-numeric: tabular-nums; }
.m-card__meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 14px; padding: 10px 0; border-top: 1px dashed var(--ek-border); border-bottom: 1px dashed var(--ek-border); font-size: .86rem; }
.m-card__meta > div { min-width: 0; }
.m-k { display: block; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ek-muted); margin-bottom: 1px; }
.m-v { color: var(--ek-ink); font-weight: 600; overflow-wrap: anywhere; }
.m-v .small { font-weight: 400; }
.m-card__line { font-size: .85rem; color: var(--ek-text); overflow-wrap: anywhere; }
.m-card__foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; }
.m-card__actions { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
.m-card__actions .btn { flex: 1 1 0; padding-block: 9px; }
.m-badges { display: flex; flex-wrap: wrap; gap: 4px; }
.m-empty { padding: 28px 16px; text-align: center; color: var(--ek-muted); background: var(--ek-surface); border: 1px dashed var(--ek-border); border-radius: var(--ek-radius); }
.m-rows { display: grid; }
.m-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--ek-border); font-size: .88rem; }
.m-row:last-child { border-bottom: 0; }
.m-row > div { min-width: 0; overflow-wrap: anywhere; }
.m-row strong { white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--ek-ink); }
.rate-cards { gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr)); margin-top: 12px; }
.rate-card { padding: 12px 14px; border: 1px solid var(--ek-border); border-radius: 12px; background: var(--ek-soft); }
.rate-card .room__rates { border-top-style: solid; }
.res-filter { margin-bottom: 12px; }
.res-filter .input { max-width: 220px; }
.sale-bar { display: none; }
.sale-bar__info { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.sale-bar__info span { font-size: .78rem; color: var(--ek-muted); }
.sale-bar__info strong { font-size: 1.15rem; color: var(--ek-ink); font-variant-numeric: tabular-nums; }

/* Small laptops and tablets in landscape: compact top bar instead of an overflowing one */
@media (max-width: 1360px) { .user__name, .duty-pill__label { display: none; } }
@media (max-width: 1240px) {
  .brand__name { display: none; }
  .nav a { padding: 8px 10px; font-size: .85rem; gap: 6px; }
  .nav__sep { margin: 0 2px; }
}
/* Tablets and phones: the bottom tab bar replaces the top links */
@media (max-width: 1024px) {
  .nav { display: none; }
  .brand__name { display: inline; }
  .duty-pill { max-width: 46vw; overflow: hidden; text-overflow: ellipsis; }
  .tabbar { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); position: fixed; z-index: 60; left: 0; right: 0; bottom: 0; background: var(--ek-surface); border-top: 1px solid var(--ek-border); box-shadow: 0 -6px 20px rgba(15,27,45,.08); padding-bottom: env(safe-area-inset-bottom, 0px); padding-left: env(safe-area-inset-left, 0px); padding-right: env(safe-area-inset-right, 0px); }
  .tabbar a { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 2px 9px; font-size: .74rem; font-weight: 700; color: var(--ek-muted); }
  .tabbar a svg { font-size: 1.2rem; }
  .tabbar a.active { color: var(--ek-primary); }
  .content { padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px)); }
  .toasts { bottom: calc(80px + env(safe-area-inset-bottom, 0px)); }
}
/* Phones and tablets in portrait: cards instead of tables, one column of content */
@media (max-width: 899.98px) {
  .desk-only { display: none !important; }
  .mob-only { display: grid; }
  .assign-grid, .sale-layout { grid-template-columns: minmax(0, 1fr); }
  .sale-side { position: static; }
  .page-header h1 { font-size: 1.5rem; }
  .hide-phone { display: none; }
  .sale-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; position: fixed; z-index: 55; left: 0; right: 0; bottom: calc(60px + env(safe-area-inset-bottom, 0px)); padding: 10px max(16px, env(safe-area-inset-right, 0px)) 10px max(16px, env(safe-area-inset-left, 0px)); background: var(--ek-surface); border-top: 1px solid var(--ek-border); box-shadow: 0 -6px 20px rgba(15,27,45,.08); }
  .sale-bar .btn { padding: 11px 18px; }
  .sale-bar-spacer { height: 72px; }
}
@media (max-width: 600px) {
  .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
  .kpis > :last-child:nth-child(odd) { grid-column: 1 / -1; }
  .kpi { padding: 12px 14px; }
  .kpi__value { font-size: 1.2rem; }
  .page-header { margin-bottom: 16px; }
  .page-header .actions, .page-header > .btn { width: 100%; }
  .page-header .actions .btn { flex: 1 1 auto; }
  .card { padding: 16px; }
  .card--flush { padding: 0; }
  .hello h1 { font-size: 1.6rem; }
  .demo-strip { font-size: .78rem; }
  .demo-note { padding: 10px 12px; font-size: .84rem; }
  .modal__foot { padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px)); }
  .modal__foot .btn { flex: 1 1 auto; }
  .modal__body { padding: 16px; }
  .alert-warning .btn { width: 100%; }
  .room-tabs { flex-wrap: nowrap; overflow-x: auto; margin-inline: -4px; padding: 4px 4px 8px; scrollbar-width: none; }
  .room-tab { flex-shrink: 0; }
  .res-filter .input { max-width: none; }
  .modes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .mode-btn { white-space: nowrap; }
}
@media (max-width: 560px) {
  .tabbar a { font-size: .62rem; padding: 9px 1px 8px; gap: 3px; }
  .tabbar a svg { font-size: 1.12rem; }
  .sale-bar { bottom: calc(54px + env(safe-area-inset-bottom, 0px)); }
  .my-cash { padding: 16px; }
  .my-cash__value { font-size: 1.6rem; }
  .my-cash__methods { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
  .my-cash__methods li { padding: 8px; font-size: .7rem; }
  .my-cash__methods li strong { font-size: .88rem; }
  .board { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
  .room { padding: 14px 12px 12px; }
  .room__number { font-size: 1.25rem; }
  .room__rates { font-size: .74rem; }
  .room__rates li { flex-wrap: wrap; column-gap: 4px; }
  .room__links { flex-direction: column; align-items: stretch; }
  .room__links .btn { justify-content: center; }
  .g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .brand__name { display: none; }
  .modal-root { padding: 0; }
  .modal { max-width: none; height: 100%; border-radius: 0; }
  .free__room { min-width: 0; flex: 1 0 64px; }
}
@media (max-width: 360px) {
  .my-cash__methods { grid-template-columns: minmax(0, 1fr); }
  .m-card__meta { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
/* Reset (standalone page) */
body { margin: 0; } img { max-width: 100%; } [hidden] { display: none !important; }
</style>
@endverbatim
</head>
<body>
@verbatim

<div class="demo-strip">
  <div class="demo-strip__in">
    <span class="demo-strip__tag">Démo</span>
    <span class="demo-strip__text"><span class="hide-phone">Hôtel fictif et données d'exemple. Vous êtes connecté en tant que réceptionniste ; rien de ce que vous faites n'est enregistré.</span><span class="phone-only">Données fictives, rien n'est enregistré.</span></span>
    <button type="button" id="toggleNotes" title="Masquer ou afficher les explications"><i class="fas fa-circle-info"></i> <span id="toggleNotesLbl">Masquer les explications</span></button>
    <button type="button" id="resetDemo" title="Réinitialiser la démo"><i class="fas fa-rotate-left"></i><span class="hide-phone"> Réinitialiser</span></button>
  </div>
</div>

<header class="topbar">
  <a href="#accueil" class="brand"><span class="brand__mark">LP</span><span class="brand__name">Hôtel Les Palmiers</span></a>
  <nav class="nav" aria-label="Navigation principale" id="nav"></nav>
  <div class="spacer"></div>
  <span class="duty-pill" title="Réceptionniste de service : les encaissements lui sont attribués"><i class="fas fa-user-clock"></i><span><span class="duty-pill__label">De service : </span>Aïcha Koné<span class="duty-pill__label" id="dutySince"></span></span></span>
  <span class="user"><i class="fas fa-circle-user"></i><span class="user__name">Aïcha Koné</span></span>
</header>

<main class="content" id="app"></main>
<nav class="tabbar" aria-label="Navigation rapide" id="tabbar"></nav>
<div id="modalHost"></div>
<div class="toasts" id="toasts" aria-live="polite"></div>

<script>
(function () {
'use strict';

/* ------------------------------------------------------------ helpers */
var $ = function (s, r) { return (r || document).querySelector(s); };
var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
function fcfa(n) { return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
function pad(n) { return (n < 10 ? '0' : '') + n; }
function hm(d) { return pad(d.getHours()) + 'h' + pad(d.getMinutes()); }
function dm(d) { return pad(d.getDate()) + '/' + pad(d.getMonth() + 1); }
function dmy(d) { return dm(d) + '/' + d.getFullYear(); }
function dmhm(d) { return dm(d) + ' ' + hm(d); }
function addH(d, h) { return new Date(d.getTime() + h * 3600000); }
function addMin(d, m) { return new Date(d.getTime() + m * 60000); }
function atTime(d, h, m, dayOffset) { var x = new Date(d); x.setDate(x.getDate() + (dayOffset || 0)); x.setHours(h, m || 0, 0, 0); return x; }
function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }
function longDate(d) { return cap(d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })); }
function shortDay(d) { return d.toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', '') + ' ' + dm(d); }
function store(key, val) { try { if (val === undefined) { return localStorage.getItem(key); } localStorage.setItem(key, val); } catch (e) { return null; } }

/* ------------------------------------------------------------ reference data */
var MODES = {
  heure:   { label: 'Passage', icon: 'fa-clock', unit: '/ heure', units: 'heures' },
  journee: { label: 'Journée (6 h)', icon: 'fa-sun', unit: '/ journée', units: 'journées (6 h)' },
  nuitee:  { label: 'Nuitée', icon: 'fa-moon', unit: '/ nuit', units: 'nuits' },
  sejour:  { label: 'Séjour (24 h)', icon: 'fa-calendar-days', unit: '/ 24 h', units: 'périodes de 24 h' }
};
var MODE_ORDER = ['sejour']; // demo configured with the « Séjour » formula only
var METHODS = { especes: { label: 'Espèces', icon: 'fa-money-bill-wave' }, orange_money: { label: 'Orange Money', icon: 'fa-mobile-screen' }, wave: { label: 'Wave', icon: 'fa-mobile-screen' } };
var TYPES = {
  std: { name: 'Chambre Standard', adults: 2, children: 0, rates: { sejour: 20000 } },
  dlx: { name: 'Chambre Deluxe', adults: 2, children: 1, until: '12h00', rates: { sejour: 35000 } },
  ste: { name: 'Suite Signature', adults: 2, children: 2, until: '12h00', rates: { sejour: 60000 } },
  fam: { name: 'Chambre Familiale', adults: 4, children: 2, rates: { sejour: 55000 } }
};
function modeLabel(mode, type) { return mode === 'sejour' && type && type.until ? "Séjour (jusqu'à " + type.until + ')' : MODES[mode].label; }
function modeUnit(mode, type) { return mode === 'sejour' && type && type.until ? '/ séjour' : MODES[mode].unit; }
function lowestRate(type) { var best = null; MODE_ORDER.forEach(function (m) { var r = type.rates[m]; if (r != null && (!best || r < best[1])) { best = [m, r]; } }); return best; }
function checkout(mode, dur, from, type) {
  if (mode === 'heure') { return addH(from, dur); }
  if (mode === 'journee') { return addH(from, 6 * dur); }
  if (mode === 'nuitee') { return atTime(from, 12, 0, (from.getHours() < 12 ? 0 : 1) + dur - 1); }
  if (type && type.until) { return atTime(from, 12, 0, dur); }
  return addH(from, 24 * dur);
}
function durationLabel(mode, dur, type) {
  var p = dur > 1;
  if (mode === 'heure') { return dur + (p ? ' heures' : ' heure'); }
  if (mode === 'journee') { return dur + (p ? ' journées' : ' journée'); }
  if (mode === 'nuitee') { return dur + (p ? ' nuits' : ' nuit'); }
  return type && type.until ? dur + (p ? ' séjours' : ' séjour') + ' (sortie à 12h00)' : dur + (p ? ' périodes' : ' période') + ' de 24 h';
}

var CATALOG = {
  restauration: [
    ['Petit-déjeuner', 'Petit-déjeuner continental', 3500], ['Petit-déjeuner', 'Petit-déjeuner ivoirien', 4000],
    ['Entrées', 'Salade composée', 2500], ['Entrées', 'Alloco sauce piment', 1500],
    ['Plats', 'Attiéké poisson braisé', 5000], ['Plats', 'Poulet braisé, attiéké ou alloco', 6000], ['Plats', 'Kedjenou de poulet', 6500],
    ['Plats', 'Foutou sauce graine', 5500], ['Plats', 'Garba', 2000], ['Plats', 'Brochettes de bœuf', 4000],
    ['Desserts', 'Salade de fruits', 1500], ['Desserts', 'Dêguê', 1000],
    ['Boissons', 'Eau minérale 1,5 L', 1000], ['Boissons', 'Jus de bissap', 1000], ['Boissons', 'Jus de gingembre', 1000],
    ['Boissons', 'Soda (Coca, Fanta, Sprite)', 1000], ['Boissons', 'Bière (Flag, Bock)', 1500], ['Boissons', 'Café ou thé', 500], ['Boissons', 'Cocktail sans alcool', 2000]
  ],
  plage: [
    ['Accès', 'Entrée adulte (journée)', 3000, true], ['Accès', 'Entrée enfant (journée)', 1500, true], ['Accès', 'Entrée client hébergé', 0, true],
    ['Locations', 'Transat', 1000], ['Locations', 'Serviette de bain', 500], ['Locations', 'Parasol', 1000], ['Locations', 'Bouée et brassards', 500],
    ['Activités', 'Cours de natation (1 h)', 5000], ['Activités', 'Aquagym (séance)', 3000]
  ],
  evenements: [
    ['Matériel', 'Vidéoprojecteur et écran', 15000], ['Matériel', 'Sonorisation et micros', 20000], ['Matériel', 'Paperboard et marqueurs', 5000],
    ['Matériel', 'Connexion Wi-Fi dédiée', 10000], ['Services', 'Installation de la salle sur mesure', 25000], ['Services', 'Décoration de salle', 50000], ['Services', "Hôtesse d'accueil (journée)", 15000]
  ]
};
var ITEMS = {};
Object.keys(CATALOG).forEach(function (dept) { CATALOG[dept] = CATALOG[dept].map(function (r, i) { var it = { id: dept + '-' + i, dept: dept, cat: r[0], name: r[1], price: r[2], entry: !!r[3] }; ITEMS[it.id] = it; return it; }); });
var VENUES = { baobab: { name: 'Salle Baobab', capacity: 80, half: 100000, day: 180000 }, kapok: { name: 'Salle Kapok', capacity: 20, half: 35000, day: 60000 } };
var DEPTS = {
  restauration: { label: 'Restauration', icon: 'fa-utensils', route: 'restauration' },
  plage: { label: 'Plage et piscine', icon: 'fa-person-swimming', route: 'plage' },
  evenements: { label: 'Réunions et événements', icon: 'fa-briefcase', route: 'evenements' }
};

/* ------------------------------------------------------------ demo state */
var S, NOW, DAY_START;
function seed() {
  NOW = new Date();
  DAY_START = atTime(NOW, 6, 0, NOW.getHours() < 6 ? -1 : 0);
  var ago = function (min) { var d = addMin(NOW, -min); return d < DAY_START ? addMin(DAY_START, 5 + (min % 50)) : d; };
  var seq = { invoice: 412, receipt: 1287 };
  var payments = [];
  function pay(amount, method, dept, label, at) { payments.push({ amount: amount, method: method, dept: dept, label: label, at: at || NOW }); }

  // Reservations (booked in advance). Those arriving today hold their room (« Réservée » on the board).
  var reservations = [];
  seq.resa = 230;
  function res(guest, phone, num, typeKey, arrival, mode, dur, paid, method, status, by) {
    var t = TYPES[typeKey], total = t.rates[mode] * dur;
    var z = { id: 'R' + (reservations.length + 1), ref: 'RES-' + String(++seq.resa).padStart(5, '0'), guest: guest, phone: phone, room: String(num), arrival: arrival, out: checkout(mode, dur, arrival, t), mode: mode, dur: dur, total: total, paid: paid === 'all' ? total : paid, method: method, status: status, by: by || 'Aïcha Koné' };
    reservations.push(z); return z;
  }
  var r203 = res('Fatou Bamba', '07 48 22 19 03', 203, 'dlx', NOW.getHours() >= 6 && NOW.getHours() < 14 ? atTime(NOW, 15, 0) : addMin(atTime(NOW, NOW.getHours(), 0), 90), 'sejour', 2, 'all', 'wave', 'confirmee', 'Koffi Brou');
  var r303 = res('Société Ivoire Conseil', '27 22 41 55 60', 303, 'ste', NOW.getHours() >= 6 && NOW.getHours() < 17 ? atTime(NOW, 18, 30) : addMin(atTime(NOW, NOW.getHours(), 0), 150), 'sejour', 2, 0, null, 'en_attente');
  res('Salif Cissé', '05 06 71 88 42', 102, 'std', addH(NOW, -3) < DAY_START ? addMin(DAY_START, 30) : addH(NOW, -3), 'sejour', 1, 0, null, 'en_attente', 'Koffi Brou');
  res('Serge Kouadio', '07 07 31 26 90', 202, 'dlx', atTime(NOW, 14, 0, 1), 'sejour', 1, 15000, 'orange_money', 'confirmee');
  res('Nadia Brou', '01 02 44 57 11', 107, 'std', atTime(NOW, 16, 0, 2), 'sejour', 1, 0, null, 'en_attente');
  res('Hervé Kacou', '07 59 12 03 66', 302, 'ste', atTime(NOW, 12, 0, 5), 'sejour', 3, 'all', 'wave', 'confirmee', 'Koffi Brou');
  res('Ama Kra', '05 44 90 27 18', 305, 'fam', atTime(NOW, 13, 0, 7), 'sejour', 2, 50000, 'especes', 'confirmee');
  res('Jean-Marc Kouassi', '07 11 63 40 25', 201, 'dlx', addMin(NOW, -26 * 60), 'sejour', 3, 'all', 'orange_money', 'honoree', 'Koffi Brou');
  res('Didier Gnagne', '01 40 18 77 32', 301, 'ste', addMin(NOW, -20 * 60), 'sejour', 2, 'all', 'wave', 'honoree');
  res('Yves Lago', '05 75 30 64 09', 104, 'std', atTime(NOW, 14, 0, -1), 'sejour', 1, 0, null, 'annulee', 'Koffi Brou');

  // Online requests sent from the website (« Réserver »): the réception calls back and books.
  var webRequests = [
    { id: 'W1', guest: 'Clarisse Amani', phone: '07 87 65 43 21', type: 'dlx', arrival: atTime(NOW, 14, 0, 3), dur: 2, adults: 2, children: 1, message: 'Arrivée prévue en début d\'après-midi, lit bébé si possible.', at: addMin(NOW, -95) },
    { id: 'W2', guest: 'Thomas Lefèvre', phone: '+33 6 12 34 56 78', type: 'ste', arrival: atTime(NOW, 12, 0, 9), dur: 4, adults: 2, children: 0, message: 'Voyage de noces, possibilité de décoration ?', at: addMin(NOW, -30 * 60) }
  ];

  var rooms = [];
  function room(num, type, state, extra) { var r = { num: String(num), type: type, state: state }; for (var k in extra || {}) { r[k] = extra[k]; } rooms.push(r); return r; }
  function stay(guest, mode, dur, inMinAgo, typeKey, method, overdueMin) {
    var t = TYPES[typeKey], inAt = addMin(NOW, -inMinAgo), out = checkout(mode, dur, inAt, t);
    if (overdueMin) { out = addMin(NOW, -overdueMin); }
    return { guest: guest, phone: '', mode: mode, dur: dur, in: inAt, out: out, amount: t.rates[mode] * dur, method: method, adults: 2, children: 0 };
  }
  room(101, 'std', 'occupee', { stay: stay('Konan Yao', 'sejour', 1, 11 * 60, 'std', 'especes') });
  room(102, 'std', 'libre');
  room(103, 'std', 'occupee', { stay: stay('Moussa Koné', 'sejour', 1, 24 * 60 + 25, 'std', 'especes') });
  room(104, 'std', 'libre');
  room(105, 'std', 'nettoyage');
  room(106, 'std', 'occupee', { stay: stay('Mariam Diallo', 'sejour', 1, 95, 'std', 'wave') });
  room(107, 'std', 'libre');
  room(108, 'std', 'hors_service', { note: 'Climatisation en réparation' });
  room(201, 'dlx', 'occupee', { stay: stay('Jean-Marc Kouassi', 'sejour', 3, 26 * 60, 'dlx', 'orange_money') });
  room(202, 'dlx', 'libre');
  room(203, 'dlx', 'reservee', { resa: r203 });
  room(204, 'dlx', 'occupee', { stay: stay('Awa Touré', 'sejour', 1, 5 * 60, 'dlx', 'especes') });
  room(205, 'dlx', 'libre');
  room(206, 'dlx', 'nettoyage');
  room(301, 'ste', 'occupee', { stay: stay('Didier Gnagne', 'sejour', 2, 20 * 60, 'ste', 'wave') });
  room(302, 'ste', 'libre');
  room(303, 'ste', 'reservee', { resa: r303 });
  room(304, 'fam', 'occupee', { stay: stay('Famille Ouattara', 'sejour', 4, 30 * 60, 'fam', 'orange_money') });
  room(305, 'fam', 'libre');

  // Payments cashed by Aïcha since the start of the business day (rooms checked in today).
  pay(TYPES.std.rates.sejour, 'wave', 'hebergement', 'Chambre N°106 — Séjour', ago(95));
  pay(TYPES.dlx.rates.sejour, 'especes', 'hebergement', 'Chambre N°204 — Séjour', ago(300));

  var sales = [];
  function sale(dept, minAgo, label, lines, method, room, by) {
    var total = lines.reduce(function (s, l) { return s + ITEMS[dept + '-' + l[0]].price * l[1]; }, 0);
    var at = ago(minAgo), paid = method ? total : 0;
    var s = { id: 'S' + (sales.length + 1), ref: 'FV-' + String(++seq.invoice).padStart(6, '0'), dept: dept, at: at, label: label, lines: lines.map(function (l) { return { id: dept + '-' + l[0], qty: l[1] }; }), total: total, discount: 0, paid: paid, room: room || null, by: by || 'Aïcha Koné', method: method };
    sales.push(s);
    if (method && (by || 'Aïcha Koné') === 'Aïcha Koné') { pay(total, method, dept, s.ref + ' · ' + DEPTS[dept].label, at); }
    return s;
  }
  sale('restauration', 290, 'Petit-déjeuner ch. 201', [[0, 2], [17, 2]], null, '201');
  sale('restauration', 260, 'Table 3', [[1, 1], [17, 1]], 'especes');
  sale('restauration', 140, 'Table 5', [[5, 2], [7, 1], [13, 3]], 'orange_money');
  sale('restauration', 75, 'Emporter — M. Bakayoko', [[4, 1], [8, 2], [15, 1]], 'especes');
  sale('restauration', 40, 'Table 1', [[6, 2], [16, 2], [18, 1]], 'wave');
  sale('restauration', 18, 'Table 8', [[9, 3], [12, 3], [14, 2]], null);
  sale('plage', 230, 'Famille Ouattara (ch. 304)', [[2, 4], [3, 2]], null, '304');
  sale('plage', 180, '', [[0, 2], [3, 2], [4, 2]], 'especes');
  sale('plage', 120, 'Groupe scolaire', [[0, 2], [1, 14], [6, 6]], 'orange_money');
  sale('plage', 60, 'Cours — Mme Aka', [[7, 1]], 'wave');
  sale('plage', 25, '', [[0, 1], [1, 2], [5, 1]], 'especes');

  var ev = [];
  function booking(venue, dayOffset, formula, slot, client, org, title, participants, items, status, paid) {
    var d = atTime(NOW, 0, 0, dayOffset), start, end;
    if (formula === 'day') { start = atTime(d, 8); end = atTime(d, 18); } else if (slot === 'pm') { start = atTime(d, 14); end = atTime(d, 19); } else { start = atTime(d, 8); end = atTime(d, 13); }
    var base = VENUES[venue][formula === 'day' ? 'day' : 'half'];
    var extras = items.reduce(function (s, l) { return s + ITEMS['evenements-' + l[0]].price * l[1]; }, 0);
    ev.push({ id: 'E' + (ev.length + 1), ref: 'FV-' + String(++seq.invoice).padStart(6, '0'), venue: venue, formula: formula, start: start, end: end, client: client, org: org, title: title, participants: participants, items: items.map(function (l) { return { id: 'evenements-' + l[0], qty: l[1] }; }), total: base + extras, paid: paid, status: status });
  }
  booking('baobab', 0, 'day', null, 'Mme Yao Christelle', 'Orange CI', 'Séminaire commercial', 60, [[0, 1], [1, 1], [6, 1]], 'confirmee', 230000);
  booking('kapok', 1, 'half', 'am', 'M. Traoré Ibrahim', 'Cabinet Traoré & Associés', 'Conseil d\'administration', 12, [[0, 1], [3, 1]], 'confirmee', 50000);
  booking('baobab', 3, 'half', 'pm', 'Mme Koffi Adjoua', '', 'Baptême — réception', 70, [[1, 1], [5, 1]], 'option', 0);
  booking('kapok', 6, 'day', null, 'M. Diabaté Moussa', 'ONG Avenir Jeunes', 'Formation des formateurs', 18, [[0, 1], [2, 2], [3, 1]], 'option', 0);
  booking('baobab', 10, 'day', null, 'M. N\'Guessan Paul', 'Mairie de Grand-Bassam', 'Atelier budget participatif', 75, [[0, 1], [1, 1], [4, 1]], 'confirmee', 120000);
  booking('kapok', -3, 'half', 'am', 'Mme Sanogo Aminata', 'Banque Atlantique', 'Réunion régionale', 15, [[0, 1]], 'confirmee', 50000);
  booking('baobab', -6, 'day', null, 'M. Coulibaly Seydou', '', 'Assemblée générale association', 50, [[1, 1]], 'annulee', 0);

  return { rooms: rooms, payments: payments, sales: sales, events: ev, reservations: reservations, webRequests: webRequests, seq: seq, roomTab: 'libre', eventTab: 'avenir', resTab: 'a-affecter', draft: {} };
}
S = seed();

/* ------------------------------------------------------------ derived figures */
function countBy(state) { return S.rooms.filter(function (r) { return r.state === state; }).length; }
function freeByType() { var g = {}; S.rooms.forEach(function (r) { if (r.state === 'libre') { (g[r.type] = g[r.type] || []).push(r); } }); return g; }
function departures() { var end = dayEnd(); var occ = S.rooms.filter(function (r) { return r.state === 'occupee'; }); return { today: occ.filter(function (r) { return r.stay.out <= end; }).length, overdue: occ.filter(function (r) { return r.stay.out < new Date(); }).length }; }
function dayEnd() { return addH(DAY_START, 24); } // end of the hotel's business day (06:00 → 06:00)
function isPending(z) { return z.status === 'en_attente' || z.status === 'confirmee'; }
function arrivals() { return S.reservations.filter(function (z) { return isPending(z) && z.arrival <= dayEnd(); }).length; }
function findRes(id) { return S.reservations.filter(function (z) { return z.id === id; })[0]; }
function cash() {
  var by = { especes: 0, orange_money: 0, wave: 0 }, total = 0, services = 0;
  S.payments.forEach(function (p) { by[p.method] += p.amount; total += p.amount; if (p.dept !== 'hebergement') { services += p.amount; } });
  return { total: total, count: S.payments.length, by: by, services: services };
}
function salesOf(dept) { return S.sales.filter(function (s) { return s.dept === dept; }).sort(function (a, b) { return b.at - a.at; }); }

/* ------------------------------------------------------------ routing & chrome */
var ROUTES = ['accueil', 'chambres', 'reservations', 'restauration', 'plage', 'evenements', 'vente-restauration', 'vente-plage'];
function route() { var h = (location.hash || '').replace('#', ''); return ROUTES.indexOf(h) >= 0 ? h : 'accueil'; }
function go(r) { if (location.hash === '#' + r) { render(); } else { location.hash = r; } }

var NAV = [
  ['accueil', 'fa-house', 'Accueil', 'Accueil'],
  ['chambres', 'fa-hotel', 'Chambres', 'Chambres'],
  ['reservations', 'fa-calendar-days', 'Réservations', 'Réserv.'],
  null,
  ['restauration', 'fa-utensils', 'Restauration', 'Resto'],
  ['plage', 'fa-person-swimming', 'Plage et piscine', 'Plage'],
  ['evenements', 'fa-briefcase', 'Événements', 'Événem.']
];
function renderChrome(r) {
  var section = r.indexOf('vente-') === 0 ? r.slice(6) : r;
  $('#nav').innerHTML = NAV.map(function (n) { return n ? '<a href="#' + n[0] + '" class="' + (section === n[0] ? 'active' : '') + '"><i class="fas ' + n[1] + '"></i> ' + n[2] + '</a>' : '<span class="nav__sep" aria-hidden="true"></span>'; }).join('');
  $('#tabbar').innerHTML = NAV.filter(Boolean).map(function (n) { return '<a href="#' + n[0] + '" class="' + (section === n[0] ? 'active' : '') + '"><i class="fas ' + n[1] + '"></i><span>' + n[3] + '</span></a>'; }).join('');
  $('#dutySince').textContent = ' depuis ' + hm(atTime(DAY_START, 6, 58));
}
function note(html) { return '<div class="demo-note"><i class="fas fa-circle-info"></i><div>' + html + '</div></div>'; }

/* ------------------------------------------------------------ page: accueil */
function pageHome() {
  var c = cash(), d = departures(), a = arrivals(), free = freeByType();
  var freeTotal = countBy('libre'), occ = countBy('occupee'), sellable = S.rooms.length - countBy('hors_service');
  var rate = sellable ? Math.round(occ / sellable * 100) : 0;
  var firstName = 'Aïcha';
  var states = [['occupee', 'Occupées', 'danger'], ['libre', 'Libres', 'success'], ['reservee', 'Réservées', 'info'], ['nettoyage', 'En nettoyage', 'warning'], ['hors_service', 'Hors service', 'secondary']];
  var svcToday = function (dept) { var list = salesOf(dept).filter(function (s) { return !s.cancelled; }); return plural(list.length, 'vente', 'ventes') + ' · ' + fcfa(list.reduce(function (t, s) { return t + s.total; }, 0)) + ' F'; };
  var upcoming = S.events.filter(function (e) { return e.status !== 'annulee' && e.end >= NOW; }).length;

  var html = note("<strong>Accueil de la réception.</strong> Tout ce dont la réceptionniste a besoin sur un seul écran : sa caisse du jour, les sorties et arrivées à traiter, et les chambres libres (à droite sur grand écran, en dessous sur téléphone). <strong>Cliquez sur un numéro de chambre</strong> pour l'affecter à un client.")
    + '<div class="hello"><h1>Bonjour ' + firstName + '</h1><div class="muted">' + longDate(NOW) + ' · ' + hm(NOW) + '</div></div>'
    + '<div class="home-layout"><div class="home-main">'
    + '<section class="my-cash" aria-label="Ma recette du jour"><div>'
    + '<span class="my-cash__label"><i class="fas fa-cash-register"></i> Ma recette du jour</span>'
    + '<span class="my-cash__value">' + fcfa(c.total) + ' <small>FCFA</small></span>'
    + '<span class="my-cash__hint">' + plural(c.count, 'encaissement enregistré', 'encaissements enregistrés') + ' par vous depuis ' + hm(DAY_START) + '</span>'
    + '<span class="my-cash__sub"><i class="fas fa-concierge-bell"></i> dont autres services : <strong>' + fcfa(c.services) + ' F</strong></span></div>'
    + '<ul class="my-cash__methods">' + Object.keys(METHODS).map(function (m) { return '<li class="' + (c.by[m] ? '' : 'is-empty') + '"><span><i class="fas ' + METHODS[m].icon + '"></i> ' + METHODS[m].label + '</span><strong>' + fcfa(c.by[m]) + ' F</strong></li>'; }).join('') + '</ul>'
    + '<div class="my-cash__foot"><span><i class="fas fa-wallet"></i> Espèces à remettre : <strong>' + fcfa(c.by.especes) + ' F</strong></span><button type="button" class="btn btn-sm btn-light" data-act="cash-detail">Voir le détail</button></div>'
    + '</section>'
    + '<div class="stat"><span class="stat__label"><i class="fas fa-bed"></i> Chambres occupées</span><span class="stat__value">' + occ + ' <small>/ ' + sellable + '</small></span><span class="stat__hint">Taux d\'occupation ' + rate + ' % · ' + plural(freeTotal, 'libre', 'libres') + '</span><div class="occ-bar"><span style="width:' + rate + '%"></span></div></div>'
    + '<div class="tiles">'
    + '<button type="button" class="tile tile--success" data-act="goto-rooms" data-state="libre"><span class="tile__icon"><i class="fas fa-door-open"></i></span><span class="tile__label">Affecter une chambre</span><span class="tile__count">' + plural(freeTotal, 'chambre libre', 'chambres libres') + '</span></button>'
    + '<button type="button" class="tile ' + (d.overdue ? 'tile--danger' : 'tile--warning') + '" data-act="goto-rooms" data-state="occupee"><span class="tile__icon"><i class="fas fa-right-from-bracket"></i></span><span class="tile__label">Sorties</span><span class="tile__count">' + plural(d.today, 'sortie', 'sorties') + " aujourd'hui " + (d.overdue ? '<span class="badge bg-danger">' + plural(d.overdue, 'dépassée', 'dépassées') + '</span>' : '') + '</span></button>'
    + '<button type="button" class="tile tile--info" data-act="goto-res"><span class="tile__icon"><i class="fas fa-right-to-bracket"></i></span><span class="tile__label">Arrivées du jour</span><span class="tile__count">' + (a ? plural(a, 'réservation', 'réservations') : 'Aucune arrivée prévue') + '</span></button>'
    + '</div>'
    + '<div class="home-rooms"><span class="home-rooms__title">Chambres</span>' + states.filter(function (s) { return countBy(s[0]); }).map(function (s) { return '<button type="button" class="home-rooms__item" data-act="goto-rooms" data-state="' + s[0] + '"><span class="dot dot--' + s[2] + '"></span><strong>' + countBy(s[0]) + '</strong> ' + s[1].toLowerCase() + '</button>'; }).join('') + '</div>'
    + '<div class="home-services"><p class="section-label" style="margin-top:4px">Autres services</p><div class="svc-row">'
    + '<a class="svc" href="#restauration"><span class="svc__icon"><i class="fas fa-utensils"></i></span><span><span class="svc__name">Restauration</span><span class="svc__meta">' + svcToday('restauration') + '</span></span></a>'
    + '<a class="svc" href="#plage"><span class="svc__icon"><i class="fas fa-person-swimming"></i></span><span><span class="svc__name">Plage et piscine</span><span class="svc__meta">' + svcToday('plage') + '</span></span></a>'
    + '<a class="svc" href="#evenements"><span class="svc__icon"><i class="fas fa-briefcase"></i></span><span><span class="svc__name">Événements</span><span class="svc__meta">' + plural(upcoming, 'réservation à venir', 'réservations à venir') + '</span></span></a>'
    + '</div></div>'
    + '</div>'
    + '<aside class="home-side"><section class="free" aria-label="Chambres disponibles"><div class="free__head"><h2 class="free__title"><i class="fas fa-door-open"></i> Chambres disponibles</h2><span class="free__count">' + freeTotal + ' libre' + (freeTotal > 1 ? 's' : '') + '</span><button type="button" class="free__all" data-act="goto-rooms" data-state="libre">Voir le tableau</button></div>'
    + (Object.keys(TYPES).filter(function (k) { return free[k]; }).map(function (k) { var t = TYPES[k], lo = lowestRate(t); return '<div class="free__type"><div class="free__type-name">' + t.name + '<span class="free__rate">dès ' + fcfa(lo[1]) + ' F ' + modeUnit(lo[0], t) + '</span></div><div class="free__rooms">' + free[k].map(function (r) { return '<button type="button" class="free__room" data-act="assign" data-room="' + r.num + '" title="Affecter la chambre N°' + r.num + '">N°' + r.num + '</button>'; }).join('') + '</div></div>'; }).join('') || '<p class="free__empty">Aucune chambre libre pour le moment.</p>')
    + '</section></aside></div>';
  return html;
}

/* ------------------------------------------------------------ page: chambres */
var ROOM_STATES = [['occupee', 'Occupées', 'danger'], ['libre', 'Libres', 'success'], ['reservee', 'Réservées', 'info'], ['nettoyage', 'En nettoyage', 'warning'], ['hors_service', 'Hors service', 'secondary']];
var STATE_BADGE = { libre: ['success', 'Disponible'], occupee: ['danger', 'Occupée'], reservee: ['info', 'Réservée'], nettoyage: ['warning', 'En nettoyage'], hors_service: ['secondary', 'Hors service'] };
function roomCard(r) {
  var t = TYPES[r.type], b = STATE_BADGE[r.state], now = new Date();
  var clickable = r.state === 'libre' || r.state === 'occupee' || r.state === 'reservee';
  var act = r.state === 'libre' ? 'assign' : r.state === 'occupee' ? 'open-stay' : r.state === 'reservee' ? 'open-resa' : '';
  var h = '<div class="room room--' + r.state + (clickable ? ' is-clickable' : '') + '"' + (clickable ? ' data-act="' + act + '" data-room="' + r.num + '"' + (r.resa ? ' data-res="' + r.resa.id + '"' : '') + ' role="button" tabindex="0" aria-label="Chambre N°' + r.num + '"' : '') + '>'
    + '<div class="room__number">N°' + r.num + '</div><div class="room__type">' + t.name + '</div><span class="badge bg-' + b[0] + '">' + b[1] + '</span>';
  if (r.state === 'occupee') {
    var over = r.stay.out < now;
    h += '<div class="room__stay"><div class="room__time"><i class="fas fa-right-to-bracket"></i> Entrée <strong>' + dmhm(r.stay.in) + '</strong></div>'
      + '<div class="room__time' + (over ? ' is-overdue' : '') + '"><i class="fas fa-right-from-bracket"></i> Sortie <strong>' + dmhm(r.stay.out) + '</strong>' + (over ? ' <span class="badge bg-danger">Dépassée</span>' : '') + '</div></div>'
      + '<div class="room__links"><span>Ouvrir (sortie)</span><span class="btn btn-sm btn-outline-primary"><i class="fas fa-clock"></i> Prolonger</span></div>';
  } else if (r.state === 'reservee') {
    h += '<div class="room__note"><strong style="color:var(--ek-ink)">' + esc(r.resa.guest) + '</strong><br>arrivée ' + dmhm(r.resa.arrival) + '</div><div class="room__links"><span>Ouvrir (check-in)</span></div>';
  } else if (r.state === 'nettoyage') {
    h += '<div style="margin-top:10px"><button type="button" class="btn btn-sm btn-success btn-block" data-act="ready" data-room="' + r.num + '"><i class="fas fa-check"></i> Chambre prête</button></div>';
  } else if (r.state === 'hors_service') {
    h += '<div class="room__note">' + esc(r.note || '') + '</div>';
  }
  if (r.state === 'libre') {
    h += '<ul class="room__rates">' + MODE_ORDER.filter(function (m) { return t.rates[m] != null; }).map(function (m) { return '<li><span><i class="fas ' + MODES[m].icon + ' mode-icon mode--' + m + '"></i> ' + modeLabel(m, t) + '</span><strong>' + fcfa(t.rates[m]) + ' F</strong></li>'; }).join('') + '</ul>';
  }
  return h + '</div>';
}
function pageRooms() {
  if (!countBy(S.roomTab)) { S.roomTab = ROOM_STATES.filter(function (s) { return countBy(s[0]); })[0][0]; }
  var tab = S.roomTab, list = S.rooms.filter(function (r) { return r.state === tab; });
  var html = note("<strong>Le tableau des chambres</strong> montre l'état de chaque chambre en temps réel. Une chambre <strong>libre</strong> s'affecte en un clic (le client s'installe et paie) ; une chambre <strong>occupée</strong> s'ouvre pour valider la sortie ou prolonger ; une chambre <strong>réservée</strong> pour faire le check-in.")
    + '<div class="page-header"><h1>Chambres</h1></div>'
    + '<details class="card rate-grid"><summary><i class="fas fa-tags"></i> Grille tarifaire <span class="muted hide-phone" style="font-weight:400">— tarifs par type de chambre (FCFA)</span></summary>'
    + '<div class="rate-cards mob-only">' + Object.keys(TYPES).map(function (k) { var t = TYPES[k], f = S.rooms.filter(function (r) { return r.type === k && r.state === 'libre'; }).length; return '<div class="rate-card"><div class="m-card__head"><div><div class="m-card__title">' + t.name + '</div><div class="m-card__sub">' + t.adults + ' adulte(s)' + (t.children ? ' + ' + t.children + ' enfant(s)' : '') + '</div></div><span class="badge bg-' + (f ? 'success' : 'secondary') + '">' + f + ' libre' + (f > 1 ? 's' : '') + '</span></div><ul class="room__rates">' + MODE_ORDER.filter(function (m) { return t.rates[m] != null; }).map(function (m) { return '<li><span><i class="fas ' + MODES[m].icon + ' mode-icon mode--' + m + '"></i> ' + modeLabel(m, t) + '</span><strong>' + fcfa(t.rates[m]) + ' F</strong></li>'; }).join('') + '</ul></div>'; }).join('') + '</div>'
    + '<div class="table-wrap desk-only" style="margin-top:12px"><table class="rate-table"><thead><tr><th>Type de chambre</th>'
    + MODE_ORDER.map(function (m) { return '<th class="text-end"><i class="fas ' + MODES[m].icon + ' mode-icon mode--' + m + '"></i> ' + MODES[m].label + (m === 'nuitee' ? '<div class="small" style="text-transform:none;font-weight:400">22h00 → 12h00</div>' : '') + '</th>'; }).join('') + '<th class="text-end">Libres</th></tr></thead><tbody>'
    + Object.keys(TYPES).map(function (k) { var t = TYPES[k], f = S.rooms.filter(function (r) { return r.type === k && r.state === 'libre'; }).length; return '<tr><td><strong>' + t.name + '</strong><div class="small muted">' + t.adults + ' adulte(s)' + (t.children ? ' + ' + t.children + ' enfant(s)' : '') + '</div></td>' + MODE_ORDER.map(function (m) { return '<td class="text-end num">' + (t.rates[m] != null ? '<strong>' + fcfa(t.rates[m]) + '</strong>' : '<span class="muted">—</span>') + '</td>'; }).join('') + '<td class="text-end"><span class="badge bg-' + (f ? 'success' : 'secondary') + '">' + f + '</span></td></tr>'; }).join('')
    + '</tbody></table></div></details>'
    + (tab === 'libre' ? '<p class="muted" style="margin:0 0 14px">Cliquez sur une chambre <strong>disponible</strong> pour l\'affecter à un client : il s\'installe maintenant (check-in immédiat).</p>' : '')
    + '<div class="room-tabs" role="tablist" aria-label="États des chambres">' + ROOM_STATES.map(function (s) { var n = countBy(s[0]); return '<button type="button" role="tab" class="room-tab' + (s[0] === tab ? ' is-active' : '') + '" aria-selected="' + (s[0] === tab) + '" data-act="room-tab" data-state="' + s[0] + '"' + (n ? '' : ' disabled') + '><span class="dot dot--' + s[2] + '"></span> ' + s[1] + ' <strong>' + n + '</strong></button>'; }).join('') + '</div>';
  if (tab === 'libre') {
    Object.keys(TYPES).forEach(function (k) {
      var rs = list.filter(function (r) { return r.type === k; }); if (!rs.length) { return; }
      var t = TYPES[k], lo = lowestRate(t);
      html += '<h3 class="type-heading"><span>' + t.name + '</span><span class="type-heading__count">' + rs.length + ' libre' + (rs.length > 1 ? 's' : '') + '</span><span class="type-heading__rate">dès ' + fcfa(lo[1]) + ' F ' + modeUnit(lo[0], t) + '</span></h3><div class="board">' + rs.map(roomCard).join('') + '</div>';
    });
  } else {
    html += '<div class="board">' + list.map(roomCard).join('') + '</div>';
  }
  return html;
}

/* ------------------------------------------------------------ page: ventes (restauration / plage) */
function pageSales(dept) {
  var D = DEPTS[dept], list = salesOf(dept), valid = list.filter(function (s) { return !s.cancelled; });
  var amount = valid.reduce(function (t, s) { return t + s.total; }, 0);
  var entries = valid.reduce(function (t, s) { return t + s.lines.reduce(function (u, l) { return u + (ITEMS[l.id].entry ? l.qty : 0); }, 0); }, 0);
  var intro = dept === 'restauration'
    ? "<strong>Restauration.</strong> Chaque vente du restaurant et du bar est saisie depuis le catalogue (les prix ne se tapent pas). Elle peut être encaissée tout de suite ou <strong>reportée sur la chambre</strong> d'un client hébergé, qui la réglera avec son séjour."
    : "<strong>Plage et piscine.</strong> Entrées, locations et activités se vendent depuis le catalogue. Les entrées sont comptées pour suivre la <strong>fréquentation</strong> du jour.";
  return note(intro)
    + '<div class="page-header"><div><h1><i class="fas ' + D.icon + '"></i>' + D.label + '</h1><div class="subtitle">Ventes de la journée du ' + dmy(NOW) + " (aujourd'hui).</div></div>"
    + '<div class="actions"><a href="#vente-' + dept + '" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle vente</a></div></div>'
    + '<div class="kpis"><div class="card kpi"><div class="kpi__label">Ventes validées</div><div class="kpi__value">' + valid.length + '</div></div>'
    + '<div class="card kpi"><div class="kpi__label">Montant vendu</div><div class="kpi__value">' + fcfa(amount) + ' <small>FCFA</small></div></div>'
    + (dept === 'plage' ? '<div class="card kpi"><div class="kpi__label">Entrées (fréquentation)</div><div class="kpi__value">' + entries + '</div></div>' : '<div class="card kpi"><div class="kpi__label">Reste à encaisser</div><div class="kpi__value">' + fcfa(valid.reduce(function (t, s) { return t + (s.room ? 0 : Math.max(0, s.total - s.paid)); }, 0)) + ' <small>FCFA</small></div></div>')
    + '</div>'
    + '<div class="card card--flush desk-only"><div class="table-wrap"><table><thead><tr><th>Heure</th><th>Vente</th><th class="text-end">Total</th><th class="text-end">Reste dû</th><th class="hide-phone">Saisie par</th><th></th></tr></thead><tbody>'
    + (list.length ? list.map(function (s) {
      var rem = Math.max(0, s.total - s.paid);
      var state = s.cancelled ? '<span class="badge bg-secondary">Annulée</span>' : s.room ? '<span class="badge bg-info">Sur la chambre</span>' : rem > 0 ? '<span class="text-danger fw">' + fcfa(rem) + ' F</span>' : '<span class="badge bg-success">Soldée</span>';
      return '<tr><td class="nowrap num">' + hm(s.at) + '</td><td><span class="file-ref">' + s.ref + '</span>' + (s.label ? ' · ' + esc(s.label) : '') + (s.room ? '<div class="small muted"><i class="fas fa-bed"></i> Reportée sur la chambre N°' + s.room + '</div>' : '') + '</td><td class="text-end nowrap fw num">' + fcfa(s.total) + ' F</td><td class="text-end nowrap">' + state + '</td><td class="small hide-phone">' + esc(s.by) + '</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-act="open-sale" data-sale="' + s.id + '">Ouvrir</button></td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted" style="text-align:center;padding:28px">Aucune vente pour cette journée.</td></tr>')
    + '</tbody></table></div></div>'
    + mobList(list.map(saleCardM), 'Aucune vente pour cette journée.');
}

function pageNewSale(dept) {
  var D = DEPTS[dept], items = CATALOG[dept], dr = S.draft[dept] || (S.draft[dept] = { qty: {}, label: '', room: '', discount: 0, method: 'especes', received: '' });
  var groups = []; items.forEach(function (it) { var g = groups[groups.length - 1]; if (!g || g.cat !== it.cat) { groups.push(g = { cat: it.cat, items: [] }); } g.items.push(it); });
  var occupied = S.rooms.filter(function (r) { return r.state === 'occupee'; });
  return note("<strong>Nouvelle vente.</strong> Ajoutez les prestations avec les boutons <strong>+</strong> : le total se calcule seul. Choisissez ensuite le mode de paiement (espèces, Orange Money, Wave) ou reportez la note sur une chambre occupée.")
    + '<div class="page-header"><div><h1><i class="fas ' + D.icon + '"></i>Nouvelle vente — ' + D.label + '</h1><div class="subtitle">Choisissez les prestations et leurs quantités. Les prix sont ceux du catalogue.</div></div><a href="#' + dept + '" class="btn btn-outline">Retour aux ventes</a></div>'
    + '<form id="saleForm" data-dept="' + dept + '" novalidate><div class="sale-layout"><div class="card"><h5>Prestations</h5>'
    + groups.map(function (g) { return '<div class="cat-title">' + g.cat + '</div><div class="sale-items">' + g.items.map(function (it) { var q = dr.qty[it.id] || 0; return '<div class="sale-item' + (q ? ' is-picked' : '') + '"><label class="sale-item__name" for="q-' + it.id + '">' + esc(it.name) + '<span class="sale-item__price">' + (it.price ? fcfa(it.price) + ' F' : 'Gratuit') + '</span></label><div class="qty"><button type="button" data-act="qty" data-item="' + it.id + '" data-step="-1" aria-label="Retirer">−</button><input type="number" min="0" max="99" inputmode="numeric" id="q-' + it.id + '" data-qty="' + it.id + '" value="' + q + '"><button type="button" data-act="qty" data-item="' + it.id + '" data-step="1" aria-label="Ajouter">+</button></div></div>'; }).join('') + '</div>'; }).join('')
    + '</div><div class="sale-side"><div class="card"><h5>Vente</h5><div class="grid">'
    + '<div><label class="label" for="saleLabel">Libellé <span>(facultatif : table, nom du client…)</span></label><input class="input" id="saleLabel" maxlength="120" value="' + esc(dr.label) + '" placeholder="Ex. Table 4"></div>'
    + '<div><label class="label" for="saleRoom">Reporter sur la chambre <span>(client hébergé : réglé avec son séjour)</span></label><select class="select" id="saleRoom"><option value="">Non — encaisser maintenant</option>' + occupied.map(function (r) { return '<option value="' + r.num + '"' + (dr.room === r.num ? ' selected' : '') + '>N°' + r.num + ' — ' + esc(r.stay.guest) + '</option>'; }).join('') + '</select></div>'
    + '<div><label class="label" for="saleDiscount">Remise <span>(F)</span></label><input class="input" id="saleDiscount" inputmode="numeric" value="' + (dr.discount || '') + '" placeholder="0"></div>'
    + '</div><div class="pay-box" style="margin-top:14px"><ul class="sale-lines" id="saleLines"></ul><div class="pay-total" id="saleTotal"></div>'
    + '<div id="salePay"><p class="label" style="margin-bottom:8px">Encaissement</p><div class="grid g2"><div><label class="label" for="saleMethod">Mode</label><select class="select" id="saleMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '"' + (dr.method === m ? ' selected' : '') + '>' + METHODS[m].label + '</option>'; }).join('') + '</select></div>'
    + '<div id="saleReceivedWrap"><label class="label" for="saleReceived">Espèces reçues</label><input class="input" id="saleReceived" inputmode="numeric" value="' + esc(dr.received) + '" placeholder="Montant donné"></div></div><div class="form-text" id="saleChange"></div></div>'
    + '<p class="small muted" id="saleRoomNote" hidden style="margin:0"><i class="fas fa-bed"></i> Reportée sur la chambre : rien à encaisser maintenant, le client réglera avec son séjour.</p>'
    + '</div><div id="saleError" class="alert alert-danger" hidden style="margin-top:12px"></div><button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:14px"><i class="fas fa-check"></i> Enregistrer la vente</button></div></div></div>'
    + '<div class="sale-bar-spacer mob-only"></div><div class="sale-bar"><div class="sale-bar__info"><span id="saleBarCount">Aucun article</span><strong id="saleBarTotal">0 F</strong></div><button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Enregistrer</button></div></form>';
}
function saleCompute(dept) {
  var dr = S.draft[dept], lines = [], sub = 0;
  CATALOG[dept].forEach(function (it) { var q = dr.qty[it.id] || 0; if (q) { lines.push({ id: it.id, qty: q }); sub += q * it.price; } });
  var disc = Math.min(sub, Math.max(0, parseInt(String(dr.discount).replace(/\D/g, ''), 10) || 0));
  return { lines: lines, sub: sub, discount: disc, total: sub - disc };
}
function saleRefresh() {
  var f = $('#saleForm'); if (!f) { return; }
  var dept = f.dataset.dept, dr = S.draft[dept], c = saleCompute(dept);
  $('#saleLines').innerHTML = c.lines.length ? c.lines.map(function (l) { var it = ITEMS[l.id]; return '<li><span>' + l.qty + ' × ' + esc(it.name) + '</span><strong class="num">' + fcfa(l.qty * it.price) + ' F</strong></li>'; }).join('') + (c.discount ? '<li><span>Remise</span><strong class="num">− ' + fcfa(c.discount) + ' F</strong></li>' : '') : '<li class="muted">Aucune prestation choisie.</li>';
  $('#saleTotal').innerHTML = 'Total : <span class="pay-amount">' + fcfa(c.total) + ' F</span>';
  var nItems = c.lines.reduce(function (n, l) { return n + l.qty; }, 0);
  $('#saleBarCount').textContent = nItems ? plural(nItems, 'article', 'articles') + (dr.room ? ' · sur la chambre N°' + dr.room : '') : 'Aucun article';
  $('#saleBarTotal').textContent = fcfa(c.total) + ' F';
  var onRoom = !!dr.room;
  $('#salePay').hidden = onRoom; $('#saleRoomNote').hidden = !onRoom;
  var cashM = dr.method === 'especes';
  $('#saleReceivedWrap').style.visibility = cashM ? 'visible' : 'hidden';
  var rec = parseInt(String(dr.received).replace(/\D/g, ''), 10) || 0;
  $('#saleChange').innerHTML = cashM && rec ? (rec >= c.total ? 'Monnaie à rendre : <strong>' + fcfa(rec - c.total) + ' F</strong>' : '<span class="text-danger">Il manque ' + fcfa(c.total - rec) + ' F</span>') : (cashM ? '' : 'Le client paie ' + fcfa(c.total) + ' F par ' + METHODS[dr.method].label + '.');
  $$('.sale-item', f).forEach(function (el) { var inp = $('[data-qty]', el); el.classList.toggle('is-picked', (dr.qty[inp.dataset.qty] || 0) > 0); });
}
function saleSubmit(dept) {
  var dr = S.draft[dept], c = saleCompute(dept), err = $('#saleError');
  var rec = parseInt(String(dr.received).replace(/\D/g, ''), 10) || 0;
  var msg = !c.lines.length ? 'Ajoutez au moins une prestation.' : (!dr.room && dr.method === 'especes' && rec && rec < c.total) ? 'Les espèces reçues ne couvrent pas le total.' : '';
  if (msg) { err.textContent = msg; err.hidden = false; try { err.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {} return; }
  var s = { id: 'S' + Date.now(), ref: 'FV-' + String(++S.seq.invoice).padStart(6, '0'), dept: dept, at: new Date(), label: dr.label.trim(), lines: c.lines, total: c.total, discount: c.discount, paid: dr.room ? 0 : c.total, room: dr.room || null, by: 'Aïcha Koné', method: dr.room ? null : dr.method };
  S.sales.push(s);
  if (!dr.room && c.total > 0) { S.payments.push({ amount: c.total, method: dr.method, dept: dept, label: s.ref + ' · ' + DEPTS[dept].label, at: s.at }); }
  delete S.draft[dept];
  go(dept);
  toast(dr.room ? 'Vente ' + s.ref + ' reportée sur la chambre N°' + dr.room + '.' : 'Vente ' + s.ref + ' enregistrée : ' + fcfa(c.total) + ' F encaissés.');
  if (!dr.room) { showSaleReceipt(s, rec); }
}

/* ------------------------------------------------------------ page: événements */
function evState(e) { if (e.status === 'annulee') { return ['secondary', 'Annulée']; } if (e.end < NOW) { return ['secondary', 'Passée']; } return e.status === 'confirmee' ? ['success', 'Confirmée'] : ['warning', 'Option (devis)']; }
function pageEvents() {
  var tabs = { avenir: 'À venir', options: 'Options', passees: 'Passées', annulees: 'Annulées' };
  var filt = {
    avenir: function (e) { return e.status !== 'annulee' && e.end >= NOW; },
    options: function (e) { return e.status === 'option' && e.end >= NOW; },
    passees: function (e) { return e.status !== 'annulee' && e.end < NOW; },
    annulees: function (e) { return e.status === 'annulee'; }
  };
  var list = S.events.filter(filt[S.eventTab]).sort(function (a, b) { return S.eventTab === 'passees' ? b.start - a.start : a.start - b.start; });
  var monthTotal = S.events.filter(function (e) { return e.status === 'confirmee'; }).reduce(function (t, e) { return t + e.total; }, 0);
  return note("<strong>Réunions et événements.</strong> La location des salles se gère du devis jusqu'au solde : une réservation commence en <strong>option</strong>, devient <strong>confirmée</strong> au premier acompte, et se solde le jour J. Le matériel et les services s'ajoutent depuis le catalogue.")
    + '<div class="page-header"><div><h1><i class="fas fa-briefcase"></i>Réunions et événements</h1><div class="subtitle">Réservations de salles : devis (option), confirmation, acompte et solde.</div></div><div class="actions"><button type="button" class="btn btn-primary" data-act="new-event"><i class="fas fa-plus"></i> Nouvelle réservation</button></div></div>'
    + '<div class="kpis"><div class="card kpi"><div class="kpi__label">Réservations à venir</div><div class="kpi__value">' + S.events.filter(filt.avenir).length + '</div></div><div class="card kpi"><div class="kpi__label">Options en attente</div><div class="kpi__value">' + S.events.filter(filt.options).length + '</div></div><div class="card kpi"><div class="kpi__label">Montant confirmé</div><div class="kpi__value">' + fcfa(monthTotal) + ' <small>FCFA</small></div></div></div>'
    + '<div class="tabs" role="tablist">' + Object.keys(tabs).map(function (k) { return '<button type="button" role="tab" class="' + (S.eventTab === k ? 'is-active' : '') + '" data-act="event-tab" data-tab="' + k + '">' + tabs[k] + '<span>' + S.events.filter(filt[k]).length + '</span></button>'; }).join('') + '</div>'
    + '<div class="card card--flush desk-only"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Salle</th><th>Client</th><th class="text-end">Participants</th><th class="text-end">Total</th><th class="text-end">Reste dû</th><th>Statut</th><th></th></tr></thead><tbody>'
    + (list.length ? list.map(function (e) {
      var st = evState(e), rem = Math.max(0, e.total - e.paid);
      return '<tr><td class="nowrap">' + dmy(e.start) + '<div class="small muted">' + hm(e.start) + ' – ' + hm(e.end) + '</div></td><td>' + VENUES[e.venue].name + '<div class="small muted">' + (e.formula === 'day' ? 'Journée' : 'Demi-journée') + '</div></td><td>' + esc(e.client) + (e.org ? '<div class="small muted">' + esc(e.org) + '</div>' : '') + (e.title ? '<div class="small">' + esc(e.title) + '</div>' : '') + '</td><td class="text-end num">' + e.participants + '</td><td class="text-end nowrap num">' + fcfa(e.total) + ' F</td><td class="text-end nowrap">' + (e.status === 'annulee' ? '—' : rem > 0 ? '<span class="text-danger fw">' + fcfa(rem) + ' F</span>' : '<span class="badge bg-success">Soldée</span>') + '</td><td><span class="badge bg-' + st[0] + '">' + st[1] + '</span></td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-act="open-event" data-event="' + e.id + '">Ouvrir</button></td></tr>';
    }).join('') : '<tr><td colspan="8" class="muted" style="text-align:center;padding:28px">Aucune réservation.</td></tr>')
    + '</tbody></table></div></div>'
    + mobList(list.map(eventCardM), 'Aucune réservation.');
}

/* ------------------------------------------------------------ mobile cards (replace the tables on phones) */
function mobList(cards, empty) { return '<div class="m-list mob-only">' + (cards.length ? cards.join('') : '<div class="m-empty">' + empty + '</div>') + '</div>'; }
function mk(label, value) { return '<div><span class="m-k">' + label + '</span><span class="m-v">' + value + '</span></div>'; }
function saleCardM(s) {
  var rem = Math.max(0, s.total - s.paid);
  var state = s.cancelled ? '<span class="badge bg-secondary">Annulée</span>' : s.room ? '<span class="badge bg-info"><i class="fas fa-bed"></i> Sur la chambre N°' + s.room + '</span>' : rem > 0 ? '<span class="badge bg-danger">Reste dû ' + fcfa(rem) + ' F</span>' : '<span class="badge bg-success">Soldée</span>';
  var items = s.lines.map(function (l) { return l.qty + ' × ' + esc(ITEMS[l.id].name); }).join(', ');
  return '<div class="m-card"><div class="m-card__head"><div><div class="m-card__title">' + (s.label ? esc(s.label) : 'Vente au comptoir') + '</div><div class="m-card__sub">' + hm(s.at) + ' · <span class="file-ref">' + s.ref + '</span> · ' + esc(s.by) + '</div></div><div class="m-card__amount">' + fcfa(s.total) + ' F</div></div>'
    + '<div class="m-card__line">' + items + '</div>'
    + '<div class="m-card__foot">' + state + '<button type="button" class="btn btn-sm btn-outline-primary" data-act="open-sale" data-sale="' + s.id + '">Ouvrir</button></div></div>';
}
function eventCardM(e) {
  var st = evState(e), rem = Math.max(0, e.total - e.paid), v = VENUES[e.venue];
  return '<div class="m-card"><div class="m-card__head"><div><div class="m-card__title">' + esc(e.title || 'Réservation de salle') + '</div><div class="m-card__sub">' + esc(e.client) + (e.org ? ' · ' + esc(e.org) : '') + '</div></div><span class="badge bg-' + st[0] + '">' + st[1] + '</span></div>'
    + '<div class="m-card__meta">' + mk('Date', cap(shortDay(e.start)) + '<div class="small muted">' + hm(e.start) + ' – ' + hm(e.end) + '</div>') + mk('Salle', v.name + '<div class="small muted">' + (e.formula === 'day' ? 'Journée' : 'Demi-journée') + '</div>')
    + mk('Participants', e.participants + ' <span class="small muted">/ ' + v.capacity + '</span>') + mk('Reste dû', e.status === 'annulee' ? '—' : rem > 0 ? '<span class="text-danger">' + fcfa(rem) + ' F</span>' : '<span class="badge bg-success">Soldée</span>') + '</div>'
    + '<div class="m-card__foot"><span class="m-card__amount">' + fcfa(e.total) + ' F</span><button type="button" class="btn btn-sm btn-outline-primary" data-act="open-event" data-event="' + e.id + '">Ouvrir</button></div></div>';
}
function resCardM(z) {
  var r = findRoom(z.room), t = TYPES[r.type], st = RES_STATUS[z.status], pending = isPending(z), now = new Date();
  var canIn = pending && z.arrival <= dayEnd() && (r.state === 'libre' || (r.state === 'reservee' && r.resa === z));
  return '<div class="m-card" data-room-row="' + z.room + '"><div class="m-card__head"><div><div class="m-card__title">' + esc(z.guest) + '</div><div class="m-card__sub"><span class="file-ref">' + z.ref + '</span> · par ' + esc(z.by) + '</div></div><span class="badge bg-' + st[0] + '">' + st[1] + '</span></div>'
    + '<div class="m-badges"><span class="mode-badge mode--' + z.mode + '"><i class="fas ' + MODES[z.mode].icon + '"></i> ' + modeLabel(z.mode, t) + '</span><span class="small muted" style="align-self:center">' + durationLabel(z.mode, z.dur, t) + '</span></div>'
    + '<div class="m-card__meta">' + mk('Arrivée', cap(shortDay(z.arrival)) + ' ' + hm(z.arrival) + (pending && z.arrival < now ? '<div><span class="badge bg-danger">Client non arrivé</span></div>' : ''))
    + mk('Départ', cap(shortDay(z.out)) + ' ' + hm(z.out)) + mk('Chambre', 'N°' + z.room + '<div class="small muted">' + t.name + '</div>')
    + mk('Montant', fcfa(z.total) + ' F' + (z.paid && z.paid < z.total ? '<div class="small muted">acompte ' + fcfa(z.paid) + ' F</div>' : '')) + '</div>'
    + '<div class="m-card__foot"><span class="small"><i class="fas fa-phone" style="color:var(--ek-primary)"></i> ' + esc(z.phone || '—') + '</span>' + (pending ? (z.paid >= z.total ? '<span class="badge bg-success">Soldée · garantie</span>' : '<span class="badge bg-warning">Non soldée</span>') : '') + '</div>'
    + '<div class="m-card__actions">' + (canIn ? '<button type="button" class="btn btn-sm btn-success" data-act="open-resa" data-res="' + z.id + '"><i class="fas fa-right-to-bracket"></i> Affecter</button>' : '') + '<button type="button" class="btn btn-sm btn-outline-primary" data-act="open-resa" data-res="' + z.id + '">Ouvrir</button>' + (pending ? '<button type="button" class="btn btn-sm btn-outline-danger" data-act="cancel-res" data-res="' + z.id + '"><i class="fas fa-xmark"></i> Annuler</button>' : '') + '</div></div>';
}

/* ------------------------------------------------------------ modal & toast */
var lastFocus = null;
function openModal(o) {
  lastFocus = document.activeElement;
  $('#modalHost').innerHTML = '<div class="modal-root" data-modal-root><div class="modal ' + (o.size ? 'modal--' + o.size : '') + '" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><div class="modal__head"><h2 class="modal__title" id="modalTitle">' + o.title + '</h2><button type="button" class="modal__close" data-act="close-modal" aria-label="Fermer"><i class="fas fa-xmark"></i></button></div><div class="modal__body' + (o.flush ? '" style="padding:0' : '') + '">' + o.body + '</div>' + (o.foot ? '<div class="modal__foot">' + o.foot + '</div>' : '') + '</div></div>';
  var root = $('[data-modal-root]');
  setTimeout(function () { var f = $('input:not([type=hidden]), select, button.btn-primary', root); if (f) { f.focus(); } }, 30);
  return root;
}
function closeModal() { $('#modalHost').innerHTML = ''; if (lastFocus && lastFocus.focus && document.body.contains(lastFocus)) { lastFocus.focus(); } }
function toast(msg) {
  var el = document.createElement('div'); el.className = 'toast'; el.innerHTML = '<i class="fas fa-circle-check"></i><div>' + esc(msg) + '</div>';
  $('#toasts').appendChild(el); setTimeout(function () { el.remove(); }, 4200);
}

/* ------------------------------------------------------------ affectation (assign a free room) */
function findRoom(num) { return S.rooms.filter(function (r) { return r.num === String(num); })[0]; }
function assignModal(num) {
  var r = findRoom(num), t = TYPES[r.type];
  var modes = MODE_ORDER.filter(function (m) { return t.rates[m] != null; });
  openModal({ size: 'xl', title: 'Affecter la chambre N°' + r.num + ' <span class="muted" style="font-weight:500;font-size:.9rem">— ' + t.name + '</span>',
    body: '<form id="assignForm" data-room="' + r.num + '" novalidate><div class="assign-grid"><div>'
      + '<p class="section-label">Client <span style="text-transform:none;font-weight:400">(facultatif)</span></p><p class="small muted" style="margin:0 0 8px">Tout est facultatif ; sans nom, la fiche est créée en « Client de passage », à compléter plus tard.</p>'
      + '<div class="grid g2"><input class="input" id="aFirst" placeholder="Prénom" autocomplete="off"><input class="input" id="aLast" placeholder="Nom" autocomplete="off"></div><div style="margin-top:12px"><input class="input" id="aPhone" placeholder="Téléphone" inputmode="tel" autocomplete="off"></div>'
      + '<p class="section-label" style="margin-top:22px">Séjour</p><label class="label">Formule</label><div class="modes" role="radiogroup">' + modes.map(function (m) { return '<button type="button" role="radio" aria-checked="' + (modes.length === 1) + '" class="mode-btn mode--' + m + (modes.length === 1 ? ' is-on' : '') + '" data-act="pick-mode" data-mode="' + m + '"><i class="fas ' + MODES[m].icon + '"></i> ' + modeLabel(m, t) + '</button>'; }).join('') + '</div>'
      + '<div class="grid g3" style="margin-top:14px"><div><label class="label" for="aDur" id="aDurLabel">Durée</label><input class="input" type="number" min="1" max="30" id="aDur" value="1"></div><div><label class="label" for="aAdults">Adultes</label><input class="input" type="number" min="1" id="aAdults" value="' + Math.min(2, t.adults) + '"></div><div><label class="label" for="aKids">Enfants</label><input class="input" type="number" min="0" id="aKids" value="0"></div></div>'
      + '<div style="margin-top:14px"><label class="label" for="aRate">Tarif <span id="aRateUnit"></span> (FCFA)</label><input class="input" id="aRate" readonly tabindex="-1" placeholder="Choisissez un mode"><div class="form-text">Tarif du type de chambre (non modifiable). Pour une réduction, utilisez la remise.</div></div>'
      + '</div><div><div class="pay-box"><p class="section-label">Règlement</p><div class="pay-total" id="aTotal">Choisissez un mode.</div>'
      + '<p class="label" style="margin-bottom:8px">Encaissement <span>— totalité du séjour</span></p><div class="grid g2"><div><label class="label" for="aMethod">Mode de paiement</label><select class="select" id="aMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div><div id="aRecWrap"><label class="label" for="aReceived">Espèces reçues</label><input class="input" id="aReceived" inputmode="numeric" placeholder="Montant donné"></div></div>'
      + '<div class="form-text" id="aChange"></div>'
      + '<div class="grid g2" style="margin-top:12px"><div><label class="label" for="aDiscount">Remise <span>(F)</span></label><input class="input" id="aDiscount" inputmode="numeric" placeholder="0"></div><div><label class="label" for="aReason">Motif</label><input class="input" id="aReason" placeholder="Ex. client fidèle"></div></div>'
      + '</div><div id="aError" class="alert alert-danger" hidden style="margin-top:12px"></div></div></div></form>',
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Annuler</button><button type="submit" form="assignForm" class="btn btn-primary"><i class="fas fa-key"></i> Affecter la chambre</button>'
  });
  assignRefresh();
}
function assignState() {
  var f = $('#assignForm'), r = findRoom(f.dataset.room), t = TYPES[r.type];
  var on = $('.mode-btn.is-on', f), mode = on ? on.dataset.mode : null;
  var dur = Math.max(1, Math.min(30, parseInt($('#aDur').value, 10) || 1));
  var rate = mode ? t.rates[mode] : 0, sub = rate * dur;
  var disc = Math.min(sub, parseInt(($('#aDiscount').value || '').replace(/\D/g, ''), 10) || 0);
  var now = new Date();
  return { room: r, type: t, mode: mode, dur: dur, rate: rate, sub: sub, discount: disc, total: sub - disc, out: mode ? checkout(mode, dur, now, t) : null, method: $('#aMethod').value, received: parseInt(($('#aReceived').value || '').replace(/\D/g, ''), 10) || 0, now: now };
}
function assignRefresh() {
  var s = assignState(), box = $('#aTotal');
  $('#aDurLabel').textContent = s.mode === 'sejour' ? 'Nb de séjours' : 'Durée';
  $('#aRate').value = s.mode ? fcfa(s.rate) : '';
  $('#aRateUnit').textContent = s.mode ? modeUnit(s.mode, s.type) : '';
  var colors = { heure: ['var(--ek-mode-heure)', '#fff'], journee: ['var(--ek-mode-journee)', '#2e2300'], nuitee: ['var(--ek-mode-nuitee)', '#fff'], sejour: ['var(--ek-mode-sejour)', '#fff'] };
  if (s.mode) { box.style.setProperty('--pay', colors[s.mode][0]); box.style.setProperty('--pay-on', colors[s.mode][1]); }
  box.innerHTML = s.mode
    ? '<small>Entrée maintenant : ' + shortDay(s.now) + ' ' + hm(s.now) + ' · Sortie prévue : <strong style="color:var(--ek-ink)">' + shortDay(s.out) + ' ' + hm(s.out) + '</strong></small>' + durationLabel(s.mode, s.dur, s.type) + ' × ' + fcfa(s.rate) + ' F' + (s.discount ? ' − ' + fcfa(s.discount) + ' F' : '') + ' = <span class="pay-amount">' + fcfa(s.total) + ' F</span>'
    : 'Choisissez un mode.';
  var cashM = s.method === 'especes';
  $('#aRecWrap').style.visibility = cashM ? 'visible' : 'hidden';
  $('#aChange').innerHTML = !s.mode ? '' : cashM ? (s.received ? (s.received >= s.total ? 'Monnaie à rendre : <strong>' + fcfa(s.received - s.total) + ' F</strong>' : '<span class="text-danger">Il manque ' + fcfa(s.total - s.received) + ' F</span>') : 'Saisissez le montant donné par le client pour calculer la monnaie.') : 'Le client paie ' + fcfa(s.total) + ' F par ' + METHODS[s.method].label + '.';
}
function assignSubmit() {
  var s = assignState(), err = $('#aError');
  var msg = !s.mode ? 'Choisissez la formule.' : (s.method === 'especes' && s.received && s.received < s.total) ? 'Les espèces reçues ne couvrent pas le total.' : '';
  if (msg) { err.textContent = msg; err.hidden = false; return; }
  var name = [$('#aFirst').value.trim(), $('#aLast').value.trim()].filter(Boolean).join(' ') || 'Client de passage';
  var r = s.room;
  r.state = 'occupee';
  r.stay = { guest: name, phone: $('#aPhone').value.trim(), mode: s.mode, dur: s.dur, in: s.now, out: s.out, amount: s.total, method: s.method, adults: +$('#aAdults').value || 1, children: +$('#aKids').value || 0 };
  S.payments.push({ amount: s.total, method: s.method, dept: 'hebergement', label: 'Chambre N°' + r.num + ' — ' + MODES[s.mode].label, at: s.now });
  closeModal();
  render();
  toast('Chambre N°' + r.num + ' affectée à ' + name + '.');
  showStayReceipt(r, s.received);
}
function receiptHead(title) {
  S.seq.receipt++;
  return '<div class="receipt__center"><div class="receipt__name">HÔTEL LES PALMIERS</div><div>Grand-Bassam · Côte d\'Ivoire</div><div>Tél. 27 00 00 00 00</div></div><hr><div class="receipt__center"><strong>' + title + '</strong><br>N° R-' + S.seq.receipt + ' · ' + dmy(new Date()) + ' ' + hm(new Date()) + '</div><hr>';
}
function receiptFoot(total, method, received) {
  return '<hr><div class="receipt__row receipt__total"><span>TOTAL</span><span>' + fcfa(total) + ' F</span></div><div class="receipt__row"><span>Payé par</span><span>' + METHODS[method].label + '</span></div>'
    + (method === 'especes' && received >= total && received ? '<div class="receipt__row"><span>Reçu</span><span>' + fcfa(received) + ' F</span></div><div class="receipt__row"><span>Monnaie</span><span>' + fcfa(received - total) + ' F</span></div>' : '')
    + '<div class="receipt__row"><span>Caissière</span><span>Aïcha Koné</span></div><hr><div class="receipt__center">Merci de votre visite !</div>';
}
function showStayReceipt(r, received) {
  var st = r.stay, t = TYPES[r.type];
  openModal({ size: 'sm', flush: true, title: '<i class="fas fa-receipt"></i> Reçu du client — aperçu',
    body: '<div class="receipt-wrap"><div class="receipt">' + receiptHead('REÇU DE SÉJOUR')
      + '<div class="receipt__row"><span>Client</span><span>' + esc(st.guest) + '</span></div><div class="receipt__row"><span>Chambre</span><span>N°' + r.num + '</span></div><div class="receipt__row"><span>Type</span><span>' + t.name + '</span></div>'
      + '<div class="receipt__row"><span>Formule</span><span>' + modeLabel(st.mode, t) + '</span></div><div class="receipt__row"><span>Durée</span><span>' + durationLabel(st.mode, st.dur, t).replace(' (sortie à 12h00)', '') + '</span></div>'
      + '<div class="receipt__row"><span>Entrée</span><span>' + dmhm(st.in) + '</span></div><div class="receipt__row"><span>Sortie</span><span>' + dmhm(st.out) + '</span></div>'
      + receiptFoot(st.amount, st.method, received) + '</div></div>',
    foot: '<span class="small muted" style="margin-right:auto">Dans l\'application, ce reçu s\'imprime sur l\'imprimante ticket.</span><button type="button" class="btn btn-primary" data-act="close-modal">Fermer</button>' });
}
function showSaleReceipt(s, received) {
  openModal({ size: 'sm', flush: true, title: '<i class="fas fa-receipt"></i> Ticket de vente — aperçu',
    body: '<div class="receipt-wrap"><div class="receipt">' + receiptHead(DEPTS[s.dept].label.toUpperCase()) + (s.label ? '<div>' + esc(s.label) + '</div>' : '') + '<div>Vente ' + s.ref + '</div><hr>'
      + s.lines.map(function (l) { var it = ITEMS[l.id]; return '<div class="receipt__row"><span>' + l.qty + ' × ' + esc(it.name) + '</span><span>' + fcfa(l.qty * it.price) + '</span></div>'; }).join('')
      + (s.discount ? '<div class="receipt__row"><span>Remise</span><span>− ' + fcfa(s.discount) + '</span></div>' : '')
      + receiptFoot(s.total, s.method, received) + '</div></div>',
    foot: '<button type="button" class="btn btn-primary" data-act="close-modal">Fermer</button>' });
}

/* ------------------------------------------------------------ occupied room: checkout / extend */
function stayModal(num) {
  var r = findRoom(num), st = r.stay, t = TYPES[r.type], over = st.out < new Date();
  var roomSales = S.sales.filter(function (s) { return s.room === r.num && !s.cancelled; });
  var extra = roomSales.reduce(function (a, s) { return a + s.total - s.paid; }, 0);
  openModal({ title: 'Chambre N°' + r.num + ' <span class="muted" style="font-weight:500;font-size:.9rem">— ' + t.name + '</span>',
    body: '<dl class="dl"><dt>Client</dt><dd>' + esc(st.guest) + '</dd><dt>Formule</dt><dd><span class="mode-badge mode--' + st.mode + '"><i class="fas ' + MODES[st.mode].icon + '"></i> ' + modeLabel(st.mode, t) + '</span> · ' + durationLabel(st.mode, st.dur, t) + '</dd>'
      + '<dt>Entrée</dt><dd>' + dmhm(st.in) + '</dd><dt>Sortie prévue</dt><dd' + (over ? ' class="text-danger"' : '') + '>' + dmhm(st.out) + (over ? ' <span class="badge bg-danger">Dépassée</span>' : '') + '</dd>'
      + '<dt>Séjour réglé</dt><dd>' + fcfa(st.amount) + ' F <span class="badge bg-success">Soldé</span></dd>'
      + (roomSales.length ? '<dt>Consommations</dt><dd>' + roomSales.map(function (s) { return DEPTS[s.dept].label + ' · ' + fcfa(s.total) + ' F'; }).join('<br>') + '</dd>' : '') + '</dl>'
      + (extra > 0 ? '<div class="alert alert-info" style="margin-top:14px"><i class="fas fa-circle-info"></i> ' + fcfa(extra) + ' F de consommations reportées sur la chambre seront encaissés à la sortie.</div>' : '')
      + '<div class="pay-box" style="margin-top:16px" id="extendBox"><p class="section-label">Prolonger le séjour</p><div class="grid g2"><div><label class="label" for="eDur">Durée supplémentaire <span>(' + (st.mode === 'sejour' && t.until ? 'séjours' : MODES[st.mode].units) + ')</span></label><input class="input" type="number" min="1" max="30" id="eDur" value="1"></div><div><label class="label" for="eMethod">Paiement</label><select class="select" id="eMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div></div><div class="form-text" id="eInfo"></div><button type="button" class="btn btn-outline-primary" style="margin-top:10px" data-act="extend" data-room="' + r.num + '"><i class="fas fa-clock"></i> Prolonger et encaisser</button></div>',
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Fermer</button><button type="button" class="btn btn-danger" data-act="checkout" data-room="' + r.num + '"><i class="fas fa-right-from-bracket"></i> Valider la sortie</button>'
  });
  extendRefresh(r);
}
function extendRefresh(r) {
  var el = $('#eDur'); if (!el) { return; }
  var t = TYPES[r.type], d = Math.max(1, parseInt(el.value, 10) || 1), st = r.stay;
  var newOut = st.mode === 'heure' ? addH(st.out, d) : st.mode === 'journee' ? addH(st.out, 6 * d) : st.mode === 'nuitee' || t.until ? atTime(st.out, 12, 0, d) : addH(st.out, 24 * d);
  $('#eInfo').innerHTML = 'Nouvelle sortie : <strong>' + shortDay(newOut) + ' ' + hm(newOut) + '</strong> · à payer : <strong>' + fcfa(t.rates[st.mode] * d) + ' F</strong>';
  return { d: d, out: newOut, amount: t.rates[st.mode] * d };
}

/* ------------------------------------------------------------ reserved room: check-in */
function resaModal(id) {
  var z = findRes(id), r = findRoom(z.room), t = TYPES[r.type], total = z.total, rem = total - z.paid, pending = isPending(z);
  var canIn = pending && z.arrival <= dayEnd() && (r.state === 'libre' || (r.state === 'reservee' && r.resa === z));
  var stLabel = RES_STATUS[z.status];
  openModal({ title: 'Réservation <span class="file-ref" style="font-size:.95rem">' + z.ref + '</span> <span class="badge bg-' + stLabel[0] + '" style="vertical-align:middle">' + stLabel[1] + '</span>',
    body: '<dl class="dl"><dt>Client</dt><dd>' + esc(z.guest) + '</dd><dt>Téléphone</dt><dd>' + esc(z.phone) + '</dd><dt>Chambre</dt><dd>N°' + r.num + ' · ' + t.name + '</dd><dt>Arrivée prévue</dt><dd>' + shortDay(z.arrival) + ' ' + hm(z.arrival) + '</dd><dt>Départ</dt><dd>' + shortDay(z.out) + ' ' + hm(z.out) + '</dd>'
      + '<dt>Formule</dt><dd><span class="mode-badge mode--' + z.mode + '"><i class="fas ' + MODES[z.mode].icon + '"></i> ' + modeLabel(z.mode, t) + '</span> · ' + durationLabel(z.mode, z.dur, t) + '</dd>'
      + '<dt>Montant</dt><dd>' + fcfa(total) + ' F</dd><dt>Déjà payé</dt><dd>' + fcfa(z.paid) + ' F' + (z.paid ? ' (' + METHODS[z.method].label + ')' : '') + '</dd></dl>'
      + (!pending ? '<div class="alert alert-info" style="margin-top:14px">' + (z.status === 'honoree' ? 'Le client est arrivé : son séjour est suivi dans le tableau des chambres.' : 'Réservation annulée : la chambre est libérée.' + (z.paid ? ' Les acomptes encaissés restent sur la facture.' : '')) + '</div>'
        : !canIn && r.state !== 'reservee' && z.arrival <= dayEnd() ? '<div class="alert alert-danger" style="margin-top:14px">La chambre N°' + r.num + ' n\'est pas libre pour le moment (' + STATE_BADGE[r.state][1].toLowerCase() + ').</div>'
        : rem > 0 ? '<div class="pay-box" style="margin-top:16px"><p class="section-label">Solde à l\'arrivée</p><div class="pay-total">Reste à payer : <span class="pay-amount">' + fcfa(rem) + ' F</span></div><label class="label" for="rMethod">Mode de paiement</label><select class="select" id="rMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div>' : '<div class="alert alert-info" style="margin-top:14px">Réservation entièrement payée : le client peut s\'installer directement.</div>'),
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Fermer</button>'
      + (pending ? '<button type="button" class="btn btn-outline-danger" data-act="cancel-res" data-res="' + z.id + '"><i class="fas fa-xmark"></i> Annuler la réservation</button>' : '')
      + (canIn ? '<button type="button" class="btn btn-primary" data-act="checkin" data-res="' + z.id + '"><i class="fas fa-right-to-bracket"></i> Faire le check-in</button>' : '')
  });
}

/* ------------------------------------------------------------ page: réservations */
var RES_STATUS = { en_attente: ['secondary', 'En attente'], confirmee: ['info', 'Confirmée'], honoree: ['success', 'Honorée (client arrivé)'], annulee: ['danger', 'Annulée'] };
var RES_TABS = {
  'a-affecter': ['À affecter', function (z) { return isPending(z); }],
  'affectees': ['Affectées', function (z) { return z.status === 'honoree'; }],
  'annulees': ['Annulées', function (z) { return z.status === 'annulee'; }]
};
function pageReservations() {
  var tab = S.resTab, list = S.reservations.filter(RES_TABS[tab][1]).sort(function (a, b) { return tab === 'a-affecter' ? a.arrival - b.arrival : b.arrival - a.arrival; });
  var todayEnd = dayEnd(), now = new Date(), web = S.webRequests.length;
  return note("<strong>Réservations.</strong> Les séjours réservés à l'avance (téléphone, site web, agence). Une réservation <strong>soldée</strong> garantit sa chambre ; le jour J, le bouton <strong>Affecter</strong> installe le client en un clic. Les demandes envoyées depuis le <strong>site web</strong> arrivent ici, à rappeler puis à convertir en réservation.")
    + '<div class="page-header"><div><h1><i class="fas fa-calendar-days"></i>Réservations</h1><div class="subtitle">Séjours réservés à l\'avance. Les clients déjà installés sont dans le <a href="#chambres">tableau des chambres</a>.</div></div><div class="actions"><button type="button" class="btn btn-primary" data-act="new-res"><i class="fas fa-plus"></i> Nouvelle réservation</button></div></div>'
    + (web ? '<div class="alert alert-warning"><span><i class="fas fa-globe"></i> <strong>' + plural(web, 'demande', 'demandes') + ' de réservation en ligne</strong> en attente de rappel.</span><button type="button" class="btn btn-sm btn-warning" data-act="web-requests">Voir les demandes</button></div>' : '')
    + '<div class="tabs" role="tablist">' + Object.keys(RES_TABS).map(function (k) { return '<button type="button" role="tab" class="' + (tab === k ? 'is-active' : '') + '" data-act="res-tab" data-tab="' + k + '">' + RES_TABS[k][0] + '<span>' + S.reservations.filter(RES_TABS[k][1]).length + '</span></button>'; }).join('') + '</div>'
    + '<div class="res-filter"><input class="input" id="resRoomFilter" placeholder="Filtrer : N° de chambre" inputmode="numeric" aria-label="Filtrer par numéro de chambre"></div>'
    + '<div class="card card--flush desk-only"><div class="table-wrap"><table><thead><tr><th>Client</th><th>Téléphone</th><th>Chambre</th><th>Formule</th><th>Arrivée</th><th>Départ</th><th>Statut</th><th>Montant</th><th class="text-end">Actions</th></tr></thead><tbody>'
    + (list.length ? list.map(function (z) {
      var r = findRoom(z.room), t = TYPES[r.type], st = RES_STATUS[z.status], pending = isPending(z);
      var canIn = pending && z.arrival <= todayEnd && (r.state === 'libre' || (r.state === 'reservee' && r.resa === z));
      return '<tr data-room-row="' + z.room + '"><td>' + esc(z.guest) + '<div><span class="file-ref">' + z.ref + '</span></div></td>'
        + '<td class="nowrap">' + (z.phone ? '<i class="fas fa-phone" style="color:var(--ek-primary)"></i> ' + esc(z.phone) : '—') + '</td>'
        + '<td class="nowrap">N°' + z.room + '<div class="small muted">' + t.name + '</div></td>'
        + '<td><span class="mode-badge mode--' + z.mode + '"><i class="fas ' + MODES[z.mode].icon + '"></i> ' + modeLabel(z.mode, t) + '</span><div class="small muted">' + durationLabel(z.mode, z.dur, t) + '</div></td>'
        + '<td class="nowrap">' + dmy(z.arrival) + ' ' + hm(z.arrival) + (pending && z.arrival < now ? '<div><span class="badge bg-danger">Client non arrivé</span></div>' : '') + '</td>'
        + '<td class="nowrap">' + dmy(z.out) + ' ' + hm(z.out) + '</td>'
        + '<td><span class="badge bg-' + st[0] + '">' + st[1] + '</span>' + (pending ? '<div style="margin-top:3px">' + (z.paid >= z.total ? '<span class="badge bg-success">Soldée · chambre garantie</span>' : '<span class="badge bg-warning">Non soldée · non garantie</span>') + '</div>' : '') + '<div class="small muted">par ' + esc(z.by) + '</div></td>'
        + '<td class="nowrap num">' + fcfa(z.total) + ' FCFA' + (z.paid && z.paid < z.total ? '<div class="small muted">acompte ' + fcfa(z.paid) + '</div>' : '') + '</td>'
        + '<td class="text-end"><div class="row-actions">' + (canIn ? '<button type="button" class="btn btn-sm btn-success" data-act="open-resa" data-res="' + z.id + '"><i class="fas fa-right-to-bracket"></i> Affecter</button>' : '') + '<button type="button" class="btn btn-sm btn-outline-primary" data-act="open-resa" data-res="' + z.id + '">Ouvrir</button>' + (pending ? '<button type="button" class="btn btn-sm btn-outline-danger" data-act="cancel-res" data-res="' + z.id + '"><i class="fas fa-xmark"></i> Annuler</button>' : '') + '</div></td></tr>';
    }).join('') : '<tr><td colspan="9" class="muted" style="text-align:center;padding:28px">' + ({ 'a-affecter': 'Aucune réservation à affecter.', 'affectees': 'Aucune réservation affectée pour l\'instant.', 'annulees': 'Aucune réservation annulée.' })[tab] + '</td></tr>')
    + '</tbody></table></div></div>'
    + mobList(list.map(resCardM), ({ 'a-affecter': 'Aucune réservation à affecter.', 'affectees': 'Aucune réservation affectée pour l\'instant.', 'annulees': 'Aucune réservation annulée.' })[tab]);
}

function cancelResModal(id) {
  var z = findRes(id);
  openModal({ size: 'sm', title: 'Annuler cette réservation ?',
    body: '<p style="margin:0">La réservation <span class="file-ref">' + z.ref + '</span> de <strong>' + esc(z.guest) + '</strong> (chambre N°' + z.room + ') sera annulée.' + (z.paid ? ' Les acomptes déjà encaissés restent sur la facture.' : '') + '</p>',
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Non, la garder</button><button type="button" class="btn btn-danger" data-act="confirm-cancel-res" data-res="' + z.id + '">Oui, annuler la réservation</button>' });
}

function webRequestsModal() {
  openModal({ title: '<i class="fas fa-globe"></i> Demandes de réservation en ligne',
    body: '<p class="small muted" style="margin:0 0 12px">Envoyées depuis le site web de l\'hôtel (bouton « Réserver »). Rappelez le client, puis créez la réservation.</p>'
      + S.webRequests.map(function (w) { var t = TYPES[w.type]; return '<div class="req"><div style="min-width:0"><strong style="color:var(--ek-ink)">' + esc(w.guest) + '</strong> · <span class="nowrap"><i class="fas fa-phone" style="color:var(--ek-primary)"></i> ' + esc(w.phone) + '</span><div class="small">' + t.name + ' · ' + plural(w.dur, 'séjour', 'séjours') + ' à partir du ' + shortDay(w.arrival) + ' · ' + w.adults + ' adulte(s)' + (w.children ? ' + ' + w.children + ' enfant(s)' : '') + '</div><div class="small muted">« ' + esc(w.message) + ' » — reçue à ' + hm(w.at) + (sameDay(w.at, NOW) ? '' : ' le ' + dm(w.at)) + '</div></div><button type="button" class="btn btn-sm btn-primary" data-act="convert-web" data-web="' + w.id + '">Créer la réservation</button></div>'; }).join(''),
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Fermer</button>' });
}

/* New reservation (optionally pre-filled from an online request) */
function roomFreeFor(num, from, to, exceptId) {
  var r = findRoom(num); if (r.state === 'hors_service') { return false; }
  if (r.state === 'occupee' && r.stay.out > from) { return false; }
  return !S.reservations.some(function (z) { return z.id !== exceptId && z.room === r.num && isPending(z) && z.arrival < to && z.out > from; });
}
function dateVal(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function newResModal(web) {
  var typeKey = web ? web.type : 'dlx', arr = web ? web.arrival : atTime(NOW, 14, 0, 1);
  var names = web ? web.guest.split(' ') : ['', ''];
  openModal({ size: 'xl', title: 'Nouvelle réservation' + (web ? ' <span class="muted" style="font-weight:500;font-size:.9rem">— demande en ligne de ' + esc(web.guest) + '</span>' : ''),
    body: '<form id="resForm" novalidate' + (web ? ' data-web="' + web.id + '"' : '') + '><div class="assign-grid"><div>'
      + '<p class="section-label">Client</p><div class="grid g2"><input class="input" id="nrFirst" placeholder="Prénom" value="' + esc(names[0] || '') + '" autocomplete="off"><input class="input" id="nrLast" placeholder="Nom" value="' + esc(names.slice(1).join(' ')) + '" autocomplete="off"></div><div style="margin-top:12px"><input class="input" id="nrPhone" placeholder="Téléphone" inputmode="tel" value="' + esc(web ? web.phone : '') + '" autocomplete="off"></div>'
      + '<p class="section-label" style="margin-top:22px">Séjour</p><div class="grid g2"><div><label class="label" for="nrDate">Arrivée le</label><input class="input" type="date" id="nrDate" value="' + dateVal(arr) + '" min="' + dateVal(NOW) + '"></div><div><label class="label" for="nrTime">à</label><input class="input" type="time" id="nrTime" value="' + pad(arr.getHours()) + ':' + pad(arr.getMinutes()) + '"></div>'
      + '<div><label class="label" for="nrType">Type de chambre</label><select class="select" id="nrType">' + Object.keys(TYPES).map(function (k) { return '<option value="' + k + '"' + (k === typeKey ? ' selected' : '') + '>' + TYPES[k].name + '</option>'; }).join('') + '</select></div><div><label class="label" for="nrRoom">Chambre</label><select class="select" id="nrRoom"></select></div></div>'
      + '<label class="label" style="margin-top:14px">Formule</label><div class="modes" id="nrModes" role="radiogroup"></div>'
      + '<div class="grid g2" style="margin-top:14px"><div><label class="label" for="nrDur" id="nrDurLabel">Durée</label><input class="input" type="number" min="1" max="30" id="nrDur" value="' + (web ? web.dur : 1) + '"></div></div>'
      + '</div><div><div class="pay-box"><p class="section-label">Règlement</p><div class="pay-total" id="nrTotal">Choisissez un mode.</div>'
      + '<label class="label" for="nrPaid">Acompte versé <span>(facultatif : soldée, la chambre est garantie)</span></label><input class="input" id="nrPaid" inputmode="numeric" placeholder="0"><div class="grid g2" style="margin-top:10px"><div><label class="label" for="nrMethod">Mode de paiement</label><select class="select" id="nrMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div></div>'
      + '<div class="form-text" id="nrStatus"></div></div><div id="nrError" class="alert alert-danger" hidden style="margin-top:12px"></div></div></div></form>',
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Annuler</button><button type="submit" form="resForm" class="btn btn-primary"><i class="fas fa-check"></i> Enregistrer la réservation</button>'
  });
  resTypeChanged(web ? 'sejour' : null);
}
function resArrival() { var d = $('#nrDate').value, t = $('#nrTime').value || '14:00'; if (!d) { return null; } var p = d.split('-'), h = t.split(':'); return new Date(+p[0], +p[1] - 1, +p[2], +h[0], +h[1]); }
function resTypeChanged(preMode) {
  var k = $('#nrType').value, t = TYPES[k], keep = $('#nrRoom').value;
  var avail = MODE_ORDER.filter(function (m) { return t.rates[m] != null; }); if (avail.length === 1) { preMode = avail[0]; }
  $('#nrModes').innerHTML = MODE_ORDER.filter(function (m) { return t.rates[m] != null; }).map(function (m) { return '<button type="button" role="radio" aria-checked="' + (m === preMode) + '" class="mode-btn mode--' + m + (m === preMode ? ' is-on' : '') + '" data-act="nr-mode" data-mode="' + m + '"><i class="fas ' + MODES[m].icon + '"></i> ' + modeLabel(m, t) + '</button>'; }).join('');
  resRoomsRefresh(keep);
}
function resRoomsRefresh(keep) {
  var k = $('#nrType').value, s = resState(), sel = $('#nrRoom'), cur = keep || sel.value;
  var rooms = S.rooms.filter(function (r) { return r.type === k && r.state !== 'hors_service'; });
  sel.innerHTML = rooms.map(function (r) { var free = !s.arrival || !s.out || roomFreeFor(r.num, s.arrival, s.out); return '<option value="' + r.num + '"' + (free ? '' : ' disabled') + '>N°' + r.num + (free ? '' : ' — déjà prise') + '</option>'; }).join('');
  var firstFree = $$('option:not([disabled])', sel)[0];
  if (cur && $('option[value="' + cur + '"]:not([disabled])', sel)) { sel.value = cur; } else if (firstFree) { sel.value = firstFree.value; }
  resRefresh();
}
function resState() {
  var k = $('#nrType').value, t = TYPES[k], on = $('#nrModes .is-on'), mode = on ? on.dataset.mode : null;
  var dur = Math.max(1, Math.min(30, parseInt($('#nrDur').value, 10) || 1)), arrival = resArrival();
  var total = mode ? t.rates[mode] * dur : 0, paid = Math.min(total, parseInt(($('#nrPaid').value || '').replace(/\D/g, ''), 10) || 0);
  return { type: t, mode: mode, dur: dur, arrival: arrival, out: mode && arrival ? checkout(mode, dur, arrival, t) : null, total: total, paid: paid, room: $('#nrRoom').value };
}
function resRefresh() {
  var s = resState(), box = $('#nrTotal');
  $('#nrDurLabel').textContent = s.mode ? 'Durée (' + (s.mode === 'sejour' && s.type.until ? 'séjours' : MODES[s.mode].units) + ')' : 'Durée';
  var colors = { heure: ['var(--ek-mode-heure)', '#fff'], journee: ['var(--ek-mode-journee)', '#2e2300'], nuitee: ['var(--ek-mode-nuitee)', '#fff'], sejour: ['var(--ek-mode-sejour)', '#fff'] };
  if (s.mode) { box.style.setProperty('--pay', colors[s.mode][0]); box.style.setProperty('--pay-on', colors[s.mode][1]); }
  box.innerHTML = s.mode && s.arrival ? '<small>Arrivée : ' + shortDay(s.arrival) + ' ' + hm(s.arrival) + ' · Départ : <strong style="color:var(--ek-ink)">' + shortDay(s.out) + ' ' + hm(s.out) + '</strong></small>' + durationLabel(s.mode, s.dur, s.type) + ' × ' + fcfa(s.type.rates[s.mode]) + ' F = <span class="pay-amount">' + fcfa(s.total) + ' F</span>' : 'Choisissez un mode.';
  $('#nrStatus').innerHTML = !s.mode ? '' : s.paid >= s.total ? 'Statut : <strong>Confirmée · soldée</strong>, la chambre est garantie.' : s.paid ? 'Statut : <strong>Confirmée</strong> · reste ' + fcfa(s.total - s.paid) + ' F à payer à l\'arrivée.' : 'Statut : <strong>En attente</strong> · non garantie tant qu\'elle n\'est pas soldée.';
}
function resSubmit() {
  var s = resState(), err = $('#nrError'), name = [$('#nrFirst').value.trim(), $('#nrLast').value.trim()].filter(Boolean).join(' '), phone = $('#nrPhone').value.trim();
  var msg = !name ? 'Indiquez le nom du client.' : !phone ? 'Indiquez le téléphone du client, pour le rappeler.' : !s.arrival ? 'Choisissez la date d\'arrivée.' : s.arrival < addMin(new Date(), -5) ? 'L\'arrivée doit être dans le futur.' : !s.mode ? 'Choisissez la formule.' : !s.room || !roomFreeFor(s.room, s.arrival, s.out) ? 'Aucune chambre de ce type n\'est libre sur cette période : changez de type ou de dates.' : '';
  if (msg) { err.textContent = msg; err.hidden = false; return; }
  var z = { id: 'R' + Date.now(), ref: 'RES-' + String(++S.seq.resa).padStart(5, '0'), guest: name, phone: phone, room: s.room, arrival: s.arrival, out: s.out, mode: s.mode, dur: s.dur, total: s.total, paid: s.paid, method: s.paid ? $('#nrMethod').value : null, status: s.paid ? 'confirmee' : 'en_attente', by: 'Aïcha Koné' };
  S.reservations.push(z);
  if (s.paid) { S.payments.push({ amount: s.paid, method: z.method, dept: 'hebergement', label: (s.paid >= s.total ? 'Paiement ' : 'Acompte ') + z.ref + ' (ch. ' + z.room + ')', at: new Date() }); }
  var r = findRoom(z.room);
  if (r.state === 'libre' && z.arrival <= dayEnd()) { r.state = 'reservee'; r.resa = z; }
  var webId = $('#resForm').dataset.web; if (webId) { S.webRequests = S.webRequests.filter(function (w) { return w.id !== webId; }); }
  S.resTab = 'a-affecter';
  closeModal(); if (route() === 'reservations') { render(); } else { go('reservations'); }
  toast('Réservation ' + z.ref + ' enregistrée : chambre N°' + z.room + ' pour ' + name + '.');
}

/* ------------------------------------------------------------ sale detail */
function saleModal(id) {
  var s = S.sales.filter(function (x) { return x.id === id; })[0], rem = Math.max(0, s.total - s.paid);
  openModal({ title: 'Vente <span class="file-ref" style="font-size:.95rem">' + s.ref + '</span>',
    body: '<dl class="dl"><dt>Service</dt><dd>' + DEPTS[s.dept].label + '</dd><dt>Heure</dt><dd>' + hm(s.at) + '</dd>' + (s.label ? '<dt>Libellé</dt><dd>' + esc(s.label) + '</dd>' : '') + '<dt>Saisie par</dt><dd>' + esc(s.by) + '</dd></dl>'
      + '<div class="card card--flush" style="margin-top:16px;box-shadow:none"><table><thead><tr><th>Prestation</th><th class="text-end">Qté</th><th class="text-end">Montant</th></tr></thead><tbody>' + s.lines.map(function (l) { var it = ITEMS[l.id]; return '<tr><td>' + esc(it.name) + '</td><td class="text-end num">' + l.qty + '</td><td class="text-end num">' + fcfa(l.qty * it.price) + ' F</td></tr>'; }).join('') + (s.discount ? '<tr><td>Remise</td><td></td><td class="text-end num">− ' + fcfa(s.discount) + ' F</td></tr>' : '') + '<tr><td><strong>Total</strong></td><td></td><td class="text-end num"><strong>' + fcfa(s.total) + ' F</strong></td></tr></tbody></table></div>'
      + (s.room ? '<div class="alert alert-info" style="margin-top:14px"><i class="fas fa-bed"></i> Reportée sur la chambre N°' + s.room + ' : réglée avec le séjour.</div>' : rem > 0 ? '<div class="pay-box" style="margin-top:14px"><div class="pay-total">Reste dû : <span class="pay-amount">' + fcfa(rem) + ' F</span></div><label class="label" for="sMethod">Mode de paiement</label><select class="select" id="sMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div>' : '<div class="alert alert-info" style="margin-top:14px">Vente soldée' + (s.method ? ' (' + METHODS[s.method].label + ')' : '') + '.</div>'),
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Fermer</button>' + (!s.room && rem > 0 ? '<button type="button" class="btn btn-primary" data-act="collect-sale" data-sale="' + s.id + '"><i class="fas fa-coins"></i> Encaisser ' + fcfa(rem) + ' F</button>' : '')
  });
}

/* ------------------------------------------------------------ events: new booking + detail */
function eventModal() {
  var tomorrow = atTime(NOW, 0, 0, 2), val = tomorrow.getFullYear() + '-' + pad(tomorrow.getMonth() + 1) + '-' + pad(tomorrow.getDate());
  openModal({ size: 'xl', title: 'Nouvelle réservation de salle',
    body: '<form id="eventForm" novalidate><div class="assign-grid"><div><p class="section-label">Salle et date</p><div class="grid g2">'
      + '<div><label class="label" for="evVenue">Salle</label><select class="select" id="evVenue">' + Object.keys(VENUES).map(function (k) { var v = VENUES[k]; return '<option value="' + k + '">' + v.name + ' (' + v.capacity + ' pers.)</option>'; }).join('') + '</select></div>'
      + '<div><label class="label" for="evDate">Date</label><input class="input" type="date" id="evDate" value="' + val + '"></div>'
      + '<div><label class="label" for="evFormula">Formule</label><select class="select" id="evFormula"><option value="am">Demi-journée — matin (08h00–13h00)</option><option value="pm">Demi-journée — après-midi (14h00–19h00)</option><option value="day">Journée (08h00–18h00)</option></select></div>'
      + '<div><label class="label" for="evPeople">Participants</label><input class="input" type="number" min="1" id="evPeople" value="25"></div></div>'
      + '<p class="section-label" style="margin-top:20px">Client</p><div class="grid g2"><div><label class="label" for="evClient">Nom du contact</label><input class="input" id="evClient" placeholder="Ex. Mme Kouamé Estelle"></div><div><label class="label" for="evOrg">Organisation <span>(facultatif)</span></label><input class="input" id="evOrg" placeholder="Ex. Lycée moderne"></div></div>'
      + '<div style="margin-top:12px"><label class="label" for="evTitle">Objet <span>(facultatif)</span></label><input class="input" id="evTitle" placeholder="Ex. Réunion des parents d\'élèves"></div>'
      + '<p class="section-label" style="margin-top:20px">Matériel et services</p><div class="sale-items">' + CATALOG.evenements.map(function (it) { return '<div class="sale-item"><label class="sale-item__name" for="eq-' + it.id + '">' + esc(it.name) + '<span class="sale-item__price">' + fcfa(it.price) + ' F</span></label><div class="qty"><button type="button" data-act="ev-qty" data-step="-1" aria-label="Retirer">−</button><input type="number" min="0" max="20" id="eq-' + it.id + '" data-evqty="' + it.id + '" value="0"><button type="button" data-act="ev-qty" data-step="1" aria-label="Ajouter">+</button></div></div>'; }).join('') + '</div>'
      + '</div><div><div class="pay-box"><p class="section-label">Devis</p><ul class="sale-lines" id="evLines"></ul><div class="pay-total" id="evTotal"></div>'
      + '<label class="label" for="evDeposit">Acompte versé <span>(facultatif : sans acompte, la réservation reste une option)</span></label><input class="input" id="evDeposit" inputmode="numeric" placeholder="0"><div class="grid g2" style="margin-top:10px"><div><label class="label" for="evMethod">Mode</label><select class="select" id="evMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div></div>'
      + '<div class="form-text" id="evStatus"></div></div><div id="evError" class="alert alert-danger" hidden style="margin-top:12px"></div></div></div></form>',
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Annuler</button><button type="submit" form="eventForm" class="btn btn-primary"><i class="fas fa-check"></i> Enregistrer la réservation</button>'
  });
  eventRefresh();
}
function eventState() {
  var v = VENUES[$('#evVenue').value], f = $('#evFormula').value, base = f === 'day' ? v.day : v.half, lines = [], extras = 0;
  $$('[data-evqty]').forEach(function (i) { var q = Math.max(0, parseInt(i.value, 10) || 0); if (q) { lines.push({ id: i.dataset.evqty, qty: q }); extras += q * ITEMS[i.dataset.evqty].price; } i.closest('.sale-item').classList.toggle('is-picked', q > 0); });
  var total = base + extras, dep = Math.min(total, parseInt(($('#evDeposit').value || '').replace(/\D/g, ''), 10) || 0);
  return { venueKey: $('#evVenue').value, v: v, f: f, base: base, lines: lines, total: total, deposit: dep };
}
function eventRefresh() {
  var s = eventState();
  $('#evLines').innerHTML = '<li><span>' + s.v.name + ' — ' + (s.f === 'day' ? 'Journée' : 'Demi-journée') + '</span><strong class="num">' + fcfa(s.base) + ' F</strong></li>' + s.lines.map(function (l) { var it = ITEMS[l.id]; return '<li><span>' + l.qty + ' × ' + esc(it.name) + '</span><strong class="num">' + fcfa(l.qty * it.price) + ' F</strong></li>'; }).join('');
  $('#evTotal').innerHTML = 'Total : <span class="pay-amount">' + fcfa(s.total) + ' F</span>';
  $('#evStatus').innerHTML = s.deposit ? 'Statut : <strong>Confirmée</strong> · reste dû ' + fcfa(s.total - s.deposit) + ' F' : 'Statut : <strong>Option (devis)</strong>';
  var p = parseInt($('#evPeople').value, 10) || 0;
  if (p > s.v.capacity) { $('#evStatus').innerHTML += '<br><span class="text-danger">Attention : ' + s.v.name + ' accueille ' + s.v.capacity + ' personnes au maximum.</span>'; }
}
function eventSubmit() {
  var s = eventState(), err = $('#evError'), client = $('#evClient').value.trim(), date = $('#evDate').value;
  if (!client || !date) { err.textContent = !client ? 'Indiquez le nom du contact.' : 'Choisissez une date.'; err.hidden = false; return; }
  var parts = date.split('-'), d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
  var start = s.f === 'pm' ? atTime(d, 14) : atTime(d, 8), end = s.f === 'day' ? atTime(d, 18) : s.f === 'pm' ? atTime(d, 19) : atTime(d, 13);
  var clash = S.events.filter(function (e) { return e.venue === s.venueKey && e.status !== 'annulee' && e.start < end && e.end > start; })[0];
  if (clash) { err.textContent = s.v.name + ' est déjà réservée sur ce créneau (' + clash.client + ').'; err.hidden = false; return; }
  var e = { id: 'E' + Date.now(), ref: 'FV-' + String(++S.seq.invoice).padStart(6, '0'), venue: s.venueKey, formula: s.f === 'day' ? 'day' : 'half', start: start, end: end, client: client, org: $('#evOrg').value.trim(), title: $('#evTitle').value.trim(), participants: parseInt($('#evPeople').value, 10) || 1, items: s.lines, total: s.total, paid: s.deposit, status: s.deposit ? 'confirmee' : 'option' };
  S.events.push(e);
  if (s.deposit) { S.payments.push({ amount: s.deposit, method: $('#evMethod').value, dept: 'evenements', label: 'Acompte ' + e.ref, at: new Date() }); }
  S.eventTab = 'avenir';
  closeModal(); render();
  toast('Réservation de la ' + s.v.name + ' enregistrée (' + (s.deposit ? 'confirmée' : 'option') + ').');
}
function eventDetail(id) {
  var e = S.events.filter(function (x) { return x.id === id; })[0], v = VENUES[e.venue], st = evState(e), rem = Math.max(0, e.total - e.paid), base = e.formula === 'day' ? v.day : v.half;
  var live = e.status !== 'annulee' && e.end >= NOW;
  openModal({ title: (e.title ? esc(e.title) : 'Réservation') + ' <span class="badge bg-' + st[0] + '" style="vertical-align:middle">' + st[1] + '</span>',
    body: '<dl class="dl"><dt>Salle</dt><dd>' + v.name + ' · ' + (e.formula === 'day' ? 'Journée' : 'Demi-journée') + '</dd><dt>Date</dt><dd>' + longDate(e.start) + ', ' + hm(e.start) + ' – ' + hm(e.end) + '</dd><dt>Client</dt><dd>' + esc(e.client) + (e.org ? ' · ' + esc(e.org) : '') + '</dd><dt>Participants</dt><dd>' + e.participants + ' / ' + v.capacity + ' places</dd></dl>'
      + '<div class="card card--flush" style="margin-top:16px;box-shadow:none"><table><tbody><tr><td>Location ' + v.name + '</td><td class="text-end num">' + fcfa(base) + ' F</td></tr>' + e.items.map(function (l) { var it = ITEMS[l.id]; return '<tr><td>' + l.qty + ' × ' + esc(it.name) + '</td><td class="text-end num">' + fcfa(l.qty * it.price) + ' F</td></tr>'; }).join('') + '<tr><td><strong>Total</strong></td><td class="text-end num"><strong>' + fcfa(e.total) + ' F</strong></td></tr><tr><td>Déjà versé</td><td class="text-end num">' + fcfa(e.paid) + ' F</td></tr></tbody></table></div>'
      + (live && rem > 0 ? '<div class="pay-box" style="margin-top:14px"><div class="pay-total">Reste dû : <span class="pay-amount">' + fcfa(rem) + ' F</span></div><div class="grid g2"><div><label class="label" for="edAmount">Montant encaissé</label><input class="input" id="edAmount" inputmode="numeric" value="' + rem + '"></div><div><label class="label" for="edMethod">Mode</label><select class="select" id="edMethod">' + Object.keys(METHODS).map(function (m) { return '<option value="' + m + '">' + METHODS[m].label + '</option>'; }).join('') + '</select></div></div></div>' : ''),
    foot: '<button type="button" class="btn btn-outline" data-act="close-modal">Fermer</button>' + (live && e.status === 'option' ? '<button type="button" class="btn btn-outline-primary" data-act="cancel-event" data-event="' + e.id + '">Annuler l\'option</button>' : '') + (live && rem > 0 ? '<button type="button" class="btn btn-primary" data-act="pay-event" data-event="' + e.id + '"><i class="fas fa-coins"></i> Encaisser</button>' : '')
  });
}

/* ------------------------------------------------------------ cash detail */
function cashModal() {
  var list = S.payments.slice().sort(function (a, b) { return b.at - a.at; }), c = cash();
  openModal({ title: 'Ma recette du jour — détail',
    body: '<div class="m-rows mob-only">' + list.map(function (p) { return '<div class="m-row"><div>' + esc(p.label) + '<div class="small muted">' + hm(p.at) + ' · ' + METHODS[p.method].label + '</div></div><strong>' + fcfa(p.amount) + ' F</strong></div>'; }).join('') + '<div class="m-row"><div><strong>Total</strong></div><strong>' + fcfa(c.total) + ' F</strong></div></div>'
      + '<div class="card card--flush desk-only" style="box-shadow:none"><div class="table-wrap"><table><thead><tr><th>Heure</th><th>Encaissement</th><th>Mode</th><th class="text-end">Montant</th></tr></thead><tbody>' + list.map(function (p) { return '<tr><td class="num">' + hm(p.at) + '</td><td>' + esc(p.label) + '</td><td class="nowrap">' + METHODS[p.method].label + '</td><td class="text-end num nowrap">' + fcfa(p.amount) + ' F</td></tr>'; }).join('') + '<tr><td></td><td><strong>Total</strong></td><td></td><td class="text-end num nowrap"><strong>' + fcfa(c.total) + ' F</strong></td></tr></tbody></table></div></div>',
    foot: '<button type="button" class="btn btn-primary" data-act="close-modal">Fermer</button>' });
}

/* ------------------------------------------------------------ render */
function render() {
  NOW = new Date();
  var r = route();
  renderChrome(r);
  var html = r === 'chambres' ? pageRooms() : r === 'reservations' ? pageReservations() : r === 'restauration' ? pageSales('restauration') : r === 'plage' ? pageSales('plage') : r === 'evenements' ? pageEvents() : r === 'vente-restauration' ? pageNewSale('restauration') : r === 'vente-plage' ? pageNewSale('plage') : pageHome();
  $('#app').innerHTML = html;
  if (r.indexOf('vente-') === 0) { saleRefresh(); }
}
var lastRoute = null;
window.addEventListener('hashchange', function () { closeModal(); render(); if (lastRoute !== route()) { window.scrollTo(0, 0); } lastRoute = route(); });

/* ------------------------------------------------------------ events (delegation) */
document.addEventListener('click', function (ev) {
  var root = ev.target.closest('[data-modal-root]');
  if (root && ev.target === root) { closeModal(); return; }
  var el = ev.target.closest('[data-act]'); if (!el) { return; }
  var act = el.dataset.act, num = el.dataset.room;
  switch (act) {
    case 'close-modal': closeModal(); break;
    case 'goto-rooms': S.roomTab = el.dataset.state; go('chambres'); break;
    case 'room-tab': S.roomTab = el.dataset.state; render(); break;
    case 'assign': assignModal(num); break;
    case 'open-stay': stayModal(num); break;
    case 'open-resa': resaModal(el.dataset.res); break;
    case 'goto-res': S.resTab = 'a-affecter'; go('reservations'); break;
    case 'res-tab': S.resTab = el.dataset.tab; render(); break;
    case 'new-res': newResModal(null); break;
    case 'web-requests': webRequestsModal(); break;
    case 'convert-web': newResModal(S.webRequests.filter(function (w) { return w.id === el.dataset.web; })[0]); break;
    case 'nr-mode':
      $$('.mode-btn', el.parentNode).forEach(function (b) { b.classList.toggle('is-on', b === el); b.setAttribute('aria-checked', String(b === el)); });
      $('#nrError').hidden = true; resRoomsRefresh(); break;
    case 'cancel-res': cancelResModal(el.dataset.res); break;
    case 'confirm-cancel-res': {
      var zc = findRes(el.dataset.res), rz = findRoom(zc.room);
      zc.status = 'annulee'; if (rz.resa === zc) { rz.state = 'libre'; rz.resa = null; }
      closeModal(); render(); toast('Réservation ' + zc.ref + ' annulée.' + (rz.state === 'libre' ? ' La chambre N°' + rz.num + ' est libre.' : '')); break;
    }
    case 'cash-detail': cashModal(); break;
    case 'ready': ev.stopPropagation(); findRoom(num).state = 'libre'; render(); toast('Chambre N°' + num + ' prête : elle est de nouveau libre.'); break;
    case 'pick-mode':
      $$('.mode-btn', el.parentNode).forEach(function (b) { b.classList.toggle('is-on', b === el); b.setAttribute('aria-checked', String(b === el)); });
      $('#aError').hidden = true; assignRefresh(); break;
    case 'checkout': {
      var rc = findRoom(num), owed = S.sales.filter(function (s) { return s.room === rc.num && !s.cancelled && s.paid < s.total; });
      owed.forEach(function (s) { S.payments.push({ amount: s.total - s.paid, method: 'especes', dept: s.dept, label: s.ref + ' · réglée à la sortie (ch. ' + rc.num + ')', at: new Date() }); s.paid = s.total; s.method = 'especes'; });
      var g = rc.stay.guest; rc.state = 'nettoyage'; rc.stay = null; closeModal(); render();
      toast('Sortie validée pour ' + g + '. La chambre N°' + num + ' passe en nettoyage.'); break;
    }
    case 'extend': {
      var re = findRoom(num), x = extendRefresh(re);
      re.stay.out = x.out; re.stay.dur += x.d; re.stay.amount += x.amount;
      S.payments.push({ amount: x.amount, method: $('#eMethod').value, dept: 'hebergement', label: 'Prolongation chambre N°' + num, at: new Date() });
      closeModal(); render(); toast('Séjour prolongé jusqu\'au ' + dmhm(x.out) + ' : ' + fcfa(x.amount) + ' F encaissés.'); break;
    }
    case 'checkin': {
      var z = findRes(el.dataset.res), rr = findRoom(z.room), t = TYPES[rr.type], total = z.total, rem = total - z.paid, now = new Date();
      if (rem > 0) { S.payments.push({ amount: rem, method: $('#rMethod').value, dept: 'hebergement', label: 'Solde ' + z.ref + ' (ch. N°' + rr.num + ')', at: now }); }
      rr.state = 'occupee'; rr.stay = { guest: z.guest, phone: z.phone, mode: z.mode, dur: z.dur, in: now, out: checkout(z.mode, z.dur, now, t), amount: total, method: rem > 0 ? $('#rMethod').value : z.method }; rr.resa = null;
      z.status = 'honoree'; z.paid = total;
      closeModal(); render(); toast('Check-in effectué : ' + z.guest + ' s\'installe en chambre N°' + rr.num + '.'); break;
    }
    case 'qty': {
      var dept = $('#saleForm').dataset.dept, dr = S.draft[dept], id = el.dataset.item;
      dr.qty[id] = Math.max(0, Math.min(99, (dr.qty[id] || 0) + (+el.dataset.step))); $('#q-' + id).value = dr.qty[id]; $('#saleError').hidden = true; saleRefresh(); break;
    }
    case 'open-sale': saleModal(el.dataset.sale); break;
    case 'collect-sale': {
      var s = S.sales.filter(function (x) { return x.id === el.dataset.sale; })[0], due = s.total - s.paid, m = $('#sMethod').value;
      S.payments.push({ amount: due, method: m, dept: s.dept, label: s.ref + ' · ' + DEPTS[s.dept].label, at: new Date() }); s.paid = s.total; s.method = m;
      closeModal(); render(); toast(fcfa(due) + ' F encaissés pour la vente ' + s.ref + '.'); break;
    }
    case 'event-tab': S.eventTab = el.dataset.tab; render(); break;
    case 'new-event': eventModal(); break;
    case 'ev-qty': { var inp = el.parentNode.querySelector('input'); inp.value = Math.max(0, Math.min(20, (parseInt(inp.value, 10) || 0) + (+el.dataset.step))); eventRefresh(); break; }
    case 'open-event': eventDetail(el.dataset.event); break;
    case 'pay-event': {
      var e = S.events.filter(function (x) { return x.id === el.dataset.event; })[0], amt = Math.min(e.total - e.paid, parseInt(($('#edAmount').value || '').replace(/\D/g, ''), 10) || 0);
      if (!amt) { return; }
      e.paid += amt; if (e.status === 'option') { e.status = 'confirmee'; }
      S.payments.push({ amount: amt, method: $('#edMethod').value, dept: 'evenements', label: (e.paid >= e.total ? 'Solde ' : 'Acompte ') + e.ref, at: new Date() });
      closeModal(); render(); toast(fcfa(amt) + ' F encaissés. ' + (e.paid >= e.total ? 'Réservation soldée.' : 'Réservation confirmée.')); break;
    }
    case 'cancel-event': { var ec = S.events.filter(function (x) { return x.id === el.dataset.event; })[0]; ec.status = 'annulee'; closeModal(); render(); toast('Option annulée : le créneau est libéré.'); break; }
  }
});
document.addEventListener('keydown', function (ev) {
  if (ev.key === 'Escape' && $('[data-modal-root]')) { closeModal(); return; }
  if ((ev.key === 'Enter' || ev.key === ' ') && ev.target.matches('.room.is-clickable')) { ev.preventDefault(); ev.target.click(); }
});
document.addEventListener('input', function (ev) {
  var t = ev.target;
  if (t.closest('#assignForm')) { $('#aError').hidden = true; assignRefresh(); return; }
  if (t.closest('#eventForm')) { $('#evError').hidden = true; eventRefresh(); return; }
  if (t.closest('#resForm')) {
    $('#nrError').hidden = true;
    if (t.id === 'nrType') { var on = $('#nrModes .is-on'); resTypeChanged(on && TYPES[t.value].rates[on.dataset.mode] != null ? on.dataset.mode : null); }
    else if (t.id === 'nrDate' || t.id === 'nrTime' || t.id === 'nrDur') { resRoomsRefresh(); }
    else { resRefresh(); }
    return;
  }
  if (t.id === 'resRoomFilter') { var q = t.value.trim(); $$('[data-room-row]').forEach(function (tr) { tr.hidden = q !== '' && tr.dataset.roomRow.indexOf(q) !== 0; }); return; }
  var f = t.closest('#saleForm'); if (f) {
    var dr = S.draft[f.dataset.dept];
    if (t.dataset.qty) { dr.qty[t.dataset.qty] = Math.max(0, Math.min(99, parseInt(t.value, 10) || 0)); }
    else if (t.id === 'saleLabel') { dr.label = t.value; }
    else if (t.id === 'saleRoom') { dr.room = t.value; }
    else if (t.id === 'saleDiscount') { dr.discount = t.value; }
    else if (t.id === 'saleMethod') { dr.method = t.value; }
    else if (t.id === 'saleReceived') { dr.received = t.value; }
    $('#saleError').hidden = true; saleRefresh(); return;
  }
  if (t.id === 'eDur') { var m = $('[data-act="extend"]'); if (m) { extendRefresh(findRoom(m.dataset.room)); } }
});
document.addEventListener('change', function (ev) { var t = ev.target; if (t.tagName === 'SELECT') { t.dispatchEvent(new Event('input', { bubbles: true })); } });
document.addEventListener('submit', function (ev) {
  ev.preventDefault();
  if (ev.target.id === 'assignForm') { assignSubmit(); }
  else if (ev.target.id === 'saleForm') { saleSubmit(ev.target.dataset.dept); }
  else if (ev.target.id === 'eventForm') { eventSubmit(); }
  else if (ev.target.id === 'resForm') { resSubmit(); }
});

/* demo controls */
function applyNotes() { var hidden = store('ekw-demo-notes') === 'off'; document.body.classList.toggle('hide-notes', hidden); $('#toggleNotesLbl').textContent = (window.matchMedia && window.matchMedia('(max-width: 600px)').matches) ? 'Aide' : (hidden ? 'Afficher les explications' : 'Masquer les explications'); }
$('#toggleNotes').addEventListener('click', function () { store('ekw-demo-notes', document.body.classList.contains('hide-notes') ? 'on' : 'off'); applyNotes(); });
$('#resetDemo').addEventListener('click', function () { S = seed(); closeModal(); render(); toast('Démo réinitialisée : les données d\'exemple sont revenues.'); });

applyNotes();
lastRoute = route();
render();
setInterval(function () { if (!$('[data-modal-root]') && route() === 'accueil') { render(); } }, 60000);
})();
</script>
@endverbatim
</body>
</html>
