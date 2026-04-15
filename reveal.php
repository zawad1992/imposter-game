<?php
/**
 * reveal.php – Player Reveal Screen
 * Each player taps "Reveal My Role" to see their word or
 * discover they are the imposter. Roles are shown one at
 * a time; the device is passed between players.
 */

session_start();

/* ── Guard: must have an active game ────────────────────────── */
if (empty($_SESSION['game'])) {
    header('Location: index.php');
    exit;
}

$game = &$_SESSION['game'];

/* ── Determine current player slot ──────────────────────────── */
// "revealed" stores how many players have already viewed their role.
$revealedCount = count($game['revealed']);
$totalPlayers  = $game['player_count'];

// All players have revealed → go to game screen
if ($revealedCount >= $totalPlayers) {
    header('Location: game.php');
    exit;
}

$currentSlot = $revealedCount; // 0-based index into $game['players']

/* ── Handle "Next Player" post ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'next') {
        // Mark current slot as revealed
        if (!in_array($currentSlot, $game['revealed'], true)) {
            $game['revealed'][] = $currentSlot;
        }

        // Flush session
        session_write_close();
        session_start();

        // Redirect back to reveal.php (now pointing at next player)
        header('Location: reveal.php');
        exit;
    }
}

/* ── Determine role for current player ──────────────────────── */
$isImposter  = ($currentSlot === $game['imposter_index']);
$playerNum   = $currentSlot + 1; // 1-based display
$playerName  = htmlspecialchars($game['player_names'][$currentSlot] ?? ('Player ' . $playerNum), ENT_QUOTES, 'UTF-8');
$word        = $game['word'];
$category    = $game['category'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#6366f1">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Reveal – Imposter Game</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
</head>
<body>

<div class="page-wrapper">

  <!-- Header -->
  <div class="ig-header anim-fade-up">
    <span class="game-title">imposter</span>
    <button id="darkToggle" class="dark-toggle" aria-label="Toggle theme">☀️</button>
  </div>

  <!-- Progress dots -->
  <div class="player-queue anim-fade-up" aria-label="Player progress">
    <?php for ($i = 0; $i < $totalPlayers; $i++): ?>
      <div class="player-dot <?= $i < $revealedCount ? 'revealed' : ($i === $currentSlot ? 'current' : '') ?>"
           title="<?= htmlspecialchars($game['player_names'][$i] ?? ('Player ' . ($i + 1)), ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php endfor; ?>
  </div>

  <!-- Main card -->
  <div class="content-card">

    <!-- Step label -->
    <p class="step-indicator">
      <?= $playerName ?> (<?= $playerNum ?> of <?= $totalPlayers ?>)
    </p>

    <!-- Instruction shown before reveal -->
    <div id="preReveal" class="text-center anim-fade-up">
      <div style="font-size:4rem; margin-bottom:0.5rem;">🤫</div>
      <h2 class="fw-900 mb-1" style="font-size:1.4rem;">
        Ready, <?= $playerName ?>?
      </h2>
      <p class="text-muted-ig mb-4">
        Make sure no one else can see the screen, then tap to reveal your secret role.
      </p>
      <button id="btnReveal" class="btn-ig-primary">
        👁 Reveal My Role
      </button>
    </div>

    <!-- Role content (hidden until reveal) -->
    <div id="roleContent"
         class="blurred-content"
         data-role="<?= $isImposter ? 'imposter' : 'normal' ?>"
         aria-hidden="true"
         style="display:none;">

      <?php if ($isImposter): ?>
      <!-- IMPOSTER card -->
      <div class="role-card imposter text-center">
        <span class="role-badge badge-imposter">🔴 Imposter</span>
        <span class="role-emoji">😈</span>
        <div class="role-word" style="color:var(--ig-danger);">YOU ARE<br>THE IMPOSTER</div>
        <p class="mt-3 text-muted-ig mb-0" style="font-size:0.9rem;">
          You don't know the word.<br>Blend in — don't get caught!
        </p>
      </div>

      <?php else: ?>
      <!-- NORMAL PLAYER card -->
      <div class="role-card normal text-center">
        <span class="role-badge badge-normal">🟢 Player</span>
        <span class="role-emoji">🕵️</span>
        <p class="text-muted-ig mb-1" style="font-size:0.85rem;">The secret word is</p>
        <div class="role-word"><?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="mt-2">
          <span class="category-chip"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <p class="mt-3 text-muted-ig mb-0" style="font-size:0.9rem;">
          Give hints about the word without saying it directly.
        </p>
      </div>
      <?php endif; ?>

    </div><!-- /#roleContent -->

    <!-- "Next Player" form – shown after reveal -->
    <form method="POST" action="reveal.php" style="display:none;" id="nextForm">
      <input type="hidden" name="action" value="next">
      <div class="ig-divider"></div>
      <?php if ($currentSlot + 1 < $totalPlayers): ?>
        <button type="submit" id="btnNext" class="btn-ig-secondary">
          ➡ Next Player
        </button>
      <?php else: ?>
        <button type="submit" id="btnNext" class="btn-ig-primary">
          🎮 Start the Game
        </button>
      <?php endif; ?>
    </form>

  </div><!-- /.content-card -->

  <p class="text-muted-ig text-center mt-3" style="font-size:0.78rem;">
    Pass the phone — don't peek!
  </p>

</div><!-- /.page-wrapper -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  /**
   * Reveal screen jQuery logic.
   * Inlined here because this behaviour is tightly coupled to the
   * markup above; the generic reveal handler in app.js delegates to
   * the #revealScreen guard which won't fire here.
   */
  $(document).ready(function () {
    $('#btnReveal').on('click', function () {
      var $btn     = $(this);
      var $pre     = $('#preReveal');
      var $content = $('#roleContent');
      var $form    = $('#nextForm');
      var role     = $content.data('role');

      $btn.prop('disabled', true);

      $pre.fadeOut(250, function () {
        // Show the role content
        $content
          .removeAttr('aria-hidden')
          .show()
          .removeClass('blurred-content')
          .addClass('anim-pop-in revealed');

        // Sound effect
        playRevealSound(role === 'imposter');

        // Shake + glow animation for imposter
        if (role === 'imposter') {
          setTimeout(function () {
            $content.find('.role-card').addClass('anim-shake anim-pulse-glow');
          }, 400);
        }

        // Show next/start button
        $form.fadeIn(350);
      });
    });
  });
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
