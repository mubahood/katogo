<?php

namespace App\Services;

/**
 * Hetzner Storage Share (Nextcloud 32) integration.
 *
 * Uses WebDAV for file operations and the OCS API for share link management.
 * Credentials are read from .env:
 *   HETZNER_STORAGE_URL  — WebDAV base (includes username path segment)
 *   HETZNER_STORAGE_USER — Nextcloud username
 *   HETZNER_STORAGE_PASS — Nextcloud password
 *
 * Public URL pattern: https://{host}/s/{token}/download
 */
class HetznerStorageService
{
    private string $davBase;
    private string $ocsBase;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->davBase = rtrim(env('HETZNER_STORAGE_URL'), '/');
        $this->user    = env('HETZNER_STORAGE_USER');
        $this->pass    = env('HETZNER_STORAGE_PASS');

        // Derive the host root from the WebDAV URL
        $parts          = parse_url($this->davBase);
        $this->ocsBase  = $parts['scheme'] . '://' . $parts['host'];
    }

    // ─── File Operations ──────────────────────────────────────────────────────

    /**
     * Upload a local file to remote storage.
     * Creates parent directories automatically if needed.
     */
    public function upload(string $remotePath, string $localPath): bool
    {
        $url  = $this->davBase . '/' . ltrim($remotePath, '/');
        $fp   = fopen($localPath, 'r');
        $size = filesize($localPath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => $size,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        return in_array($code, [201, 204]);
    }

    /**
     * Upload raw string/binary content without a local file.
     */
    public function uploadContent(string $remotePath, string $content): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hetzner_');
        file_put_contents($tmp, $content);
        $result = $this->upload($remotePath, $tmp);
        unlink($tmp);
        return $result;
    }

    /**
     * Upload a Laravel UploadedFile / Symfony File instance.
     */
    public function uploadFile(string $remotePath, \Illuminate\Http\UploadedFile $file): bool
    {
        return $this->upload($remotePath, $file->getRealPath());
    }

    /**
     * Delete a remote file.
     */
    public function delete(string $remotePath): bool
    {
        $ch = curl_init($this->davBase . '/' . ltrim($remotePath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 204;
    }

    /**
     * Create a remote directory. Safe to call if directory already exists (returns true).
     */
    public function mkdir(string $remotePath): bool
    {
        $ch = curl_init($this->davBase . '/' . ltrim($remotePath, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'MKCOL',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 405]); // 405 = already exists
    }

    /**
     * Copy a file on the remote storage.
     */
    public function copy(string $fromPath, string $toPath): bool
    {
        $ch = curl_init($this->davBase . '/' . ltrim($fromPath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'COPY',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Destination: ' . $this->davBase . '/' . ltrim($toPath, '/'),
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 204]);
    }

    /**
     * Move / rename a file on the remote storage.
     */
    public function move(string $fromPath, string $toPath): bool
    {
        $ch = curl_init($this->davBase . '/' . ltrim($fromPath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'MOVE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Destination: ' . $this->davBase . '/' . ltrim($toPath, '/'),
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 204]);
    }

    /**
     * List files and folders in a remote directory.
     * Returns array of hrefs (paths).
     */
    public function listDirectory(string $remotePath): array
    {
        $ch = curl_init($this->davBase . '/' . ltrim($remotePath, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Depth: 1'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        preg_match_all('/<d:href>([^<]+)<\/d:href>/', $body, $matches);
        return $matches[1] ?? [];
    }

    // ─── Sharing ──────────────────────────────────────────────────────────────

    /**
     * Create a public share and return the direct download URL.
     *
     * @param string      $remotePath  Path relative to the user root (e.g. "movies/film.mp4")
     * @param string|null $password    Optional password to protect the link
     * @param string|null $expireDate  Optional expiry date (YYYY-MM-DD)
     * @return string|null             Direct URL or null on failure
     */
    public function share(string $remotePath, ?string $password = null, ?string $expireDate = null): ?string
    {
        $params = [
            'path'        => '/' . ltrim($remotePath, '/'),
            'shareType'   => 3,
            'permissions' => 1,
        ];
        if ($password)   $params['password']   = $password;
        if ($expireDate) $params['expireDate']  = $expireDate;

        $ch = curl_init($this->ocsBase . '/ocs/v2.php/apps/files_sharing/api/v1/shares');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data  = json_decode($body, true);
        $token = $data['ocs']['data']['token'] ?? null;

        return $token ? "{$this->ocsBase}/s/{$token}/download" : null;
    }

    /**
     * Upload a file and immediately return a public direct-download URL.
     * This is the primary one-liner for most use cases.
     */
    public function uploadAndShare(string $remotePath, string $localPath): ?string
    {
        if (!$this->upload($remotePath, $localPath)) return null;
        return $this->share($remotePath);
    }

    /**
     * Upload an UploadedFile and return a public direct-download URL.
     */
    public function uploadFileAndShare(string $remotePath, \Illuminate\Http\UploadedFile $file): ?string
    {
        return $this->uploadAndShare($remotePath, $file->getRealPath());
    }

    /**
     * Delete a share by its share ID (not the token).
     */
    public function deleteShare(int $shareId): bool
    {
        $ch = curl_init($this->ocsBase . "/ocs/v2.php/apps/files_sharing/api/v1/shares/{$shareId}");
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true'],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    /**
     * List all active shares. Returns the raw OCS data array.
     */
    public function listShares(): array
    {
        $ch = curl_init($this->ocsBase . '/ocs/v2.php/apps/files_sharing/api/v1/shares');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($body, true);
        return $data['ocs']['data'] ?? [];
    }

    // ─── Quota ───────────────────────────────────────────────────────────────

    /**
     * Returns ['used' => bytes, 'free' => bytes_or_-3_for_unlimited].
     */
    public function quota(): array
    {
        $xml = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop>'
             . '<d:quota-available-bytes/><d:quota-used-bytes/>'
             . '</d:prop></d:propfind>';

        $ch = curl_init($this->davBase . '/');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Depth: 0', 'Content-Type: application/xml'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        preg_match('/<d:quota-used-bytes>(\d+)</', $body, $used);
        preg_match('/<d:quota-available-bytes>(-?\d+)</', $body, $free);

        return [
            'used' => (int) ($used[1] ?? 0),
            'free' => (int) ($free[1] ?? -3),
        ];
    }
}
