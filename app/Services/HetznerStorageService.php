<?php

namespace App\Services;

/**
 * Hetzner Storage Share (Nextcloud 32) integration.
 *
 * Uses WebDAV for file operations and the OCS API for share link management.
 * All connections are HTTPS with full certificate verification.
 *
 * .env keys required:
 *   HETZNER_STORAGE_URL  — WebDAV base URL (includes username path segment)
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

        $parts         = parse_url($this->davBase);
        $this->ocsBase = $parts['scheme'] . '://' . $parts['host'];
    }

    // ─── File Operations ──────────────────────────────────────────────────────

    /**
     * Upload a local file to remote storage.
     */
    public function upload(string $remotePath, string $localPath): bool
    {
        $url  = $this->davBase . '/' . ltrim($remotePath, '/');
        $fp   = fopen($localPath, 'r');
        $size = filesize($localPath);

        $ch = $this->curl($url, timeout: 7200);
        curl_setopt_array($ch, [
            CURLOPT_PUT        => true,
            CURLOPT_INFILE     => $fp,
            CURLOPT_INFILESIZE => $size,
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
     * Upload a Laravel UploadedFile instance.
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
        $ch = $this->curl($this->davBase . '/' . ltrim($remotePath, '/'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 204;
    }

    /**
     * Create a remote directory. Safe to call if it already exists (405 = already exists).
     */
    public function mkdir(string $remotePath): bool
    {
        $ch = $this->curl($this->davBase . '/' . ltrim($remotePath, '/') . '/');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 405]);
    }

    /**
     * Copy a file on the remote storage.
     */
    public function copy(string $fromPath, string $toPath): bool
    {
        $destination = $this->davBase . '/' . ltrim($toPath, '/');
        $ch = $this->curl($this->davBase . '/' . ltrim($fromPath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'COPY',
            CURLOPT_HTTPHEADER    => [
                'Destination: ' . $destination,
                'Overwrite: T',
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
        $destination = $this->davBase . '/' . ltrim($toPath, '/');
        $ch = $this->curl($this->davBase . '/' . ltrim($fromPath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'MOVE',
            CURLOPT_HTTPHEADER    => [
                'Destination: ' . $destination,
                'Overwrite: T',
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 204]);
    }

    /**
     * List files and folders in a remote directory. Returns array of hrefs (paths).
     */
    public function listDirectory(string $remotePath): array
    {
        $ch = $this->curl($this->davBase . '/' . ltrim($remotePath, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_HTTPHEADER    => [
                'Depth: 1',
                'OCS-APIRequest: true',
            ],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        preg_match_all('/<d:href>([^<]+)<\/d:href>/', (string)$body, $matches);
        return $matches[1] ?? [];
    }

    // ─── Sharing ──────────────────────────────────────────────────────────────

    /**
     * Create a public share and return the direct download URL.
     *
     * @param string      $remotePath  Path relative to the user root (e.g. "movies/film.mp4")
     * @param string|null $password    Optional share password
     * @param string|null $expireDate  Optional expiry (YYYY-MM-DD)
     * @return string|null             Direct download URL or null on failure
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

        $ch = $this->curl($this->ocsBase . '/ocs/v2.php/apps/files_sharing/api/v1/shares');
        curl_setopt_array($ch, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => ['OCS-APIRequest: true', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data  = json_decode((string)$body, true);
        $token = $data['ocs']['data']['token'] ?? null;

        return $token ? "{$this->ocsBase}/s/{$token}/download" : null;
    }

    /**
     * Upload a file and immediately return a public direct-download URL.
     */
    public function uploadAndShare(string $remotePath, string $localPath): ?string
    {
        if (!$this->upload($remotePath, $localPath)) return null;
        return $this->share($remotePath);
    }

    /**
     * Upload a Laravel UploadedFile and return a public direct-download URL.
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
        $ch = $this->curl($this->ocsBase . "/ocs/v2.php/apps/files_sharing/api/v1/shares/{$shareId}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER    => ['OCS-APIRequest: true'],
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
        $ch = $this->curl($this->ocsBase . '/ocs/v2.php/apps/files_sharing/api/v1/shares');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['OCS-APIRequest: true', 'Accept: application/json']);
        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$body, true);
        return $data['ocs']['data'] ?? [];
    }

    // ─── Quota ────────────────────────────────────────────────────────────────

    /**
     * Returns ['used' => bytes, 'free' => bytes_or_-3_for_unlimited].
     */
    public function quota(): array
    {
        $xml = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop>'
             . '<d:quota-available-bytes/><d:quota-used-bytes/>'
             . '</d:prop></d:propfind>';

        $ch = $this->curl($this->davBase . '/');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_POSTFIELDS    => $xml,
            CURLOPT_HTTPHEADER    => ['Depth: 0', 'Content-Type: application/xml'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        preg_match('/<d:quota-used-bytes>(\d+)</', (string)$body, $used);
        preg_match('/<d:quota-available-bytes>(-?\d+)</', (string)$body, $free);

        return [
            'used' => (int) ($used[1] ?? 0),
            'free' => (int) ($free[1] ?? -3),
        ];
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Create a cURL handle pre-configured with auth, SSL verification, and timeout.
     * All Hetzner Storage connections are HTTPS — peer and host verification is
     * always enabled using the system CA bundle.
     */
    private function curl(string $url, int $timeout = 60): \CurlHandle
    {
        $ch = curl_init($url);

        $opts = [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ];

        $caBundle = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($caBundle)) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $opts);
        return $ch;
    }
}
