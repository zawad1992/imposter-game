<?php
/**
 * index.php – Setup Screen
 * Players configure the game: choose player count & category.
 * On submit, game state is created in session and players are
 * redirected to the reveal screen.
 */

session_start();

/* ── Handle form submission ──────────────────────────────────── */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playerCount = filter_input(INPUT_POST, 'player_count', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 3, 'max_range' => 20]
    ]);
    $category = trim($_POST['category'] ?? '');
    $timerPost = filter_input(INPUT_POST, 'timer', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 30, 'max_range' => 600, 'default' => 90]
    ]);

    if ($playerCount === false || $playerCount === null) {
        $error = 'Please select between 3 and 20 players.';
    } else {
        // Load and validate words
        $wordsFile = __DIR__ . '/data/words.json';
        $words = json_decode(file_get_contents($wordsFile), true);

        if (empty($words)) {
            $error = 'Word list could not be loaded. Please check data/words.json.';
        } else {
            // Filter by category if one was selected
            if ($category !== '' && $category !== 'all') {
                $filtered = array_values(array_filter($words, function ($w) use ($category) {
                    return isset($w['category']) && $w['category'] === $category;
                }));
            } else {
                $filtered = $words;
            }

            if (empty($filtered)) {
                $error = 'No words found for that category. Please choose another.';
            } else {
                // Pick a random word
                $selected = $filtered[array_rand($filtered)];

                // All-imposters chaos mode — random 1-in-10 chance, no one knows in advance
                $allImposters  = (random_int(1, 10) === 1);

                // Randomly pick one imposter (or -1 for all-imposters mode)
                $imposterIndex = $allImposters ? -1 : random_int(0, $playerCount - 1);

                // Create ordered player list and optionally shuffle it
                $players = range(1, $playerCount);
                shuffle($players); // randomise reveal order

                // Process player names
                $rawNames    = $_POST['player_names'] ?? [];
                $playerNames = [];
                for ($n = 0; $n < $playerCount; $n++) {
                    $name = mb_substr(trim($rawNames[$n] ?? ''), 0, 30);
                    $playerNames[] = $name !== '' ? $name : 'Player ' . ($n + 1);
                }

                // Store game state in session
                $_SESSION['game'] = [
                    'players'       => $players,
                    'player_count'  => $playerCount,
                    'player_names'  => $playerNames,
                    'all_imposters' => $allImposters,
                    'imposter_index'=> $imposterIndex,
                    'word'          => $selected['word'],
                    'category'      => $selected['category'],
                    'revealed'      => [],   // which player slots have viewed their role
                    'votes'         => [],   // player_slot => voted_for_slot
                    'timer'         => $timerPost ?: 90,
                    'created_at'    => time(),
                ];

                // Redirect to first player reveal
                header('Location: reveal.php');
                exit;
            }
        }
    }
}

/* ── Build category list from words.json for the pills UI ───── */
$wordsFile = __DIR__ . '/data/words.json';
$allWords  = json_decode(file_get_contents($wordsFile), true) ?: [];
$categories = array_values(array_unique(array_column($allWords, 'category')));
sort($categories);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="Imposter Game – A fun party word-guessing game">
  <meta name="theme-color" content="#6366f1">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Imposter Game</title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- App styles -->
  <link rel="stylesheet" href="assets/css/style.css">
  <!-- PWA manifest -->
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="icons/icon.svg">
</head>
<body>

<div class="page-wrapper">

  <!-- Header row -->
  <div class="ig-header anim-fade-up">
    <span class="game-title">imposter</span>
    <div class="d-flex align-items-center gap-2">
      <button id="btnInstallApp" style="display:none;" aria-label="Install app">📲 Install</button>
      <button id="darkToggle" class="dark-toggle" title="Toggle theme" aria-label="Toggle dark/light mode">☀️</button>
    </div>
  </div>

  <!-- Main setup card -->
  <div class="content-card stagger">

    <p class="text-center text-muted-ig mb-4" style="font-size:1rem;">
      Set up your game. Pass the phone around — everyone gets a secret word. One player is the imposter!
    </p>

    <!-- Inline error alert -->
    <?php if ($error): ?>
    <div class="ig-alert alert alert-danger" role="alert" style="display:block;">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div id="setupAlert" class="ig-alert" role="alert"></div>

    <form id="setupForm" method="POST" action="index.php" novalidate>
      <!-- Hidden inputs -->
      <input type="hidden" name="player_count" id="playerCountInput" value="4">
      <input type="hidden" name="category"     id="categoryInput"    value="all">

      <!-- ── Step 1: Settings ── -->
      <div id="step1">

        <!-- Player count stepper -->
        <div class="mb-4">
          <label class="section-label d-block mb-2">Number of Players</label>
          <div class="player-count-wrapper">
            <button type="button" id="btnDecrease" class="count-btn" aria-label="Decrease player count">−</button>
            <div class="count-display" id="playerCountDisplay" aria-live="polite">4</div>
            <button type="button" id="btnIncrease" class="count-btn" aria-label="Increase player count">+</button>
          </div>
          <div class="text-center mt-1">
            <small class="text-muted-ig">Min 3 · Max 20</small>
          </div>
        </div>

        <div class="ig-divider"></div>

        <!-- Category selection -->
        <div class="mb-4">
          <label class="section-label d-block mb-2">Category <span class="text-muted-ig fw-normal">(optional)</span></label>
          <div class="category-pills">
            <button type="button" class="cat-pill active" data-value="all">🎲 All</button>
            <?php foreach ($categories as $cat): ?>
            <button type="button" class="cat-pill" data-value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="ig-divider"></div>

        <!-- Timer option -->
        <div class="mb-4">
          <label class="section-label d-block mb-2" for="timerSelect">Discussion Timer</label>
          <div class="select-wrapper">
            <select class="ig-select" id="timerSelect" name="timer">
              <option value="60">60 seconds</option>
              <option value="90" selected>90 seconds</option>
              <option value="120">120 seconds</option>
              <option value="180">3 minutes</option>
            </select>
          </div>
        </div>

        <div class="ig-divider"></div>

        <!-- Next → -->
        <button type="button" id="btnNextStep" class="btn-ig-primary mt-2">
          Next → Enter Player Names
        </button>

      </div><!-- /#step1 -->

      <!-- ── Step 2: Player Names ── -->
      <div id="step2" style="display:none;">

        <label class="section-label d-block mb-3">Enter Player Names</label>
        <div id="nameFields"></div>

        <div class="ig-divider"></div>

        <div class="d-flex gap-2">
          <button type="button" id="btnBackStep" class="btn-ig-secondary" style="flex:1;">← Back</button>
          <button type="submit" class="btn-ig-primary" style="flex:2;">🎮 Start Game</button>
        </div>

      </div><!-- /#step2 -->

    </form>
  </div>

  <!-- Footer note -->
  <p class="text-muted-ig text-center mt-3" style="font-size:0.78rem;">
    Imposter Game · PWA Edition
  </p>

</div><!-- /.page-wrapper -->

<!-- iOS Add-to-Home-Screen banner (shown only on iOS Safari, not installed) -->
<div id="iosInstallBanner" role="status" aria-live="polite">
  <button class="ios-banner-close" id="iosInstallClose" aria-label="Dismiss">&times;</button>
  <div class="ios-banner-title">📲 Add to Home Screen</div>
  <div class="ios-banner-text">
    Tap <strong>Share</strong> <span style="font-size:1.1em;">⎙</span> then <strong>"Add to Home Screen"</strong> to install this game as an app.
  </div>
</div>

<!-- jQuery + Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
