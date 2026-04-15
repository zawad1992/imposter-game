<?php
/**
 * game.php – Game Screen
 * Shows the discussion instructions, player list,
 * and a countdown timer. Players discuss and give
 * hints. Leads to the voting/result screen.
 */

session_start();

/* ── Guard: must have an active game ────────────────────────── */
if (empty($_SESSION['game'])) {
    header('Location: index.php');
    exit;
}

$game         = $_SESSION['game'];
$totalPlayers = $game['player_count'];
$category     = $game['category'];
$timerSeconds = (int)($game['timer'] ?? 90);

// Validate timer value is sensible
if ($timerSeconds < 30 || $timerSeconds > 600) {
    $timerSeconds = 90;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#6366f1">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Game – Imposter Game</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
</head>
<body>

<div id="gameScreen" class="page-wrapper" data-timer="<?= $timerSeconds ?>">

  <!-- Header -->
  <div class="ig-header anim-fade-up">
    <a href="index.php" class="btn-ig-secondary" style="width:auto;padding:0.4rem 0.9rem;font-size:0.85rem;">← New Game</a>
    <span class="game-title" style="font-size:1.6rem;">imposter</span>
    <button id="darkToggle" class="dark-toggle" aria-label="Toggle theme">☀️</button>
  </div>

  <div class="content-card stagger">

    <!-- Category badge -->
    <div class="text-center mb-3">
      <span class="category-chip">📂 <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <!-- Hint instruction box -->
    <div class="hint-box anim-fade-up">
      🗣 Give a <strong>one-word hint</strong> about the secret word.<br>
      <span class="text-muted-ig" style="font-size:0.88rem;">Don't say the word directly!</span>
    </div>

    <!-- Timer ring -->
    <div class="timer-ring">
      <svg viewBox="0 0 120 120" width="120" height="120">
        <circle class="timer-circle-bg" cx="60" cy="60" r="50"/>
        <circle id="timerRingFg" class="timer-circle-fg" cx="60" cy="60" r="50"
                stroke-dasharray="314" stroke-dashoffset="0"/>
      </svg>
      <div class="timer-value" id="timerValue"><?= $timerSeconds ?></div>
    </div>

    <!-- Timer end message (hidden initially) -->
    <div id="timerEndMsg" class="text-center mb-2" style="display:none;">
      <span class="text-accent fw-900" style="font-size:1.1rem;">⏰ Time's up! Start voting.</span>
    </div>

    <!-- Timer controls -->
    <div class="d-flex gap-2 mb-4">
      <button id="btnStartTimer" class="btn-ig-primary" data-running="false" style="flex:2;">
        ▶ Start
      </button>
      <button id="btnResetTimer" class="btn-ig-secondary" style="flex:1;width:auto;">
        ↺
      </button>
    </div>

    <div class="ig-divider"></div>

    <!-- Player list -->
    <div class="mb-3">
      <label class="section-label d-block mb-2">Players (<?= $totalPlayers ?>)</label>
      <ul class="player-list" id="playerList">
        <?php for ($i = 0; $i < $totalPlayers; $i++):
          $pName = htmlspecialchars($game['player_names'][$i] ?? ('Player ' . ($i + 1)), ENT_QUOTES, 'UTF-8');
        ?>
        <li class="player-list-item anim-fade-up" style="animation-delay: <?= ($i + 1) * 0.04 ?>s">
          <div class="avatar"><?= $i + 1 ?></div>
          <span><?= $pName ?></span>
        </li>
        <?php endfor; ?>
      </ul>
    </div>

    <div class="ig-divider"></div>

    <!-- Rules reminder -->
    <div class="mb-4">
      <label class="section-label d-block mb-2">Rules</label>
      <ul class="text-muted-ig" style="font-size:0.9rem; padding-left:1.2rem; margin:0;">
        <li>Take turns giving one hint each.</li>
        <li>The <strong>imposter</strong> doesn't know the word — they must bluff.</li>
        <li>After everyone hints, vote for who you think the imposter is.</li>
        <li>If the imposter is not caught, they win!</li>
      </ul>
    </div>

    <!-- Go to voting button -->
    <a href="result.php" class="btn-ig-danger d-block text-center text-decoration-none" style="display:flex!important;align-items:center;justify-content:center;gap:0.5rem;">
      🗳 Go to Voting
    </a>

  </div><!-- /.content-card -->

</div><!-- /#gameScreen -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
