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
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>gm_v3 - Assistente AI</title>
<link rel="manifest" href="assets/manifest.webmanifest">
<meta name="theme-color" content="#111827"/>

<!-- MODIFICHE PUNTO 5: Aggiunta VAPID key e FullCalendar -->
<meta name="vapid-key" content="<?= htmlspecialchars(env_get('VAPID_PUBLIC_KEY','')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
<!-- FINE MODIFICHE -->

<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div id="root"></div>
<script src="assets/js/core.js"></script>
<script src="assets/js/ui.js"></script>

<!-- MODIFICHE PUNTO 5: Import per le notifiche push -->
<script type="module">
  import { enablePush } from './assets/js/push.js';
  window.gm_enablePush = enablePush;
</script>
<!-- FINE MODIFICHE -->

</body>
</html>
