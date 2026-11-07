<?php

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;

final class TestClient {
    public Client $http;
    public CookieJar $cookies;
    public ?string $csrf = null;

    public function __construct() {
        $this->cookies = new CookieJar();
        $this->http = new Client([
            "base_uri" => getenv("LAB_TEST_BASE_URL") ?: "http://127.0.0.1:8090",
            "cookies" => $this->cookies,
            "http_errors" => false,
            "timeout" => 10,
        ]);
    }

    public function login(string $username, string $password): array {
        $response = $this->http->post("/api/v2/auth/login", [
            "json" => ["username" => $username, "password" => $password],
        ]);
        $body = json_decode((string) $response->getBody(), true);
        $this->csrf = $body["data"]["csrf_token"] ?? null;
        return [$response, $body];
    }

    public function request(string $method, string $uri, ?array $json = null) {
        $options = [];
        if ($json !== null) {
            $options["json"] = $json;
        }
        if (in_array(strtoupper($method), ["POST", "PUT", "PATCH", "DELETE"], true)
            && $this->csrf !== null) {
            $options["headers"]["X-CSRF-Token"] = $this->csrf;
        }
        return $this->http->request($method, $uri, $options);
    }
}
