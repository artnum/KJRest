<?php
declare(strict_types=1);

namespace artnum\KJRest\HTTP;

class Request implements IRequest {
    protected string $base;
    protected array $request_parts;
    protected string|null $endpoint;
    protected string|null $item = null;
    protected string|null $rpc = null;
    protected string $verb;
    protected array $body = [];

    function __construct()
    {
        $this->base = dirname($_SERVER['SCRIPT_NAME']);
        $this->request_parts = array_filter(explode('/', trim($_SERVER['PATH_INFO'])), fn($part) => !empty($part));
        $this->endpoint = array_shift($this->request_parts);
        $this->item = array_shift($this->request_parts);
        if ($this->item !== null && strpos($this->item, '.') === 0) {
            $this->rpc = substr($this->item, 1);
            $this->item = array_shift($this->request_parts);
        }
        $this->verb = strtolower($_SERVER['REQUEST_METHOD']);

        switch ($this->verb) {
            case 'head':
            case 'get':
                // cannot have body
                break;
            default:
                $this->body = $this->parseBody();
                break;
        }
    }

    protected function parseBody(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        switch ($_SERVER['CONTENT_TYPE']) {
            case 'application/json':
                return json_decode($raw, true);
                break;
            case 'application/x-www-form-urlencoded':
                return parse_str($raw, $body);
            case 'multipart/form-data':
                $body = [];
                foreach (explode('&', $raw) as $pair) {
                    [$key, $value] = explode('=', $pair);
                    $body[$key] = $value;
                }
                return $body;
            default:
                return [];
        }
    }

    function getEndpoint(): string|null {
        return $this->endpoint;
    }

    function getItem(): string|null {
        return $this->item;
    }

    function getRPC(): string|null {
        return $this->rpc;
    }

    function getVerb(): string {
        return $this->verb;
    }

    function getBody(): array {
        return $this->body;
    }

    function getBase(): string {
        return $this->base;
    }
}