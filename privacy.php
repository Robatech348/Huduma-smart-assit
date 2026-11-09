<?php
require __DIR__ . '/includes/bootstrap.php';
render_header('Privacy');
?>
<div class="narrow">
  <h1>Privacy notice</h1>
  <div class="card">
    <h2>What we collect</h2>
    <ul>
      <li><strong>Accounts:</strong> to book online you create an account with your name, email address, an optional phone number and a password. Passwords are stored only as a one-way hash, so staff cannot read them. Browsing services and asking SmartAssist need no account.</li>
      <li><strong>Appointments:</strong> your name, email address, optional phone number, the service, centre and time you choose, and whether you asked for extra assistance.</li>
      <li><strong>Service requests:</strong> the reference, status updates and the contact details you gave at the centre.</li>
      <li><strong>Feedback:</strong> your rating and optional comment.</li>
      <li><strong>SmartAssist questions:</strong> the text of questions (with email addresses and long numbers removed) to improve service information. Questions are not linked to your name.</li>
    </ul>
    <h2>What we do not collect</h2>
    <p>We never ask for ID numbers, KRA PINs, passwords or documents through this website or by email. Staff verify documents in person.</p>
    <h2>How we use it</h2>
    <p>Only to manage your appointment, send confirmations, reminders and status updates, and to measure and improve service quality. Authorised centre staff can see appointments for their own centre.</p>
    <h2>AI-assisted answers</h2>
    <p>When enabled, SmartAssist questions are sent to an AI provider to draft an answer from approved service information. Do not include personal details in questions. Decisions on applications are always made by the responsible agency.</p>
    <h2>Your choices</h2>
    <p>You can cancel an appointment at any time from your confirmation email. To ask for your data to be corrected or deleted, contact <?= e(config('app.support_email')) ?>.</p>
    <p class="muted small">This notice describes a pilot prototype. A pilot must complete legal, data-protection and security review, and define retention periods, before going live.</p>
  </div>
</div>
<?php render_footer();
