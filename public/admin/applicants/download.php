<?php

declare(strict_types=1);

// PATH NOTE: /public/admin/applicants/ — three levels up to /includes.
require_once __DIR__ . '/../../../includes/admin_bootstrap.php'; // admin-only; $pdo

/*
 * Secure document download.
 *
 * The uploaded files live in /uploads, OUTSIDE the web root, so there is no
 * direct URL to them — this script is the only way to read one, and it runs
 * behind admin_bootstrap (admin-only). We stream the bytes through PHP with
 * headers set from the file's real content, and we deliberately never expose
 * the randomized stored filename or any server path to the client.
 */

// A clean, path-free 404 used for every "can't serve this" case, so we never
// reveal whether a doc id exists, what it maps to, or where files live.
$serveNotFound = static function (): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Not found</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:480px;margin:3rem auto;color:#222;">'
        . '<h1 style="font-size:1.3rem;">Document not found</h1>'
        . '<p>The requested document could not be found.</p>'
        . '<p><a href="index.php">&larr; Back to applicants</a></p>'
        . '</body></html>';
    exit;
};

// doc_id must be a valid integer.
$docId = filter_input(INPUT_GET, 'doc_id', FILTER_VALIDATE_INT);
if ($docId === false || $docId === null) {
    $serveNotFound();
}

// Look up the document row.
$stmt = $pdo->prepare('SELECT file_path, original_name FROM applicant_documents WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

// Uploads base directory (sibling of /public, outside the web root).
$uploadsDir = realpath(__DIR__ . '/../../../uploads');

if ($doc === false || $uploadsDir === false) {
    $serveNotFound();
}

/*
 * Resolve the stored file safely. basename() keeps only the final path
 * component, so a stored value can never traverse out of the uploads directory
 * — defense in depth. We generated these names ourselves, but a value read back
 * from the DB is still never trusted directly as a filesystem path.
 */
$storedName = basename((string) $doc['file_path']);
$absPath    = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;
$realAbs    = realpath($absPath);

// The resolved path must exist, be a regular file, and sit strictly inside the
// uploads directory (guards against symlink escapes as well).
if ($realAbs === false
    || strncmp($realAbs, $uploadsDir . DIRECTORY_SEPARATOR, strlen($uploadsDir) + 1) !== 0
    || !is_file($realAbs)) {
    // The row exists but the file is missing/unreadable: log the real reason for
    // us, show the visitor the same generic 404 (no path leak).
    error_log('download.php: file unavailable for applicant_documents.id=' . $docId);
    $serveNotFound();
}

// Content-Type from the file's actual bytes, not any stored/guessed value.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($realAbs);
if (!is_string($mime) || $mime === '') {
    $mime = 'application/octet-stream';
}

// Download filename = the ORIGINAL name the applicant uploaded, sanitized for
// safe use in a header (strip path parts, control characters, and quotes to
// prevent header injection or a broken quoted-string).
$downloadName = basename((string) $doc['original_name']);
$downloadName = preg_replace('/[\x00-\x1F\x7F"]+/', '', $downloadName);
if ($downloadName === null || $downloadName === '') {
    $downloadName = 'document';
}

// Discard any buffered output so it can't corrupt the binary stream.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff'); // don't let the browser MIME-sniff
header('Cache-Control: private, no-store'); // sensitive; don't cache
header('Content-Transfer-Encoding: binary');
// Both a plain filename (ASCII clients) and RFC 5987 filename* (UTF-8) so the
// original name survives, including non-ASCII characters.
header(
    'Content-Disposition: attachment; filename="' . $downloadName . '"; '
    . "filename*=UTF-8''" . rawurlencode($downloadName)
);
header('Content-Length: ' . (string) filesize($realAbs));

readfile($realAbs);
exit;
