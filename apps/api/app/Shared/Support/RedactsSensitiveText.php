<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Redact secrets out of a message body before persisting it.
 *
 * The raw body is still what we hand to the carrier (SMS/WhatsApp) — this
 * only sanitizes the *stored* / *logged* copy so a compromised database
 * dump or leaked application log doesn't hand an attacker the plaintext
 * passwords we ship in provisioning messages.
 *
 * Match rules cover both the Arabic "كلمة السر:" phrasing used by the
 * welcome/reset-password SMS template and a defensive English fallback
 * ("password:") in case a template is ever added in English. The token
 * following the label runs until the next whitespace character, matching
 * how EmployeeService::generateReadablePassword produces contiguous
 * (no-whitespace) passwords.
 */
trait RedactsSensitiveText
{
    /**
     * Return $body with any embedded password value replaced by [REDACTED].
     * Non-matching bodies are returned unchanged.
     */
    protected function redactBody(string $body): string
    {
        // Arabic: "كلمة السر: <secret>"
        $body = (string) preg_replace('/(كلمة السر:\s*)\S+/u', '$1[REDACTED]', $body);

        // English fallback: "password: <secret>" / "Password:<secret>"
        $body = (string) preg_replace('/(password\s*:\s*)\S+/iu', '$1[REDACTED]', $body);

        return $body;
    }
}
