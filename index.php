<?php
session_start();
require_once __DIR__.'/_core/helpers.php';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>gm_v3 - Assistente AI</title>
<link rel="manifest" href="assets/manifest.webmanifest">
<meta name="theme-color" content="#111827"/>
<style>
:root{--bg:#0f172a;--panel:#111827;--fg:#e5e7eb;--muted:#94a3b8;--accent:#7c3aed;--ok:#10b981;--warn:#f59e0b;--danger:#ef4444;--card:#0b1220}
*{box-sizing:border-box;margin:0;padding:0}
body{font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--fg);min-height:100vh}
.app{display:flex;min-height:100vh}
aside{width:220px;background:#0b1220;border-right:1px solid #1f2937;padding:20px;flex-shrink:0}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:18px;margin-bottom:24px}
.nav a{display:block;padding:12px 16px;border-radius:10px;color:var(--fg);text-decoration:none;margin:6px 0;background:#121a2e;transition:all .2s}
.nav a:hover{background:#1e293b;transform:translateX(4px)}
.nav a.active{background:var(--accent);color:#fff}
main{flex:1;padding:24px 32px;overflow-y:auto}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:20px 0}
.card{background:var(--panel);border:1px solid #1f2937;border-radius:14px;padding:20px}
.card h3{margin-bottom:16px;color:var(--accent)}
.drop{border:2px dashed #374151;border-radius:12px;min-height:180px;display:flex;align-items:center;justify-content:center;margin:16px 0;color:var(--muted);cursor:pointer;transition:all .3s}
.drop:hover{border-color:var(--accent);background:rgba(124,58,237,.05)}
.btn{background:var(--accent);border:none;color:#fff;padding:12px 20px;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;transition:all .2s}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(124,58,237,.4)}
.btn.secondary{background:#374151}
.btn.warn{background:var(--warn)}
.btn.del{background:var(--danger);padding:8px 14px;font-size:13px}
.banner{background:#1f2937;border:1px solid #374151;border-left:4px solid var(--warn);padding:16px;border-radius:10px;color:#fbbf24;margin:20px 0}
.ads{height:90px;background:linear-gradient(135deg,#1f2937,#0b1220);border:1px dashed #374151;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:13px;margin:20px 0}
.hidden{display:none}
.auth-container{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e293b);padding:20px}
.auth-box{background:#0b1220;padding:40px;border-radius:16px;border:1px solid #1f2937;box-shadow:0 20px 60px rgba(0,0,0,.5);max-width:440px;width:100%}
.auth-box h1{font-size:28px;margin-bottom:8px;background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.auth-box p{color:var(--muted);margin-bottom:32px;font-size:14px}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:500;font-size:13px;color:var(--muted)}
input,select{width:100%;padding:12px 16px;border-radius:10px;border:1px solid #374151;background:#0b1220;color:#e5e7eb;font-size:14px;transition:border .2s}
input:focus,select:focus{outline:none;border-color:var(--accent)}
input::placeholder{color:#4b5563}
.btn-group{display:flex;gap:12px;margin-top:24px}
.btn-group .btn{flex:1}
.link-btn{background:none;border:none;color:var(--accent);cursor:pointer;text-decoration:underline;font-size:13px;margin-top:16px;display:inline-block}
.link-btn:hover{color:#a78bfa}
.error{color:var(--danger);font-size:13px;margin-top:8px}
.success{color:var(--ok);font-size:13px;margin-top:8px}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{border-bottom:1px solid #1f2937;padding:12px 8px;text-align:left}
th{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase}
h1{margin-bottom:8px}
</style>
</head>
<body>
<div id="root"></div>
<script src="<?php echo htmlspecialchars($GLOBALS['_index_html_from_artifact'] ?? 'data:text/plain,'); ?>"></script>
<script type="module">
// JavaScript code will be inserted here by the artifact
</script>
</body>
</html>
