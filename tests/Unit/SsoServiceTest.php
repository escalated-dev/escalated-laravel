<?php

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\SsoService;

beforeEach(function () {
    $this->service = new SsoService;
});

describe('JWT Validation', function () {
    it('validates a valid HS256 JWT', function () {
        // Build a valid JWT manually
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'sub' => '1234',
            'email' => 'user@example.com',
            'name' => 'Test User',
            'exp' => time() + 3600,
        ]));
        $secret = 'test-secret-key';
        $signature = base64url_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );
        $token = "$header.$payload.$signature";

        // Configure SSO
        EscalatedSettings::set('sso_jwt_secret', $secret);
        EscalatedSettings::set('sso_jwt_algorithm', 'HS256');
        EscalatedSettings::set('sso_attr_email', 'email');
        EscalatedSettings::set('sso_attr_name', 'name');

        $result = $this->service->validateJwtToken($token);

        expect($result['email'])->toBe('user@example.com');
        expect($result['name'])->toBe('Test User');
        expect($result['claims'])->toBeArray();
    });

    it('rejects a JWT with invalid signature', function () {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'email' => 'user@example.com',
            'exp' => time() + 3600,
        ]));
        $token = "$header.$payload.invalid-signature";

        EscalatedSettings::set('sso_jwt_secret', 'test-secret');
        EscalatedSettings::set('sso_jwt_algorithm', 'HS256');

        $this->service->validateJwtToken($token);
    })->throws(RuntimeException::class, 'signature verification failed');

    it('rejects an expired JWT', function () {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'email' => 'user@example.com',
            'exp' => time() - 3600,
        ]));
        $secret = 'test-secret';
        $signature = base64url_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );
        $token = "$header.$payload.$signature";

        EscalatedSettings::set('sso_jwt_secret', $secret);
        EscalatedSettings::set('sso_jwt_algorithm', 'HS256');

        $this->service->validateJwtToken($token);
    })->throws(RuntimeException::class, 'expired');

    it('rejects a JWT with wrong number of segments', function () {
        EscalatedSettings::set('sso_jwt_secret', 'test');

        $this->service->validateJwtToken('not.a.valid.jwt.token');
    })->throws(RuntimeException::class, 'expected 3 segments');

    it('rejects JWT when no secret is configured', function () {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode(['email' => 'user@test.com']));
        $token = "$header.$payload.sig";

        EscalatedSettings::set('sso_jwt_secret', '');

        $this->service->validateJwtToken($token);
    })->throws(RuntimeException::class, 'not configured');

    it('rejects JWT missing email claim', function () {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'name' => 'No Email',
            'exp' => time() + 3600,
        ]));
        $secret = 'test-secret';
        $signature = base64url_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );
        $token = "$header.$payload.$signature";

        EscalatedSettings::set('sso_jwt_secret', $secret);
        EscalatedSettings::set('sso_jwt_algorithm', 'HS256');
        EscalatedSettings::set('sso_attr_email', 'email');

        $this->service->validateJwtToken($token);
    })->throws(RuntimeException::class, 'missing email');
});

describe('SAML Validation', function () {
    it('rejects invalid base64', function () {
        $this->service->validateSamlAssertion('not-valid-base64!!!');
    })->throws(RuntimeException::class, 'base64 decode failed');

    it('rejects malformed XML', function () {
        $this->service->validateSamlAssertion(base64_encode('not xml'));
    })->throws(RuntimeException::class, 'malformed XML');
});

describe('SSO Config', function () {
    it('returns default config when nothing is set', function () {
        $config = $this->service->getConfig();

        expect($config['sso_provider'])->toBe('none');
        expect($config['sso_jwt_algorithm'])->toBe('HS256');
    });

    it('reports disabled when provider is none', function () {
        expect($this->service->isEnabled())->toBeFalse();
    });

    it('reports enabled when provider is set', function () {
        EscalatedSettings::set('sso_provider', 'saml');
        expect($this->service->isEnabled())->toBeTrue();
    });

    it('saves and retrieves config', function () {
        $this->service->saveConfig([
            'sso_provider' => 'jwt',
            'sso_jwt_secret' => 'my-secret',
        ]);

        $config = $this->service->getConfig();
        expect($config['sso_provider'])->toBe('jwt');
        expect($config['sso_jwt_secret'])->toBe('my-secret');
    });
});

/**
 * Base64url encode helper for tests.
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
