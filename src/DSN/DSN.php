<?php

namespace Utopia\DSN;

class DSN
{
    /**
     * @var string
     */
    protected string $scheme;

    /**
     * @var ?string
     */
    protected ?string $user;

    /**
     * @var ?string
     */
    protected ?string $password;

    /**
     * @var string
     */
    protected string $host;

    /**
     * @var ?string
     */
    protected ?string $port;

    /**
     * @var ?string
     */
    protected ?string $path;

    /**
     * @var ?string
     */
    protected ?string $query;

    /**
     * @var ?array
     */
    protected ?array $params = null;

    /**
     * Construct
     *
     * Construct a new DSN object
     *
     * @param  string  $dsn
     */
    public function __construct(string $dsn)
    {
        $parts = $this->parseDsn($dsn);

        if (empty($parts['scheme'])) {
            throw new \InvalidArgumentException('Unable to parse DSN: scheme is required');
        }

        $hasAuthority = (($parts['host'] ?? '') !== '') || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']);
        $hasPath = array_key_exists('path', $parts) && $parts['path'] !== '';
        $hasQuery = array_key_exists('query', $parts) && $parts['query'] !== '';

        if (! $hasAuthority && ! $hasPath && ! $hasQuery) {
            throw new \InvalidArgumentException('Unable to parse DSN: missing connection information');
        }

        $this->scheme = $parts['scheme'];
        $this->user = isset($parts['user']) ? \urldecode($parts['user']) : null;
        $this->password = isset($parts['pass']) ? \urldecode($parts['pass']) : null;
        $this->host = $parts['host'] ?? '';
        $this->port = $parts['port'] ?? null;
        $this->path = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
        $this->query = $parts['query'] ?? null;
    }

    /**
     * Parse a DSN string while tolerating missing host values.
     *
     * @return array<string, mixed>
     */
    protected function parseDsn(string $dsn): array
    {
        $parts = \parse_url($dsn);

        if ($parts !== false) {
            return $parts;
        }

        if (! \preg_match('/^(?<scheme>[a-z][a-z0-9+\.\-]*):\/\/(?<rest>.*)$/i', $dsn, $matches)) {
            throw new \InvalidArgumentException("Unable to parse DSN: $dsn");
        }

        $placeholder = '__utopia_dsn_placeholder__';
        $rest = $matches['rest'];

        $authorityEnd = strcspn($rest, '/?#');
        $authority = substr($rest, 0, $authorityEnd);
        $suffix = substr($rest, $authorityEnd);

        $hostMissing = $authority === '';

        if (! $hostMissing && \str_contains($authority, '@')) {
            $afterAt = substr($authority, strrpos($authority, '@') + 1);
            $hostMissing = $afterAt === '';
            $authority = rtrim($authority, '@');
        }

        if ($hostMissing) {
            $authority = $authority === '' ? $placeholder : $authority . '@' . $placeholder;
        }

        $normalized = $matches['scheme'] . '://' . $authority . $suffix;

        $parts = \parse_url($normalized);

        if ($parts === false) {
            throw new \InvalidArgumentException("Unable to parse DSN: $dsn");
        }

        if (($parts['host'] ?? null) === $placeholder) {
            $parts['host'] = '';
        }

        return $parts;
    }

    /**
     * Return the scheme.
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Return the user.
     *
     * @return ?string
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * Return the password.
     *
     * @return ?string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Return the host
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Return the port
     *
     * @return ?string
     */
    public function getPort(): ?string
    {
        return $this->port;
    }

    /**
     * Return the path
     *
     * @return ?string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Return the raw query string
     *
     * @return ?string
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Return a query parameter by its key
     *
     * @return string
     */
    public function getParam(string $key, string $default = ''): string
    {
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }

        if (! $this->query) {
            return $default;
        }

        parse_str($this->query, $this->params);

        return $this->params[$key] ?? $default;
    }
}
