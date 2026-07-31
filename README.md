# Bright House College — School Admission Management System

A school admission portal built in plain PHP (no framework) with MySQL and automated email notifications via PHPMailer. Prospective students apply online, admins review and decide, and accepted students get their own dashboard.

## Features

**Public**
- Online application form with document upload (optional)
- Auto-generated reference number for tracking an application
- Status check page (reference number + email)
- Automated "Application Received" email

**Admin**
- Dashboard stats: total, accepted, declined, pending applicants, total teachers
- Manage Teachers, Subjects, and Announcements (full CRUD)
- Review applicants, view details and uploaded documents
- Accept/Decline applicants — generates a student account and sends a decision email

**Student**
- Forced password change on first login
- View profile, announcements, and subjects

## Tech stack

- PHP 8.2, plain (no framework)
- MySQL via PDO (prepared statements)
- PHPMailer over SMTP
- Composer (`vlucas/phpdotenv`, `phpmailer/phpmailer`)
- Plain HTML/CSS

## Security

- Passwords hashed with `password_hash()`
- CSRF tokens on every form
- Session ID regenerated on login
- Role checked from the database on every protected page
- File uploads validated by real file content (not just extension), stored outside the web root
- Credentials stored in `.env`, excluded from version control

## Key design decisions

- **Applicants vs. Users**: an applicant only gets a real login account after being accepted — mirrors the real admission process.
- **Student login identity**: generated as a school-issued identifier, separate from the guardian's email, so siblings can share a guardian email without a login conflict. Notifications still go to the real email.
- **Accept/Decline safety**: wrapped in a database transaction with row-locking (`SELECT ... FOR UPDATE`) to prevent double-processing if an action is triggered twice.
- **Email failures never block a save**: an application or decision is saved to the database first; the email is sent after and logged separately, so a delivery issue never loses data.

## Known limitations

- No "forgot password" flow yet (password change while logged in is supported)
- Session-based rate limiting on the status-check page is a soft deterrent, not strong security
- Sender email isn't on an authenticated custom domain, so mail may occasionally land in spam

## Local setup

1. `composer install`
2. Copy `config/.env.example` to `config/.env` and fill in your database + SMTP credentials
3. Import the schema `.sql` file into MySQL
4. Point your web server's document root at `/public`
5. Visit `/public/login.php` (admin) or `/public/apply.php` (public form)

## Project structure

```
/config     — database connection, environment loading
/includes   — shared auth, bootstrap files, mailer
/templates  — email templates
/public     — web root
  /admin    — admin pages
  /student  — student pages
  apply.php, login.php, check-status.php — public pages
/uploads    — applicant documents (outside web root)
```
