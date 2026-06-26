<?php
/**
 * YADS OCI Distribution Spec v2 — read-only registry
 *
 * Serves OCI image layouts stored under data/{namespace}/{image}/.
 * Write endpoints return 403. Authentication is handled by Apache via .htaccess.
 *
 * Image format on disk (OCI image layout, as produced by skopeo):
 *   data/yads/yads-api/
 *     oci-layout          {"imageLayoutVersion":"1.0.0"}
 *     index.json          OCI index — maps tags (via annotations) to manifest digests
 *     blobs/sha256/<hash> blobs (manifests + encrypted layer tars)
 */

define('DATA_DIR', __DIR__ . '/data');

header('Docker-Distribution-API-Version: registry/2.0');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = '/' . ltrim($path, '/');

// Reject write methods globally — this is a pull-only registry
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['errors' => [['code' => 'UNAUTHORIZED', 'message' => 'push not supported']]]);
    exit;
}

// GET /v2/ — version check
if ($path === '/v2/' || $path === '/v2') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo '{}';
    exit;
}

// Route: /v2/{name...}/manifests/{reference}  or  /v2/{name...}/blobs/{digest}
if (!preg_match('#^/v2/(.+)/(manifests|blobs)/([^/]+)$#', $path, $m)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['errors' => [['code' => 'NAME_UNKNOWN', 'message' => 'not found']]]);
    exit;
}

// Sanitise path components — no traversal, no backslash
$name      = preg_replace('#[^a-zA-Z0-9/_.-]#', '', $m[1]);
$type      = $m[2];
$reference = preg_replace('#[^a-zA-Z0-9:._-]#', '', $m[3]);

$image_dir = DATA_DIR . '/' . $name;

if (!is_dir($image_dir)) {
    api_error(404, 'NAME_UNKNOWN', "repository $name not found");
}

if ($type === 'manifests') {
    serve_manifest($image_dir, $reference, $method);
} else {
    serve_blob($image_dir, $reference, $method);
}

// ─────────────────────────────────────────────────────────────────────────────

function serve_manifest(string $image_dir, string $reference, string $method): void
{
    // sha256: digest → read blob directly
    if (str_starts_with($reference, 'sha256:')) {
        $path = blob_path($image_dir, $reference);
        if (!$path) { api_error(404, 'MANIFEST_UNKNOWN', "digest $reference not found"); }
        $data = file_get_contents($path);
        $mf   = json_decode($data, true);
        $mt   = $mf['mediaType'] ?? 'application/vnd.oci.image.manifest.v1+json';
        send_file($path, $mt, $reference, $method);
        return;
    }

    // Tag → resolve via index.json
    $index_path = $image_dir . '/index.json';
    if (!file_exists($index_path)) {
        api_error(404, 'MANIFEST_UNKNOWN', "no index for tag $reference");
    }
    $index = json_decode(file_get_contents($index_path), true);

    foreach (($index['manifests'] ?? []) as $entry) {
        $ref_name = $entry['annotations']['org.opencontainers.image.ref.name'] ?? '';
        if ($ref_name !== $reference) { continue; }

        $digest = $entry['digest'];
        $path   = blob_path($image_dir, $digest);
        if (!$path) { api_error(404, 'MANIFEST_UNKNOWN', "blob for tag $reference missing"); }
        $mt = $entry['mediaType'] ?? 'application/vnd.oci.image.manifest.v1+json';
        send_file($path, $mt, $digest, $method);
        return;
    }

    api_error(404, 'MANIFEST_UNKNOWN', "tag $reference not found");
}

function serve_blob(string $image_dir, string $reference, string $method): void
{
    if (!str_starts_with($reference, 'sha256:')) {
        api_error(400, 'DIGEST_INVALID', "blob reference must start with sha256:");
    }
    $path = blob_path($image_dir, $reference);
    if (!$path) {
        api_error(404, 'BLOB_UNKNOWN', "blob $reference not found");
    }
    send_file($path, 'application/octet-stream', $reference, $method);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Returns the filesystem path for a digest, or null if it doesn't exist. */
function blob_path(string $image_dir, string $digest): ?string
{
    if (!str_starts_with($digest, 'sha256:')) { return null; }
    $hash = substr($digest, 7);
    if (!preg_match('/^[a-f0-9]{64}$/', $hash)) { return null; }
    $p = $image_dir . '/blobs/sha256/' . $hash;
    return file_exists($p) ? $p : null;
}

/**
 * Stream a file to the client.
 * Uses X-Sendfile when available (mod_xsendfile on Apache) to avoid PHP
 * buffering multi-GB layer blobs in memory.
 */
function send_file(string $path, string $content_type, string $digest, string $method): void
{
    $size = filesize($path);
    if (!str_starts_with($digest, 'sha256:')) {
        $digest = 'sha256:' . hash_file('sha256', $path);
    }

    header('Content-Type: ' . $content_type);
    header('Docker-Content-Digest: ' . $digest);
    header('Content-Length: ' . $size);
    http_response_code(200);

    if ($method === 'HEAD') { return; }

    // Try X-Sendfile (requires Apache mod_xsendfile or nginx X-Accel-Redirect)
    if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules())) {
        header('X-Sendfile: ' . realpath($path));
        return;
    }

    // Chunked readfile to avoid exhausting PHP memory limit on large blobs
    $fp = fopen($path, 'rb');
    while (!feof($fp)) {
        echo fread($fp, 2 * 1024 * 1024); // 2 MB chunks
        flush();
    }
    fclose($fp);
}

function api_error(int $status, string $code, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['errors' => [['code' => $code, 'message' => $message]]]);
    exit;
}
