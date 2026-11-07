<?php

function lab_mode(): string {
    $mode = strtolower(trim((string) getenv("LAB_MODE")));
    return in_array($mode, ["secure", "vulnerable", "custom"], true) ? $mode : "secure";
}

function lab_vulnerability_flags(): array {
    static $flags = null;
    if ($flags !== null) {
        return $flags;
    }

    $names = [
        "BAC_VERTICAL" => "LAB_VULN_BAC_VERTICAL",
        "BFLA" => "LAB_VULN_BFLA",
        "BOLA" => "LAB_VULN_BOLA",
        "MASS_ASSIGN" => "LAB_VULN_MASS_ASSIGN",
        "PROPERTY_EXPOSURE" => "LAB_VULN_PROPERTY_EXPOSURE",
    ];
    $flags = [];
    foreach ($names as $key => $environmentKey) {
        $flags[$key] = getenv($environmentKey) === "1";
    }
    return $flags;
}

function lab_vuln(string $flag): bool {
    if (lab_mode() === "vulnerable") {
        return true;
    }
    if (lab_mode() !== "custom") {
        return false;
    }
    $flags = lab_vulnerability_flags();
    return $flags[$flag] ?? false;
}

function lab_bootstrap(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    header("X-Lab-Mode: " . lab_mode());
    $flags = lab_vulnerability_flags();
    $hasVulnerability = lab_mode() === "vulnerable"
        || (lab_mode() === "custom" && in_array(true, $flags, true));
    if (!$hasVulnerability) {
        return;
    }

    if (getenv("APP_ENV") !== "local") {
        error_log("Lab v2 refused: vulnerable mode requires APP_ENV=local.");
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => ["message" => "Vulnerable lab mode is only available in a local environment."]]);
        exit;
    }

    $ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
    $packed = @inet_pton($ip);
    $loopback = $ip === "localhost" || $ip === "::1" || str_starts_with($ip, "127.");
    $dockerBridge = false;
    if (is_string($packed) && strlen($packed) === 4) {
        $octets = array_values(unpack("C4", $packed));
        $dockerBridge = $octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31;
    }
    if (!$loopback && !$dockerBridge) {
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["error" => ["message" => "Vulnerable lab mode only accepts local or Docker bridge clients."]]);
        exit;
    }
}
