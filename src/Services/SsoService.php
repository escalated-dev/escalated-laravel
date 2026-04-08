<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\EscalatedSettings;

class SsoService
{
    /**
     * SSO configuration keys and their defaults.
     */
    protected array $configKeys = [
        'sso_provider' => 'none',
        'sso_entity_id' => '',
        'sso_url' => '',
        'sso_certificate' => '',
        'sso_attr_email' => 'email',
        'sso_attr_name' => 'name',
        'sso_attr_role' => 'role',
        'sso_jwt_secret' => '',
        'sso_jwt_algorithm' => 'HS256',
    ];

    /**
     * Get the current SSO configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        foreach ($this->configKeys as $key => $default) {
            $config[$key] = EscalatedSettings::get($key, $default);
        }

        return $config;
    }

    /**
     * Validate and save SSO configuration.
     */
    public function saveConfig(array $data): void
    {
        $allowedKeys = array_keys($this->configKeys);

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                EscalatedSettings::set($key, (string) $value);
            }
        }
    }

    /**
     * Validate a SAML assertion.
     *
     * @param  string  $samlResponse  The base64-encoded SAML response
     * @return array Parsed user attributes from the assertion
     *
     * @throws \RuntimeException If validation fails
     */
    public function validateSamlAssertion(string $samlResponse): array
    {
        $config = $this->getConfig();

        $xml = base64_decode($samlResponse, true);
        if ($xml === false) {
            throw new \RuntimeException('Invalid SAML response: base64 decode failed.');
        }

        $doc = new \DOMDocument;
        $prevUseErrors = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_use_internal_errors($prevUseErrors);
        if (! $loaded) {
            throw new \RuntimeException('Invalid SAML response: malformed XML.');
        }

        // Verify signature if certificate is configured
        $certificate = trim($config['sso_certificate']);
        if ($certificate !== '') {
            $this->verifySamlSignature($doc, $certificate);
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');

        // Check issuer matches configured entity ID
        $entityId = trim($config['sso_entity_id']);
        if ($entityId !== '') {
            $issuerNodes = $xpath->query('//saml:Issuer');
            if ($issuerNodes->length === 0) {
                throw new \RuntimeException('SAML assertion missing Issuer element.');
            }
            $issuer = trim($issuerNodes->item(0)->textContent);
            if ($issuer !== $entityId) {
                throw new \RuntimeException("SAML Issuer mismatch: expected '{$entityId}', got '{$issuer}'.");
            }
        }

        // Validate conditions (NotBefore / NotOnOrAfter)
        $conditionNodes = $xpath->query('//saml:Conditions');
        if ($conditionNodes->length > 0) {
            $conditions = $conditionNodes->item(0);
            $now = time();
            $skew = 120; // 2 minute clock skew tolerance

            $notBefore = $conditions->getAttribute('NotBefore');
            if ($notBefore !== '' && strtotime($notBefore) > ($now + $skew)) {
                throw new \RuntimeException('SAML assertion is not yet valid.');
            }

            $notOnOrAfter = $conditions->getAttribute('NotOnOrAfter');
            if ($notOnOrAfter !== '' && strtotime($notOnOrAfter) < ($now - $skew)) {
                throw new \RuntimeException('SAML assertion has expired.');
            }
        }

        // Extract user attributes
        $attrEmail = $config['sso_attr_email'];
        $attrName = $config['sso_attr_name'];
        $attrRole = $config['sso_attr_role'];

        $attributes = $this->extractSamlAttributes($xpath);

        $email = $attributes[$attrEmail] ?? null;
        if (! $email) {
            // Fall back to NameID
            $nameIdNodes = $xpath->query('//saml:Subject/saml:NameID');
            if ($nameIdNodes->length > 0) {
                $email = trim($nameIdNodes->item(0)->textContent);
            }
        }

        if (! $email) {
            throw new \RuntimeException('SAML assertion missing email attribute.');
        }

        return [
            'email' => $email,
            'name' => $attributes[$attrName] ?? '',
            'role' => $attributes[$attrRole] ?? '',
            'attributes' => $attributes,
        ];
    }

    /**
     * Verify the XML signature on a SAML response.
     */
    protected function verifySamlSignature(\DOMDocument $doc, string $certificate): void
    {
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signatureNodes = $xpath->query('//ds:Signature');
        if ($signatureNodes->length === 0) {
            throw new \RuntimeException('SAML response is not signed.');
        }

        $signedInfoNodes = $xpath->query('//ds:SignedInfo', $signatureNodes->item(0));
        $signatureValueNodes = $xpath->query('//ds:SignatureValue', $signatureNodes->item(0));

        if ($signedInfoNodes->length === 0 || $signatureValueNodes->length === 0) {
            throw new \RuntimeException('SAML signature is incomplete.');
        }

        $signatureValue = base64_decode(
            preg_replace('/\s+/', '', $signatureValueNodes->item(0)->textContent)
        );

        // Canonicalize SignedInfo
        $signedInfoXml = $signedInfoNodes->item(0)->C14N(true, false);

        // Prepare certificate
        $certPem = $certificate;
        if (strpos($certPem, '-----BEGIN CERTIFICATE-----') === false) {
            $certPem = "-----BEGIN CERTIFICATE-----\n"
                .chunk_split($certPem, 64, "\n")
                ."-----END CERTIFICATE-----\n";
        }

        $pubKey = openssl_pkey_get_public($certPem);
        if ($pubKey === false) {
            throw new \RuntimeException('Invalid SSO certificate.');
        }

        $result = openssl_verify($signedInfoXml, $signatureValue, $pubKey, OPENSSL_ALGO_SHA256);
        if ($result === 0) {
            // Try SHA1 as fallback (common in SAML)
            $result = openssl_verify($signedInfoXml, $signatureValue, $pubKey, OPENSSL_ALGO_SHA1);
        }

        if ($result !== 1) {
            throw new \RuntimeException('SAML signature verification failed.');
        }
    }

    /**
     * Extract attribute name-value pairs from SAML assertion.
     */
    protected function extractSamlAttributes(\DOMXPath $xpath): array
    {
        $attributes = [];

        $attrNodes = $xpath->query('//saml:AttributeStatement/saml:Attribute');
        foreach ($attrNodes as $attr) {
            $name = $attr->getAttribute('Name');
            $valueNodes = $xpath->query('saml:AttributeValue', $attr);
            if ($valueNodes->length > 0) {
                $attributes[$name] = trim($valueNodes->item(0)->textContent);
            }
        }

        return $attributes;
    }

    /**
     * Validate a JWT token.
     *
     * @param  string  $token  The JWT token string
     * @return array Decoded token payload with mapped user attributes
     *
     * @throws \RuntimeException If validation fails
     */
    public function validateJwtToken(string $token): array
    {
        $config = $this->getConfig();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT: expected 3 segments.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Decode header
        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (! $header || ! isset($header['alg'])) {
            throw new \RuntimeException('Invalid JWT: malformed header.');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (! $payload) {
            throw new \RuntimeException('Invalid JWT: malformed payload.');
        }

        // Verify signature
        $secret = $config['sso_jwt_secret'];
        $algorithm = $config['sso_jwt_algorithm'] ?: 'HS256';

        if ($secret === '') {
            throw new \RuntimeException('JWT secret is not configured.');
        }

        $signature = $this->base64UrlDecode($signatureB64);
        $signingInput = $headerB64.'.'.$payloadB64;

        if (! $this->verifyJwtSignature($signingInput, $signature, $secret, $algorithm)) {
            throw new \RuntimeException('Invalid JWT: signature verification failed.');
        }

        // Check expiration
        $now = time();
        $skew = 60; // 1 minute clock skew tolerance

        if (isset($payload['exp']) && $payload['exp'] < ($now - $skew)) {
            throw new \RuntimeException('JWT has expired.');
        }

        if (isset($payload['nbf']) && $payload['nbf'] > ($now + $skew)) {
            throw new \RuntimeException('JWT is not yet valid.');
        }

        // Extract user attributes
        $attrEmail = $config['sso_attr_email'];
        $attrName = $config['sso_attr_name'];
        $attrRole = $config['sso_attr_role'];

        $email = $payload[$attrEmail] ?? $payload['email'] ?? $payload['sub'] ?? null;
        if (! $email) {
            throw new \RuntimeException('JWT missing email claim.');
        }

        return [
            'email' => $email,
            'name' => $payload[$attrName] ?? $payload['name'] ?? '',
            'role' => $payload[$attrRole] ?? $payload['role'] ?? '',
            'claims' => $payload,
        ];
    }

    /**
     * Verify a JWT signature.
     */
    protected function verifyJwtSignature(string $input, string $signature, string $secret, string $algorithm): bool
    {
        $algoMap = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        ];

        if (isset($algoMap[$algorithm])) {
            $expected = hash_hmac($algoMap[$algorithm], $input, $secret, true);

            return hash_equals($expected, $signature);
        }

        // RSA algorithms
        $rsaAlgoMap = [
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        ];

        if (isset($rsaAlgoMap[$algorithm])) {
            $pubKey = openssl_pkey_get_public($secret);
            if ($pubKey === false) {
                throw new \RuntimeException("Invalid public key for {$algorithm}.");
            }

            return openssl_verify($input, $signature, $pubKey, $rsaAlgoMap[$algorithm]) === 1;
        }

        throw new \RuntimeException("Unsupported JWT algorithm: {$algorithm}");
    }

    /**
     * Base64url decode (JWT variant without padding).
     */
    protected function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'), true) ?: '';
    }

    /**
     * Check if SSO is enabled.
     */
    public function isEnabled(): bool
    {
        $provider = EscalatedSettings::get('sso_provider', 'none');

        return $provider !== 'none';
    }

    /**
     * Get the active SSO provider type.
     */
    public function getProvider(): string
    {
        return EscalatedSettings::get('sso_provider', 'none');
    }
}
