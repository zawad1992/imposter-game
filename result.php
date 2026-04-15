<?php
/**
 * result.php – Voting & Result Screen
 *
 * Phase 1 (GET):  Voting form – each player selects who they think the imposter is.
 *                 Votes are tracked per player (stored in session).
 * Phase 2 (POST, action=vote): Record a single vote, then advance to next voter.
 * Phase 3 (all voted): Show tally, reveal actual imposter and word.
 */

session_start();

/* ── Guard ───────────────────────────────────────────────────── */
if (empty($_SESSION['game'])) {
    header('Location: index.php');
    exit;
}

$game         = &$_SESSION['game'];
$totalPlayers = (int)$game['player_count'];
$imposterIdx  = (int)$game['imposter_index'];
$allImposters = !empty($game['all_imposters']);
$word         = $game['word'];
$category     = $game['category'];

// Initialise votes array if not present
if (!isset($game['votes'])) {
    $game['votes'] = [];
}

/* ── Handle vote submission ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vote') {
    $voterSlot    = filter_input(INPUT_POST, 'voter_slot',    FILTER_VALIDATE_INT);
    $votedForSlot = filter_input(INPUT_POST, 'voted_for',     FILTER_VALIDATE_INT);

    if ($voterSlot !== false && $votedForSlot !== false
        && $voterSlot  >= 0 && $voterSlot  < $totalPlayers
        && $votedForSlot >= 0 && $votedForSlot < $totalPlayers
        && $voterSlot !== $votedForSlot                         // can't vote for yourself
        && !isset($game['votes'][$voterSlot])                  // no double-voting
    ) {
        $game['votes'][$voterSlot] = $votedForSlot;
    }

    session_write_close();
    session_start();
    header('Location: result.php');
    exit;
}

/* ── Handle "Play Again" reset ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    session_destroy();
    header('Location: index.php');
    exit;
}

/* ── Determine current voting phase ─────────────────────────── */
$votedCount   = count($game['votes']);
$allVoted     = ($votedCount >= $totalPlayers);

// Which player is voting next?
// Players skip voting for themselves, but we still walk 0..n-1
// Here each slot votes once; $votedCount indexes the next voter.
$currentVoter = $votedCount; // 0-based slot

/* ── Compute tally (only when all voted) ────────────────────── */
$tally  = array_fill(0, $totalPlayers, 0);
$winner = ''; // 'town' | 'imposter'

if ($allVoted) {
    foreach ($game['votes'] as $voted) {
        if (isset($tally[$voted])) {
            $tally[$voted]++;
        }
    }
    // Find most-voted player
    $maxVotes    = max($tally);
    $mostVoted   = array_search($maxVotes, $tally, true);
    $wasCorrect  = ($mostVoted === $imposterIdx);
    $winner      = $wasCorrect ? 'town' : 'imposter';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#6366f1">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Vote – Imposter Game</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="manifest" href="manifest.json">
</head>
<body>

<div id="votingScreen" class="page-wrapper">

  <!-- Header -->
  <div class="ig-header anim-fade-up">
    <a href="game.php" class="btn-ig-secondary" style="width:auto;padding:0.4rem 0.9rem;font-size:0.85rem;">← Back</a>
    <span class="game-title" style="font-size:1.6rem;">imposter</span>
    <button id="darkToggle" class="dark-toggle" aria-label="Toggle theme">☀️</button>
  </div>

  <div class="content-card stagger">

    <?php if (!$allVoted): ?>
    <!-- ══════════════════════════════════════════════════════
         PHASE 1 – Voting
         ══════════════════════════════════════════════════════ -->

    <p class="step-indicator">
      <?= htmlspecialchars($game['player_names'][$currentVoter] ?? ('Player ' . ($currentVoter + 1)), ENT_QUOTES, 'UTF-8') ?> votes &mdash; <?= $totalPlayers - $votedCount ?> remaining
    </p>

    <!-- Progress dots -->
    <div class="player-queue mb-3" aria-label="Voting progress">
      <?php for ($i = 0; $i < $totalPlayers; $i++): ?>
        <div class="player-dot <?= $i < $votedCount ? 'revealed' : ($i === $currentVoter ? 'current' : '') ?>"></div>
      <?php endfor; ?>
    </div>

    <h2 class="fw-900 text-center mb-1" style="font-size:1.3rem;">
      🗳 Who is the imposter?
    </h2>
    <p class="text-center text-muted-ig mb-4" style="font-size:0.9rem;">
      <?= htmlspecialchars($game['player_names'][$currentVoter] ?? ('Player ' . ($currentVoter + 1)), ENT_QUOTES, 'UTF-8') ?>, vote now. Keep it secret!
    </p>

    <div id="setupAlert" class="ig-alert" role="alert"></div>

    <form id="voteForm" method="POST" action="result.php" novalidate>
      <input type="hidden" name="action"      value="vote">
      <input type="hidden" name="voter_slot"  value="<?= $currentVoter ?>">
      <input type="hidden" name="voted_for"   id="selectedVoteInput" value="">

      <?php for ($i = 0; $i < $totalPlayers; $i++):
        // Can't vote for yourself
        if ($i === $currentVoter) continue;
      ?>
      <label class="vote-option" data-player="<?= $i ?>">
        <input type="radio" name="voted_for_radio" value="<?= $i ?>">
        <div class="check-icon"></div>
        <div>
          <div class="fw-700"><?= htmlspecialchars($game['player_names'][$i] ?? ('Player ' . ($i + 1)), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </label>
      <?php endfor; ?>

      <div class="ig-divider"></div>

      <button type="submit" id="btnCastVote" class="btn-ig-primary" disabled>
        ✅ Cast Vote
      </button>
    </form>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════
         PHASE 2 – Results
         ══════════════════════════════════════════════════════ -->

    <?php
      $maxVotes   = max($tally);
      $mostVoted  = array_search($maxVotes, $tally, true);
      $wasCorrect = ($mostVoted === $imposterIdx);
    ?>

    <div id="resultsSection" class="text-center anim-fade-up">
      <?php if ($allImposters): ?>
        <div style="font-size:3.5rem;">🤯</div>
        <h2 class="fw-900 mb-1" style="font-size:1.8rem; color:var(--ig-accent);">Plot Twist!</h2>
        <p class="text-muted-ig mb-3">Everyone was an imposter all along! Nobody had the word. 😂</p>
      <?php elseif ($wasCorrect): ?>
        <div style="font-size:3.5rem;">🎉</div>
        <h2 class="fw-900 mb-1 text-success" style="font-size:1.8rem;">Town Wins!</h2>
        <p class="text-muted-ig mb-3">The group correctly identified the imposter.</p>
      <?php else: ?>
        <div style="font-size:3.5rem;">😈</div>
        <h2 class="fw-900 mb-1" style="font-size:1.8rem; color:var(--ig-danger);">Imposter Wins!</h2>
        <p class="text-muted-ig mb-3">The imposter fooled everyone!</p>
      <?php endif; ?>
    </div>

    <div class="ig-divider"></div>

    <!-- Reveal word -->
    <div class="text-center mb-3 anim-pop-in">
      <p class="section-label mb-1">The secret word was</p>
      <div class="role-word" style="font-size:2.5rem;">
        <?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <span class="category-chip"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <!-- Imposter reveal -->
    <div class="text-center mb-3">
      <?php if ($allImposters): ?>
        <p class="section-label mb-1">The twist</p>
        <div class="fw-900" style="font-size:1.4rem;">😈 Everyone was the imposter!</div>
      <?php else: ?>
        <p class="section-label mb-1">The imposter was</p>
        <div class="fw-900" style="font-size:1.5rem;">
          <?= htmlspecialchars($game['player_names'][$imposterIdx] ?? ('Player ' . ($imposterIdx + 1)), ENT_QUOTES, 'UTF-8') ?> 😈
        </div>
      <?php endif; ?>
    </div>

    <div class="ig-divider"></div>

    <!-- Vote tally bars -->
    <label class="section-label d-block mb-2">Vote Tally</label>
    <?php
      $maxTally = max($tally) ?: 1;
      for ($i = 0; $i < $totalPlayers; $i++):
        $pct       = round(($tally[$i] / $maxTally) * 100);
        $isTopVoted = ($i === $mostVoted);
    ?>
    <div class="result-bar-wrap">
      <div class="result-bar-label">
        <span class="fw-700">
          <?= htmlspecialchars($game['player_names'][$i] ?? ('Player ' . ($i + 1)), ENT_QUOTES, 'UTF-8') ?>
          <?= (!$allImposters && $i === $imposterIdx) ? ' 😈' : '' ?>
        </span>
        <span><?= $tally[$i] ?> vote<?= $tally[$i] !== 1 ? 's' : '' ?></span>
      </div>
      <div class="result-bar">
        <div class="result-bar-fill <?= !$wasCorrect && $isTopVoted ? 'loser' : '' ?>"
             data-width="<?= $pct ?>"
             style="width:0%"></div>
      </div>
    </div>
    <?php endfor; ?>

    <div class="ig-divider"></div>

    <!-- Play again -->
    <form method="POST" action="result.php">
      <input type="hidden" name="action" value="reset">
      <button type="submit" class="btn-ig-primary">
        🔄 Play Again
      </button>
    </form>

    <?php endif; ?>

  </div><!-- /.content-card -->

</div><!-- /#votingScreen -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
