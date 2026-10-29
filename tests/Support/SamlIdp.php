<?php

namespace Escalated\Laravel\Tests\Support;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * A throwaway SAML identity provider for tests: generates its own key and
 * self-signed certificate at runtime and builds (optionally signed) SAML 2.0
 * responses, so the tests exercise the real signature checks.
 */
final class SamlIdp
{
    public string $privateKey;

    public string $certificate;

    public function __construct(public string $entityId = 'https://idp.example.test/metadata')
    {
        [$this->privateKey, $this->certificate] = self::keyPair();
    }

    /** @return array{0: string, 1: string} PEM private key, PEM certificate */
    public static function keyPair(): array
    {
        // A minimal openssl.cnf, since some PHP builds (Windows) ship none.
        $cnf = tempnam(sys_get_temp_dir(), 'esc-ossl');
        file_put_contents($cnf, '[req]
distinguished_name = req_dn
[req_dn]
');
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256', 'config' => $cnf];
        $key = openssl_pkey_new($config);
        $csr = openssl_csr_sign(openssl_csr_new(['commonName' => 'idp.example.test'], $key, $config), null, $key, 365, $config);
        openssl_pkey_export($key, $privatePem, null, $config);
        openssl_x509_export($csr, $certPem);
        @unlink($cnf);

        return [$privatePem, $certPem];
    }

    /**
     * @param  array{
     *     destination?: string|null, recipient?: string|null, audience?: string|null,
     *     in_response_to?: string|null, issuer?: string, name_id?: string,
     *     attributes?: array<string, string>, not_on_or_after?: int, not_before?: int,
     *     assertion_id?: string, sign_assertion?: bool, sign_response?: bool, signing_key?: string|null,
     * }  $o
     */
    public function response(string $acs, string $audience, array $o = []): string
    {
        $now = time();
        $notBefore = gmdate('Y-m-d\TH:i:s\Z', $o['not_before'] ?? $now - 60);
        $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', $o['not_on_or_after'] ?? $now + 300);
        $instant = gmdate('Y-m-d\TH:i:s\Z', $now);
        $responseId = '_r'.bin2hex(random_bytes(16));
        $assertionId = $o['assertion_id'] ?? '_a'.bin2hex(random_bytes(16));
        $issuer = htmlspecialchars($o['issuer'] ?? $this->entityId);
        $destination = array_key_exists('destination', $o) ? $o['destination'] : $acs;
        $recipient = array_key_exists('recipient', $o) ? $o['recipient'] : $acs;
        $aud = array_key_exists('audience', $o) ? $o['audience'] : $audience;
        $irt = $o['in_response_to'] ?? null;
        $nameId = htmlspecialchars($o['name_id'] ?? 'person@example.com');

        $irtAttr = $irt !== null ? ' InResponseTo="'.htmlspecialchars($irt).'"' : '';
        $destAttr = $destination !== null ? ' Destination="'.htmlspecialchars($destination).'"' : '';
        $recipientAttr = $recipient !== null ? ' Recipient="'.htmlspecialchars($recipient).'"' : '';
        $audienceXml = $aud !== null
            ? '<saml:AudienceRestriction><saml:Audience>'.htmlspecialchars($aud).'</saml:Audience></saml:AudienceRestriction>'
            : '';

        $attrs = '';
        foreach ($o['attributes'] ?? [] as $name => $value) {
            $attrs .= '<saml:Attribute Name="'.htmlspecialchars($name).'"><saml:AttributeValue>'.htmlspecialchars($value).'</saml:AttributeValue></saml:Attribute>';
        }
        $attrStatement = $attrs !== '' ? '<saml:AttributeStatement>'.$attrs.'</saml:AttributeStatement>' : '';

        $assertion = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="'.$assertionId.'" Version="2.0" IssueInstant="'.$instant.'">'
            .'<saml:Issuer>'.$issuer.'</saml:Issuer>'
            .'<saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$nameId.'</saml:NameID>'
            .'<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="'.$notOnOrAfter.'"'.$recipientAttr.$irtAttr.'/></saml:SubjectConfirmation></saml:Subject>'
            .'<saml:Conditions NotBefore="'.$notBefore.'" NotOnOrAfter="'.$notOnOrAfter.'">'.$audienceXml.'</saml:Conditions>'
            .'<saml:AuthnStatement AuthnInstant="'.$instant.'" SessionIndex="'.$assertionId.'"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>'
            .$attrStatement
            .'</saml:Assertion>';

        $xml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$responseId.'" Version="2.0" IssueInstant="'.$instant.'"'.$destAttr.$irtAttr.'>'
            .'<saml:Issuer>'.$issuer.'</saml:Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .$assertion
            .'</samlp:Response>';

        $doc = new \DOMDocument;
        $doc->loadXML($xml);
        $key = $o['signing_key'] ?? $this->privateKey;

        if ($o['sign_assertion'] ?? true) {
            $node = $doc->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
            self::sign($node, $key, $this->certificate);
        }

        if ($o['sign_response'] ?? false) {
            self::sign($doc->documentElement, $key, $this->certificate);
        }

        return $doc->saveXML();
    }

    /** Enveloped RSA-SHA256 signature placed after the Issuer, as SAML requires. */
    public static function sign(\DOMElement $node, string $privateKey, string $certificate): void
    {
        $dsig = new XMLSecurityDSig;
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference(
            $node,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
            ['id_name' => 'ID', 'overwrite' => false],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey);
        $dsig->sign($key);
        $dsig->add509Cert($certificate);

        $issuer = null;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'Issuer') {
                $issuer = $child;
                break;
            }
        }
        $dsig->insertSignature($node, $issuer?->nextSibling);
    }
}
