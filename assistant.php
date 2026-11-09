<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/assistant.php';

render_header('Ask SmartAssist');
?>
<div class="spread">
  <div>
    <h1>Ask SmartAssist</h1>
    <p class="muted">Plain-language answers about services, documents, fees and booking. English or Kiswahili.</p>
  </div>
  <button class="btn btn-sm" id="chat-reset" type="button">New conversation</button>
</div>

<div class="suggestions" aria-label="Example questions">
  <button type="button" data-ask="What do I need to replace a lost ID card?">Replace a lost ID</button>
  <button type="button" data-ask="How do I get a certificate of good conduct?">Good conduct certificate</button>
  <button type="button" data-ask="Nawezaje kupata KRA PIN?">Nawezaje kupata KRA PIN?</button>
  <button type="button" data-ask="What are the opening hours?">Opening hours</button>
  <button type="button" data-ask="How do I cancel my appointment?">Cancel an appointment</button>
</div>

<div id="chat" class="chat"
     data-endpoint="<?= e(url('api/assistant.php')) ?>"
     data-feedback="<?= e(url('api/assistant_feedback.php')) ?>"
     data-initial="<?= e(mb_substr(input('q'), 0, 500)) ?>">
  <div id="chat-log" class="chat-log" aria-live="polite"></div>
  <form id="chat-form" class="chat-form">
    <label class="skip" for="chat-input">Your question</label>
    <input id="chat-input" type="text" maxlength="500" autocomplete="off" placeholder="Type your question…" required>
    <button class="btn btn-primary" type="submit">Send</button>
  </form>
</div>
<p class="muted small" style="margin-top:12px">
  <?= Assistant::aiAvailable() ? 'Answers are AI-assisted and limited to approved service information.' : 'Answers come from the approved service catalogue.' ?>
  Do not share ID numbers, PINs or other personal details here. Decisions on applications are made by the responsible agency.
</p>
<?php render_footer();
