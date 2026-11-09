<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    logout_citizen();
    flash('info', 'You have signed out.');
}
redirect('index.php');
