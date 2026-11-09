<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    if (current_user()) {
        audit('logout');
    }
    logout_user();
    flash('info', 'You have signed out.');
}
redirect('staff/login.php');
