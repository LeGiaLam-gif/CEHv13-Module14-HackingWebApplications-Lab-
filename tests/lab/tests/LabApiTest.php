<?php

use PHPUnit\Framework\TestCase;

final class LabApiTest extends TestCase {
    private function client(): TestClient {
        return new TestClient();
    }

    private function expectedMode(): string {
        return getenv("LAB_MODE") ?: "secure";
    }

    public function testCustomerCannotReadAnotherCustomersOrderInSecureMode(): void {
        $client = $this->client();
        [$login] = $client->login("alice", "Alice@Lab2026");
        self::assertSame(200, $login->getStatusCode());

        $response = $client->request("GET", "/api/v2/orders/3");
        if ($this->expectedMode() === "vulnerable") {
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString("Bob Tran", (string) $response->getBody());
        } else {
            self::assertContains($response->getStatusCode(), [403, 404]);
            self::assertStringNotContainsString("Bob Tran", (string) $response->getBody());
        }
    }

    public function testSensitiveProfileFieldIsRejectedOrExposedAccordingToMode(): void {
        $client = $this->client();
        [$login] = $client->login("bob", "Bob@Lab2026");
        self::assertSame(200, $login->getStatusCode());
        $response = $client->request("PATCH", "/api/v2/me", ["store_credit" => 9000000]);
        if ($this->expectedMode() === "vulnerable") {
            self::assertSame(200, $response->getStatusCode());
        } else {
            self::assertSame(422, $response->getStatusCode());
        }
    }

    public function testProfileNeverReturnsPasswordHashInSecureMode(): void {
        $client = $this->client();
        [$login] = $client->login("alice", "Alice@Lab2026");
        self::assertSame(200, $login->getStatusCode());
        $response = $client->request("GET", "/api/v2/me");
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        if ($this->expectedMode() === "vulnerable") {
            self::assertArrayHasKey("password_hash", $body["data"]);
        } else {
            self::assertArrayNotHasKey("password_hash", $body["data"]);
        }
    }
}
