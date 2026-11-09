# Security Policy

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report them privately through GitHub's **Report a vulnerability** button (Security tab → Advisories) on
[Robatech348/Huduma-smart-assit](https://github.com/Robatech348/Huduma-smart-assit/security/advisories/new),
or contact the maintainer, [@Robatech348](https://github.com/Robatech348).

Please include:
- the affected page or file,
- steps to reproduce, and
- the impact you observed.

You should receive a response within 7 days.

## Deployment checklist

- Set `app.debug` to `false` in `config.php`.
- Delete or block the `install/` folder after installation.
- Serve the site over HTTPS.
- Keep `config.php` out of version control, and supply `ANTHROPIC_API_KEY` as an environment variable.
- Make sure Apache honours the bundled `.htaccess` files (`AllowOverride All`).
