/**
 * Imposter Game – Main JavaScript
 * Handles: PWA registration, dark mode, setup logic,
 *          reveal flow, game timer, voting & result animations.
 */

/* ============================================================
   1. PWA – Service Worker Registration
   ============================================================ */
(function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register('service-worker.js')
        .then(function (reg) {
          console.log('[SW] Registered. Scope:', reg.scope);
        })
        .catch(function (err) {
          console.warn('[SW] Registration failed:', err);
        });
    });
  }
})();


/* ============================================================
   2. Dark Mode Toggle
   ============================================================ */
(function initDarkMode() {
  var STORAGE_KEY = 'ig-theme';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    var btn = $('#darkToggle');
    if (btn.length) {
      btn.text(theme === 'light' ? '🌙' : '☀️');
      btn.attr('title', theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
    }
    localStorage.setItem(STORAGE_KEY, theme);
  }

  // Load saved preference (default: dark)
  $(document).ready(function () {
    var saved = localStorage.getItem(STORAGE_KEY) || 'dark';
    applyTheme(saved);

    $('#darkToggle').on('click', function () {
      var current = document.documentElement.getAttribute('data-theme') || 'dark';
      applyTheme(current === 'dark' ? 'light' : 'dark');
    });
  });
})();


/* ============================================================
   3. Setup Screen (index.php)
   ============================================================ */
$(document).ready(function () {
  if (!$('#setupForm').length) return;

  /* ---- Player count stepper ---- */
  var MIN_PLAYERS = 3;
  var MAX_PLAYERS = 20;
  var playerCount  = parseInt($('#playerCountInput').val(), 10) || 4;

  function updateCounter() {
    $('#playerCountDisplay').text(playerCount);
    $('#playerCountInput').val(playerCount);
    $('#btnDecrease').prop('disabled', playerCount <= MIN_PLAYERS);
    $('#btnIncrease').prop('disabled', playerCount >= MAX_PLAYERS);
  }

  updateCounter();

  $('#btnIncrease').on('click', function () {
    if (playerCount < MAX_PLAYERS) { playerCount++; updateCounter(); }
  });

  $('#btnDecrease').on('click', function () {
    if (playerCount > MIN_PLAYERS) { playerCount--; updateCounter(); }
  });

  /* ---- Category pill selection ---- */
  $(document).on('click', '.cat-pill', function () {
    $('.cat-pill').removeClass('active');
    $(this).addClass('active');
    $('#categoryInput').val($(this).data('value'));
  });

  /* ---- Step 1 → Step 2: show name fields ---- */
  function buildNameFields(count) {
    var html = '';
    for (var i = 0; i < count; i++) {
      html += '<div class="mb-2">' +
        '<input type="text" name="player_names[]" class="ig-input"' +
        ' placeholder="Player ' + (i + 1) + ' name"' +
        ' maxlength="30" autocomplete="off">' +
        '</div>';
    }
    $('#nameFields').html(html);
  }

  $('#btnNextStep').on('click', function () {
    buildNameFields(playerCount);
    $('#step1').hide();
    $('#step2').show();
    $('#nameFields input').first().focus();
  });

  $('#btnBackStep').on('click', function () {
    $('#step2').hide();
    $('#step1').show();
  });

  /* ---- Form submit validation ---- */
  $('#setupForm').on('submit', function (e) {
    var count = parseInt($('#playerCountInput').val(), 10);
    if (isNaN(count) || count < MIN_PLAYERS || count > MAX_PLAYERS) {
      e.preventDefault();
      showAlert('#setupAlert', 'Please select between ' + MIN_PLAYERS + ' and ' + MAX_PLAYERS + ' players.', 'danger');
    }
  });
});


/* ============================================================
   4. Reveal Screen (reveal.php)
   ============================================================ */
$(document).ready(function () {
  if (!$('#revealScreen').length) return;

  /* ---- Reveal button ---- */
  $('#btnReveal').on('click', function () {
    var $btn     = $(this);
    var $content = $('#roleContent');

    // Disable button to prevent double-tap
    $btn.prop('disabled', true);

    $btn.fadeOut(200, function () {
      $content.removeClass('blurred-content').addClass('anim-pop-in revealed');

      // Play sound effect
      playRevealSound($content.data('role') === 'imposter');

      // Trigger shake animation for imposter
      if ($content.data('role') === 'imposter') {
        setTimeout(function () {
          $content.closest('.role-card').addClass('anim-shake anim-pulse-glow');
        }, 300);
      }

      // Show "Next Player" button
      $('#btnNext').fadeIn(300);
    });
  });
});


/* ============================================================
   5. Game Screen (game.php)
   ============================================================ */
$(document).ready(function () {
  if (!$('#gameScreen').length) return;

  var totalSeconds = parseInt($('#gameScreen').data('timer') || 90, 10);
  var remaining    = totalSeconds;
  var timerInterval = null;
  var CIRCUMFERENCE = 314; // 2 * PI * 50 (radius)

  function updateTimerDisplay(secs) {
    var mins = Math.floor(secs / 60);
    var s    = secs % 60;
    $('#timerValue').text(
      (mins > 0 ? mins + ':' : '') + (s < 10 && mins > 0 ? '0' : '') + s
    );

    // Update SVG ring
    var progress  = secs / totalSeconds;
    var dashOffset = CIRCUMFERENCE * (1 - progress);
    $('#timerRingFg').css('stroke-dashoffset', dashOffset);

    // Urgent color when < 20 seconds
    if (secs <= 20) {
      $('#timerRingFg').addClass('urgent');
      $('#timerValue').css('color', 'var(--ig-danger)');
      if (secs <= 10 && secs > 0) { playTickSound(); }
    }
  }

  function startTimer() {
    if (timerInterval) return;
    timerInterval = setInterval(function () {
      remaining--;
      updateTimerDisplay(remaining);
      if (remaining <= 0) {
        clearInterval(timerInterval);
        timerInterval = null;
        playEndSound();
        $('#timerValue').text('⏰');
        $('#timerEndMsg').fadeIn(400);
        $('#btnStartTimer').prop('disabled', true);
      }
    }, 1000);
    $('#btnStartTimer').text('⏸ Pause').data('running', true);
  }

  function pauseTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    $('#btnStartTimer').text('▶ Resume').data('running', false);
  }

  // Initialise ring display
  updateTimerDisplay(totalSeconds);

  $('#btnStartTimer').on('click', function () {
    if ($(this).data('running')) {
      pauseTimer();
    } else {
      startTimer();
    }
  });

  $('#btnResetTimer').on('click', function () {
    clearInterval(timerInterval);
    timerInterval = null;
    remaining = totalSeconds;
    updateTimerDisplay(totalSeconds);
    $('#timerRingFg').removeClass('urgent');
    $('#timerValue').css('color', '');
    $('#timerEndMsg').hide();
    $('#btnStartTimer').text('▶ Start').data('running', false).prop('disabled', false);
  });
});


/* ============================================================
   6. Voting Screen (result.php)
   ============================================================ */
$(document).ready(function () {
  if (!$('#votingScreen').length) return;

  /* ---- Vote option selection ---- */
  $(document).on('click', '.vote-option', function () {
    var $this = $(this);
    var playerId = $this.data('player');

    $('.vote-option').removeClass('selected');
    $this.addClass('selected');
    $this.find('input[type="radio"]').prop('checked', true);
    $('#selectedVoteInput').val(playerId);
    $('#btnCastVote').prop('disabled', false);
  });

  /* ---- Animate result bars ---- */
  if ($('#resultsSection').length) {
    setTimeout(function () {
      $('.result-bar-fill').each(function () {
        var target = $(this).data('width');
        $(this).css('width', target + '%');
      });
    }, 300);
  }
});


/* ============================================================
   7. Utility Functions
   ============================================================ */

/**
 * Show a Bootstrap-style alert inside the given selector.
 * @param {string} selector – jQuery selector for the alert element
 * @param {string} message  – Alert text
 * @param {string} type     – 'danger' | 'success' | 'warning'
 */
function showAlert(selector, message, type) {
  var typeClass = {
    danger:  'alert-danger',
    success: 'alert-success',
    warning: 'alert-warning'
  };
  var $el = $(selector);
  $el.removeClass('alert-danger alert-success alert-warning')
     .addClass('ig-alert alert ' + (typeClass[type] || 'alert-danger'))
     .html(message)
     .fadeIn(250);

  // Auto-dismiss after 4 seconds
  setTimeout(function () {
    $el.fadeOut(300);
  }, 4000);
}


/* ============================================================
   8. Sound Effects (Web Audio API – no external files needed)
   ============================================================ */

/**
 * Play a reveal sound.
 * @param {boolean} isImposter – plays a spooky sound for the imposter
 */
function playRevealSound(isImposter) {
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);

    if (isImposter) {
      // Descending menacing tone
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(440, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(110, ctx.currentTime + 0.6);
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.7);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.7);
    } else {
      // Cheerful ascending chime
      osc.type = 'sine';
      osc.frequency.setValueAtTime(330, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.3);
      gain.gain.setValueAtTime(0.25, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.4);
    }
  } catch (e) {
    // Audio not supported – silently ignore
  }
}

/** Play a tick sound for the final countdown. */
function playTickSound() {
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'square';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.1);
  } catch (e) {}
}

/** Play an end-of-timer alarm. */
function playEndSound() {
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    [0, 0.15, 0.30].forEach(function (delay) {
      var osc  = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime + delay);
      gain.gain.setValueAtTime(0.3, ctx.currentTime + delay);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.12);
      osc.start(ctx.currentTime + delay);
      osc.stop(ctx.currentTime  + delay + 0.12);
    });
  } catch (e) {}
}
