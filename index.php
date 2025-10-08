<?php
/**
 * index.php (shell minimale)
 * Spezzato in: assets/css/app.css, assets/js/core.js, assets/js/ui.js
 */
session_start();
require_once __DIR__.'/_core/helpers.php';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width-device-width,initial-scale=1"/>
<title>gm_v3 - Assistente AI</title>
<link rel="manifest" href="assets/manifest.webmanifest">
<meta name="theme-color" content="#111827"/>

<!-- FullCalendar CSS e JS (caricato da unpkg) -->
<link rel="stylesheet" href="https://unpkg.com/fullcalendar@6.1.15/index.global.min.css">
<script src="https://unpkg.com/fullcalendar@6.1.15/index.global.min.js"></script>

<!-- CSS dell'applicazione -->
<link rel="stylesheet" href="assets/css/app.css">

<!-- Meta VAPID key -->
<meta name="vapid-key" content="<?= htmlspecialchars(env_get('VAPID_PUBLIC_KEY','')) ?>">
</head>
<body>

<div id="app"></div>

<!-- Script dell'applicazione (caricati dopo) -->
<script src="assets/js/core.js" defer></script>
<script src="assets/js/ui.js" defer></script>

<!-- Modulo per le notifiche push -->
<script type="module">
  import { enablePush } from './assets/js/push.js';
  window.gm_enablePush = enablePush;
</script>

</body>
</html>

