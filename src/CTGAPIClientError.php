<?php
declare(strict_types=1);

namespace CTG\ApiClient;

// Typed error class for API client transport and HTTP failures
class CTGAPIClientError extends \Exception {

    /* Constants */
    const TYPES = [
        // 1xxx — Connection
        'CONNECTION_FAILED'  => 1000,
        'TIMEOUT'            => 1001,
        'DNS_FAILED'         => 1002,
        'SSL_ERROR'          => 1003,
        // 2xxx — Request/Response
        'REQUEST_FAILED'     => 2000,
        'INVALID_JSON'       => 2001,
        // 3xxx — Validation
        'INVALID_URL'        => 3000,
        'INVALID_METHOD'     => 3001,
        // 4xxx — HTTP (caller-initiated)
        'HTTP_ERROR'         => 4000,
    ];

    /* Instance Properties */
    public readonly string $type;
    public readonly string $msg;
    public readonly mixed  $data;
    private bool $_handled = false;

    // CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> $this
    // Creates a new error — accepts type name or integer code
    public function __construct(
        string|int $type,
        ?string    $msg = null,
        mixed      $data = null
    ) {
        if (is_string($type)) {
            $this->type = $type;
            $code = self::TYPES[$type]
                ?? throw new \InvalidArgumentException("Unknown CTGAPIClientError type: {$type}");
        } else {
            $code = $type;
            $this->type = self::lookup($type)
                ?? throw new \InvalidArgumentException("Unknown CTGAPIClientError code: {$type}");
        }

        $this->msg  = $msg ?? $this->type;
        $this->data = $data;
        parent::__construct($this->msg, $code);
    }

    /**
     *
     * Instance Methods
     *
     */

    // :: STRING|INT, (ctgapiClientError -> VOID) -> $this
    // Handle error if it matches the given type. Chainable. Short-circuits after first match.
    public function on(string|int $type, callable $handler): static {
        $code = is_string($type) ? (self::TYPES[$type] ?? null) : $type;

        if (!$this->_handled && $this->getCode() === $code) {
            $handler($this);
            $this->_handled = true;
        }
        return $this;
    }

    // :: (ctgapiClientError -> VOID) -> VOID
    // Handle error if no previous on() matched
    public function otherwise(callable $handler): void {
        if (!$this->_handled) {
            $handler($this);
        }
    }

    /**
     *
     * Static Methods
     *
     */

    // :: STRING|INT -> INT|STRING|NULL
    // Bidirectional lookup — name to code or code to name
    public static function lookup(string|int $key): int|string|null {
        if (is_string($key)) {
            return self::TYPES[$key] ?? null;
        }
        $result = array_search($key, self::TYPES, true);
        return $result !== false ? $result : null;
    }
}
