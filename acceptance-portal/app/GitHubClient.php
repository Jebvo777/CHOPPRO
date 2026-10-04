<?php
declare(strict_types=1);

final class GitHubFailure extends RuntimeException
{
    public function __construct(string $message, public int $retryAt = 0)
    {
        parent::__construct($message);
    }
}

final class GitHubClient
{
    private string $base;

    public function __construct(private array $config, private ?Closure $transport = null)
    {
        $this->base = 'https://api.github.com/repos/'.rawurlencode($config['owner']).'/'.rawurlencode($config['repository']);
    }

    public function api(string $path, string $etag = ''): array
    {
        $response = $this->request($this->base.$path, true, $etag);
        if ($response['status'] === 304) {
            return ['data' => null, 'etag' => $etag, 'not_modified' => true];
        }
        $this->check($response);
        try {
            $data = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new GitHubFailure('GitHub вернул некорректный JSON.');
        }
        if (!is_array($data)) {
            throw new GitHubFailure('Некорректный ответ GitHub.');
        }
        return ['data' => $data, 'etag' => $response['headers']['etag'] ?? '', 'not_modified' => false];
    }

    public function blobs(array $entries, string $commit): array
    {
        $out = [];
        $requests = [];
        foreach ($entries as $entry) {
            $sha = $entry['sha'];
            if (isset($requests[$sha])) {
                continue;
            }
            $authenticated = $this->config['token'] !== '';
            $url = $authenticated
                ? $this->base.'/git/blobs/'.$sha
                : 'https://raw.githubusercontent.com/'.rawurlencode($this->config['owner']).'/'
                    .rawurlencode($this->config['repository']).'/'.$commit.'/'
                    .implode('/', array_map('rawurlencode', explode('/', $entry['path'])));
            $requests[$sha] = ['url' => $url, 'auth' => $authenticated, 'sha' => $sha];
        }
        foreach (array_chunk($requests, 4, true) as $chunk) {
            foreach ($this->batch($chunk) as $sha => $response) {
                $this->check($response);
                $body = $response['body'];
                if ($chunk[$sha]['auth']) {
                    $blob = json_decode($body, true);
                    $body = is_array($blob) && ($blob['encoding'] ?? '') === 'base64'
                        ? base64_decode(str_replace(["\n", "\r"], '', $blob['content'] ?? ''), true) : false;
                    if ($body === false) {
                        throw new GitHubFailure('GitHub не вернул содержимое файла.');
                    }
                }
                if (strlen($body) > $this->config['max_file_bytes']
                    || sha1('blob '.strlen($body)."\0".$body) !== $sha) {
                    throw new GitHubFailure('Проверка целостности файла не пройдена. Сохранена предыдущая версия.');
                }
                $out[$sha] = $body;
            }
        }
        return $out;
    }

    private function check(array $response): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }
        $headers = $response['headers'];
        $retry = isset($headers['retry-after']) ? time() + max(60, (int)$headers['retry-after']) : 0;
        if (($headers['x-ratelimit-remaining'] ?? '1') === '0') {
            $retry = max($retry, (int)($headers['x-ratelimit-reset'] ?? time() + 3600));
        }
        throw new GitHubFailure(
            in_array($response['status'], [403, 429], true)
                ? 'GitHub временно ограничил запросы. Повторная проверка запланирована автоматически.'
                : 'Связь с GitHub недоступна (HTTP '.$response['status'].').',
            $retry
        );
    }

    private function headers(bool $auth, string $etag = ''): array
    {
        $headers = ['User-Agent: CHOPPRO-Portal/5', 'Accept: application/vnd.github+json'];
        if ($auth && $this->config['token'] !== '') {
            $headers[] = 'Authorization: Bearer '.$this->config['token'];
        }
        if ($etag !== '') {
            $headers[] = 'If-None-Match: '.$etag;
        }
        return $headers;
    }

    private function handle(string $url, bool $auth, string $etag, array &$response)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => $this->headers($auth, $etag),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$response): int {
                
                if (strlen($response['body']) + strlen($data) > max(16 * 1024 * 1024, $this->config['max_file_bytes'] * 2)) {
                    return 0;
                }
                $response['body'] .= $data;
                return strlen($data);
            },
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$response): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $response['headers'][strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        return $ch;
    }

    private function request(string $url, bool $auth, string $etag = ''): array
    {
        if ($this->transport) {
            return ($this->transport)($url, $this->headers($auth, $etag));
        }
        $response = ['status' => 0, 'headers' => [], 'body' => ''];
        if (function_exists('curl_init')) {
            $ch = $this->handle($url, $auth, $etag, $response);
            curl_exec($ch);
            $response['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $response;
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'header' => implode("\r\n", $this->headers($auth, $etag)),
                'timeout' => $this->config['timeout'], 'ignore_errors' => true, 'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream) {
            $response['body'] = (string)stream_get_contents($stream, max(16 * 1024 * 1024, $this->config['max_file_bytes'] * 2) + 1);
            $meta = stream_get_meta_data($stream);
            foreach ($meta['wrapper_data'] ?? [] as $line) {
                if (preg_match('/^HTTP\/\S+ (\d+)/', $line, $m)) {
                    $response['status'] = (int)$m[1];
                } elseif (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $response['headers'][strtolower(trim($key))] = trim($value);
                }
            }
            fclose($stream);
        }
        return $response;
    }

    private function batch(array $requests): array
    {
        if ($this->transport || !function_exists('curl_multi_init')) {
            $out = [];
            foreach ($requests as $sha => $request) {
                $out[$sha] = $this->request($request['url'], $request['auth']);
            }
            return $out;
        }
        $multi = curl_multi_init();
        $out = [];
        $handles = [];
        foreach ($requests as $sha => $request) {
            $out[$sha] = ['status' => 0, 'headers' => [], 'body' => ''];
            $handles[$sha] = $this->handle($request['url'], $request['auth'], '', $out[$sha]);
            curl_multi_add_handle($multi, $handles[$sha]);
        }
        do {
            $code = curl_multi_exec($multi, $running);
            if ($running) {
                if (curl_multi_select($multi, 0.2) === -1) {
                    usleep(10000);
                }
            }
        } while ($running && $code === CURLM_OK);
        foreach ($handles as $sha => $handle) {
            $out[$sha]['status'] = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        return $out;
    }
}
