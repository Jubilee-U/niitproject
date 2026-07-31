<?php

declare(strict_types=1);

/*
 * PUBLIC application form — no login, no admin_bootstrap. Anyone on the
 * internet can reach this page, so every piece of input (text AND files) is
 * treated as untrusted: validated server-side, bound into prepared statements,
 * and escaped on output.
 */

require_once __DIR__ . '/../config/database.php';                 // provides $pdo
require_once __DIR__ . '/../includes/auth.php';                   // startSecureSession() + CSRF helpers
require_once __DIR__ . '/../includes/mailer.php';                 // sendEmail()
require_once __DIR__ . '/../templates/application_received.php';  // renderApplicationReceivedEmail()

// Must run before any output (it may set the session cookie).
startSecureSession();

/*
 * Allowed values for the constrained dropdowns. Defined once and used for BOTH
 * validation and rendering, so the two can never drift apart.
 */
$classLevels = [
    'Kindergarten', 'Pry1', 'Pry2', 'Pry3', 'Pry4', 'Pry5',
    'JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3',
];
$relationships = ['Father', 'Mother', 'Guardian', 'Other'];

/*
 * Optional document uploads: a fixed set of labeled file inputs. The KEY is the
 * HTML input name; the VALUE is the doc_type stored in applicant_documents.
 * Because the type comes from this server-side map (not a field the user can
 * edit), an applicant can't mislabel or inject an arbitrary doc_type.
 */
$documentFields = [
    'doc_birth_certificate' => 'Birth Certificate',
    'doc_passport_photo'    => 'Passport Photo',
    'doc_previous_result'   => 'Previous School Result',
];

// Upload constraints.
$allowedMime = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
];
$maxUploadBytes = 5 * 1024 * 1024; // 5 MB per file

/*
 * Files are stored in /uploads, a sibling of /public — i.e. OUTSIDE the web
 * root. This matters for security:
 *
 *   - Anything under /public is directly fetchable by URL. If uploads lived
 *     there, a stranger could guess or enumerate paths and pull down other
 *     applicants' birth certificates and photos — a serious data leak — and,
 *     under an unlucky server config, a malicious uploaded file could even be
 *     executed. Keeping files outside the web root means the ONLY way to read
 *     one is through a PHP script we write, which can require admin auth first.
 */
$uploadsDir = __DIR__ . '/../uploads';

/*
 * Post-Redirect-Get: after a successful insert we redirect to ?submitted=1 and
 * stash the reference number in the session, so refreshing can't re-submit.
 */
$confirmationRef = null;
if (isset($_GET['submitted']) && !empty($_SESSION['application_ref'])) {
    $confirmationRef = (string) $_SESSION['application_ref'];
    unset($_SESSION['application_ref']);
}

// ── Small helpers ───────────────────────────────────────────────────────────
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Phone rule shared by applicant and guardian phones: only digits, spaces, and
 * + - ( ), and at least 7 actual digits. Returns an error string, or null if OK.
 */
function phoneError(string $value): ?string
{
    if (!preg_match('/^[0-9\s()+-]+$/', $value)) {
        return 'Phone may contain only digits, spaces, and + - ( ) — no letters.';
    }
    if (preg_match_all('/\d/', $value) < 7) {
        return 'Phone must include at least 7 digits.';
    }
    return null;
}

/**
 * Generate a reference number in the format APP-{year}-{6 hex chars}, retrying
 * until it's unique in the applicants table.
 */
function generateUniqueReferenceNo(PDO $pdo): string
{
    $year  = date('Y');
    $check = $pdo->prepare('SELECT 1 FROM applicants WHERE reference_no = :ref LIMIT 1');

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $ref = sprintf('APP-%s-%s', $year, strtoupper(bin2hex(random_bytes(3))));
        $check->execute([':ref' => $ref]);
        if ($check->fetchColumn() === false) {
            return $ref;
        }
    }
    throw new RuntimeException('Could not generate a unique reference number after several attempts.');
}

/**
 * Validate one OPTIONAL uploaded file entry from $_FILES.
 *
 * Returns [$error, $info]:
 *   - no file chosen        -> [null, null]   (fine; upload is optional)
 *   - failed validation     -> [$message, null]
 *   - passed validation     -> [null, ['tmp_name','stored_name','original_name','ext']]
 *
 * The stored name is unpredictable (random bytes) and its extension is derived
 * from the DETECTED MIME type — never from the client-supplied filename. Reasons
 * this renaming matters for security:
 *   - The original name is attacker-controlled. It can carry path-traversal
 *     sequences ("../../etc/..."), null bytes, or names that overwrite an
 *     existing file. Never use it as a filesystem path.
 *   - A random name can't traverse directories and (being random) won't collide
 *     with or overwrite another applicant's file.
 *   - Taking the extension from the real MIME, not the name, defeats tricks like
 *     "photo.php" or "result.pdf.php" that try to smuggle an executable
 *     extension onto the server.
 */
function validateUpload(array $file, array $allowedMime, int $maxBytes, finfo $finfo): array
{
    // Nothing selected for this input — optional, so just skip it.
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    // Any other PHP upload error (exceeded ini size, partial upload, no tmp dir).
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['Upload failed. Please try again.', null];
    }

    // Must be a genuine HTTP upload, not a server path we were tricked into reading.
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['Invalid upload.', null];
    }

    // Size is checked server-side; any HTML/JS limit is only a hint.
    if ((int) $file['size'] > $maxBytes) {
        return ['File is too large (max 5 MB).', null];
    }
    if ((int) $file['size'] <= 0) {
        return ['File appears to be empty.', null];
    }

    // Content-based type check: read the REAL MIME from the file's bytes. The
    // client-sent name and type are both forgeable, so a renamed .exe with a
    // .pdf extension is caught here because its actual bytes aren't a PDF.
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || !isset($allowedMime[$mime])) {
        return ['Only PDF, JPG, or PNG files are allowed.', null];
    }
    $ext = $allowedMime[$mime];

    return [null, [
        'tmp_name'      => $file['tmp_name'],
        'stored_name'   => bin2hex(random_bytes(16)) . '.' . $ext,
        // Metadata only — shown back to admins, NEVER used as a path. Capped
        // so an absurdly long name can't overflow the column.
        'original_name' => mb_substr((string) $file['name'], 0, 255),
        'ext'           => $ext,
    ]];
}

// ── Form state ──────────────────────────────────────────────────────────────
$errors = [];
$values = [
    'full_name'                => '',
    'email'                    => '',
    'phone'                    => '',
    'dob'                      => '',
    'gender'                   => '',
    'home_address'             => '',
    'previous_school'          => '',
    'state_of_origin'          => '',
    'guardian_name'            => '',
    'relationship_to_guardian' => '',
    'guardian_phone'           => '',
    'guardian_email'           => '',
    'program_applied'          => '',
    'medical_notes'            => '',
    'special_needs'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmationRef === null) {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please reload the page and try again.';
    }

    // Collect + trim all text fields up front.
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    // full_name (required)
    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    }

    // email (OPTIONAL — many applicants are minors without their own email).
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address, or leave it blank.';
    }

    // phone (OPTIONAL — guardian contact is the required point of contact).
    if ($values['phone'] !== '' && ($pe = phoneError($values['phone'])) !== null) {
        $errors['phone'] = $pe;
    }

    // dob (required, valid, past, within 100 years)
    if ($values['dob'] === '') {
        $errors['dob'] = 'Date of birth is required.';
    } else {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $values['dob']);
        if (!$parsed || $parsed->format('Y-m-d') !== $values['dob']) {
            $errors['dob'] = 'Please enter a valid date.';
        } else {
            $today = new DateTimeImmutable('today');
            if ($parsed > $today) {
                $errors['dob'] = 'Date of birth cannot be in the future.';
            } elseif ($parsed < $today->modify('-100 years')) {
                $errors['dob'] = 'Please enter a date of birth within the last 100 years.';
            }
        }
    }

    // gender (optional; if present must be a known enum value)
    if ($values['gender'] !== '' && !in_array($values['gender'], ['male', 'female'], true)) {
        $errors['gender'] = 'Please choose a valid option.';
    }

    // home_address (required)
    if ($values['home_address'] === '') {
        $errors['home_address'] = 'Home address is required.';
    }

    // guardian_name (required)
    if ($values['guardian_name'] === '') {
        $errors['guardian_name'] = 'Guardian name is required.';
    }

    // relationship_to_guardian (required + whitelist)
    if ($values['relationship_to_guardian'] === '') {
        $errors['relationship_to_guardian'] = 'Please select the relationship to the applicant.';
    } elseif (!in_array($values['relationship_to_guardian'], $relationships, true)) {
        $errors['relationship_to_guardian'] = 'Please select a valid relationship.';
    }

    // guardian_phone (required + pattern)
    if ($values['guardian_phone'] === '') {
        $errors['guardian_phone'] = 'Guardian phone is required.';
    } elseif (($gpe = phoneError($values['guardian_phone'])) !== null) {
        $errors['guardian_phone'] = $gpe;
    }

    // guardian_email (required + format)
    if ($values['guardian_email'] === '') {
        $errors['guardian_email'] = 'Guardian email is required.';
    } elseif (!filter_var($values['guardian_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['guardian_email'] = 'Please enter a valid email address.';
    }

    // program_applied / class level (required + whitelist)
    if ($values['program_applied'] === '') {
        $errors['program_applied'] = 'Please select a class level.';
    } elseif (!in_array($values['program_applied'], $classLevels, true)) {
        $errors['program_applied'] = 'Please select a valid class level.';
    }

    // ── File validation (after text fields) ─────────────────────────────────
    // Each validated file is collected in $preparedFiles but NOTHING is written
    // to disk yet — we only move files once EVERYTHING (text + files) is valid,
    // inside the transaction below.
    $preparedFiles = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($documentFields as $inputName => $docTypeLabel) {
        $file = $_FILES[$inputName] ?? ['error' => UPLOAD_ERR_NO_FILE];
        [$fileError, $info] = validateUpload($file, $allowedMime, $maxUploadBytes, $finfo);

        if ($fileError !== null) {
            $errors[$inputName] = $fileError;
        } elseif ($info !== null) {
            $info['doc_type'] = $docTypeLabel;
            $preparedFiles[]  = $info;
        }
    }

    // ── Persist, if everything checks out ───────────────────────────────────
    // Set only after a successful commit, so the post-commit email step can run
    // outside the transaction's try/catch and never affect the saved record.
    $committedRef = null;
    $committedApplicantId = null;

    if ($errors === []) {
        $movedFiles = []; // absolute paths already moved, for cleanup on failure

        try {
            // Make sure the target directory is usable before we start.
            if (!is_dir($uploadsDir) || !is_writable($uploadsDir)) {
                throw new RuntimeException('Uploads directory is not writable: ' . $uploadsDir);
            }

            // The whole submission is one transaction: applicant + reference +
            // every document row commit together, or nothing does.
            $pdo->beginTransaction();

            // 1. Insert the applicant (status 'pending'; reference filled at step 3).
            $insert = $pdo->prepare(
                'INSERT INTO applicants
                    (full_name, email, phone, dob, gender, home_address, previous_school,
                     state_of_origin, guardian_name, relationship_to_guardian, guardian_phone,
                     guardian_email, program_applied, medical_notes, special_needs, status)
                 VALUES
                    (:full_name, :email, :phone, :dob, :gender, :home_address, :previous_school,
                     :state_of_origin, :guardian_name, :relationship_to_guardian, :guardian_phone,
                     :guardian_email, :program_applied, :medical_notes, :special_needs, :status)'
            );
            $insert->execute([
                ':full_name'                => $values['full_name'],
                ':email'                    => $values['email'] !== '' ? $values['email'] : null,
                ':phone'                    => $values['phone'] !== '' ? $values['phone'] : null,
                ':dob'                      => $values['dob'],
                ':gender'                   => $values['gender'] !== '' ? $values['gender'] : null,
                ':home_address'             => $values['home_address'],
                ':previous_school'          => $values['previous_school'] !== '' ? $values['previous_school'] : null,
                ':state_of_origin'          => $values['state_of_origin'] !== '' ? $values['state_of_origin'] : null,
                ':guardian_name'            => $values['guardian_name'],
                ':relationship_to_guardian' => $values['relationship_to_guardian'],
                ':guardian_phone'           => $values['guardian_phone'],
                ':guardian_email'           => $values['guardian_email'] !== '' ? $values['guardian_email'] : null,
                ':program_applied'          => $values['program_applied'],
                ':medical_notes'            => $values['medical_notes'] !== '' ? $values['medical_notes'] : null,
                ':special_needs'            => $values['special_needs'] !== '' ? $values['special_needs'] : null,
                ':status'                   => 'pending',
            ]);

            // 2. New applicant id.
            $applicantId = (int) $pdo->lastInsertId();

            // 3. Generate + store the unique reference number.
            $referenceNo = generateUniqueReferenceNo($pdo);
            $pdo->prepare('UPDATE applicants SET reference_no = :ref WHERE id = :id')
                ->execute([':ref' => $referenceNo, ':id' => $applicantId]);

            // 4. Move each validated file and record one applicant_documents row.
            $docStmt = $pdo->prepare(
                'INSERT INTO applicant_documents
                    (applicant_id, doc_type, file_path, original_name, uploaded_at)
                 VALUES
                    (:applicant_id, :doc_type, :file_path, :original_name, NOW())'
            );

            foreach ($preparedFiles as $pf) {
                $targetAbs = $uploadsDir . '/' . $pf['stored_name'];

                // move_uploaded_file also re-checks this really was an HTTP upload.
                if (!move_uploaded_file($pf['tmp_name'], $targetAbs)) {
                    throw new RuntimeException('Failed to move an uploaded file into place.');
                }
                $movedFiles[] = $targetAbs;

                $docStmt->execute([
                    ':applicant_id'  => $applicantId,
                    ':doc_type'      => $pf['doc_type'],
                    // Stored relative to the project root. A later download
                    // script must basename() this and re-join it to $uploadsDir.
                    ':file_path'     => 'uploads/' . $pf['stored_name'],
                    ':original_name' => $pf['original_name'],
                ]);
            }

            $pdo->commit();

            // Capture what the post-commit steps need. The confirmation email
            // and the redirect run AFTER this try/catch, so nothing there can
            // reach the rollback/cleanup path for an already-saved record.
            $committedRef = $referenceNo;
            $committedApplicantId = $applicantId;
        } catch (Throwable $ex) {
            // Undo everything: roll back the DB, then delete any files we already
            // moved this request, so we never leave orphans on disk or in the DB.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($movedFiles as $mf) {
                @unlink($mf);
            }
            error_log('apply.php submission failed: ' . $ex->getMessage());
            $errors['general'] = 'Something went wrong while submitting your application. Please try again.';
        }
    }

    /*
     * Post-commit confirmation email — best-effort ONLY.
     *
     * Why an email failure must never roll back or block the save: at this
     * point the applicant row, its reference number, and its documents are
     * already committed and durable. Email delivery depends on external systems
     * (the SMTP server, the network, the recipient's mailbox) that can fail or
     * stall for reasons that have nothing to do with the application itself. If
     * a delivery hiccup could undo the transaction or error out the request,
     * we'd throw away a valid, completed application — and show the applicant a
     * failure — over something unrelated to whether we received it. So we send
     * fire-and-forget: sendEmail() catches its own errors and records the
     * outcome in email_log, we ignore its return value, and the confirmation
     * page is shown either way.
     *
     * This runs OUTSIDE the try/catch above precisely so nothing here can reach
     * the rollback/cleanup branch for a record that's already saved.
     */
    if ($committedRef !== null) {
        // The applicant's own email is optional, so fall back to the guardian's.
        $recipientEmail = $values['email'] !== '' ? $values['email'] : $values['guardian_email'];

        if ($recipientEmail !== '') {
            $htmlBody = renderApplicationReceivedEmail($values['full_name'], $committedRef);
            sendEmail(
                $pdo,
                $recipientEmail,
                $values['full_name'],
                'Application Received — ' . $committedRef,
                $htmlBody,
                $committedApplicantId,
                'application_received'
            );
        }

        $_SESSION['application_ref'] = $committedRef;
        header('Location: apply.php?submitted=1');
        exit;
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply — School Admissions</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
</head>
<body class="auth">
<?php include __DIR__ . '/../includes/public_header.php'; ?>
    <main>
        <?php if ($confirmationRef !== null): ?>

            <div class="confirm">
                <h1>Application received!</h1>
                <p>Your reference number is:</p>
                <p class="ref"><?= e($confirmationRef) ?></p>
                <p>Please save this for checking your status later.</p>
            </div>
            <p style="text-align:center; margin-top:1.5rem;">
                <a href="apply.php">Submit another application</a>
            </p>

        <?php else: ?>

            <h1>Admission Application</h1>

            <?php if (isset($errors['csrf'])): ?>
                <div class="errors"><?= e($errors['csrf']) ?></div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="errors"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <!-- enctype is REQUIRED for file uploads; without it $_FILES stays empty. -->
            <form method="post" action="apply.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <h2>Applicant details</h2>

                <label>Full name <span class="req">*</span>
                    <input type="text" name="full_name" value="<?= e($values['full_name']) ?>"
                           class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>

                <label>Email <span class="hint">(optional)</span>
                    <input type="email" name="email" value="<?= e($values['email']) ?>"
                           class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>

                <label>Phone <span class="hint">(optional)</span>
                    <input type="tel" name="phone" value="<?= e($values['phone']) ?>"
                           class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['phone'])): ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>

                <label>Date of birth <span class="req">*</span>
                    <input type="date" name="dob" value="<?= e($values['dob']) ?>"
                           class="<?= isset($errors['dob']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['dob'])): ?><p class="field-error"><?= e($errors['dob']) ?></p><?php endif; ?>

                <label style="margin-bottom:.35rem;">Gender <span class="hint">(optional)</span></label>
                <div class="radios">
                    <label><input type="radio" name="gender" value="male"
                        <?= $values['gender'] === 'male' ? 'checked' : '' ?>> Male</label>
                    <label><input type="radio" name="gender" value="female"
                        <?= $values['gender'] === 'female' ? 'checked' : '' ?>> Female</label>
                    <label><input type="radio" name="gender" value=""
                        <?= $values['gender'] === '' ? 'checked' : '' ?>> Prefer not to say</label>
                </div>
                <?php if (isset($errors['gender'])): ?><p class="field-error"><?= e($errors['gender']) ?></p><?php endif; ?>

                <label>Home address <span class="req">*</span>
                    <input type="text" name="home_address" value="<?= e($values['home_address']) ?>"
                           class="<?= isset($errors['home_address']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['home_address'])): ?><p class="field-error"><?= e($errors['home_address']) ?></p><?php endif; ?>

                <label>Previous school <span class="hint">(optional)</span>
                    <input type="text" name="previous_school" value="<?= e($values['previous_school']) ?>">
                </label>

                <label>State of origin <span class="hint">(optional)</span>
                    <input type="text" name="state_of_origin" value="<?= e($values['state_of_origin']) ?>">
                </label>

                <h2>Guardian details</h2>

                <label>Guardian name <span class="req">*</span>
                    <input type="text" name="guardian_name" value="<?= e($values['guardian_name']) ?>"
                           class="<?= isset($errors['guardian_name']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['guardian_name'])): ?><p class="field-error"><?= e($errors['guardian_name']) ?></p><?php endif; ?>

                <label>Relationship to applicant <span class="req">*</span>
                    <select name="relationship_to_guardian"
                            class="<?= isset($errors['relationship_to_guardian']) ? 'invalid' : '' ?>">
                        <option value="">— Select —</option>
                        <?php foreach ($relationships as $rel): ?>
                            <option value="<?= e($rel) ?>" <?= $values['relationship_to_guardian'] === $rel ? 'selected' : '' ?>><?= e($rel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (isset($errors['relationship_to_guardian'])): ?><p class="field-error"><?= e($errors['relationship_to_guardian']) ?></p><?php endif; ?>

                <label>Guardian phone <span class="req">*</span>
                    <input type="tel" name="guardian_phone" value="<?= e($values['guardian_phone']) ?>"
                           class="<?= isset($errors['guardian_phone']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['guardian_phone'])): ?><p class="field-error"><?= e($errors['guardian_phone']) ?></p><?php endif; ?>

                <label>Guardian email <span class="req">*</span>
                    <input type="email" name="guardian_email" value="<?= e($values['guardian_email']) ?>"
                           class="<?= isset($errors['guardian_email']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['guardian_email'])): ?><p class="field-error"><?= e($errors['guardian_email']) ?></p><?php endif; ?>

                <h2>Class level</h2>

                <label>Class Level Applying For <span class="req">*</span>
                    <select name="program_applied"
                            class="<?= isset($errors['program_applied']) ? 'invalid' : '' ?>">
                        <option value="">— Select —</option>
                        <?php foreach ($classLevels as $level): ?>
                            <option value="<?= e($level) ?>" <?= $values['program_applied'] === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if (isset($errors['program_applied'])): ?><p class="field-error"><?= e($errors['program_applied']) ?></p><?php endif; ?>

                <h2>Additional information</h2>

                <label>Medical Conditions / Allergies (if any) <span class="hint">(optional)</span>
                    <textarea name="medical_notes"><?= e($values['medical_notes']) ?></textarea>
                </label>

                <label>Disabilities / Special Needs (if any) <span class="hint">(optional)</span>
                    <textarea name="special_needs"><?= e($values['special_needs']) ?></textarea>
                </label>

                <h2>Documents</h2>
                <p class="note">All optional. Accepted formats: PDF, JPG, or PNG, up to 5&nbsp;MB each.
                    If the form is returned with errors, please re-attach any files.</p>

                <?php foreach ($documentFields as $inputName => $docLabel): ?>
                    <label><?= e($docLabel) ?> <span class="hint">(optional)</span>
                        <input type="file" name="<?= e($inputName) ?>" accept=".pdf,.jpg,.jpeg,.png"
                               class="<?= isset($errors[$inputName]) ? 'invalid' : '' ?>">
                    </label>
                    <?php if (isset($errors[$inputName])): ?><p class="field-error"><?= e($errors[$inputName]) ?></p><?php endif; ?>
                <?php endforeach; ?>

                <div style="margin-top:1rem;">
                    <button type="submit" class="btn">Submit application</button>
                </div>
            </form>

        <?php endif; ?>
    </main>
</body>
</html>
