<?php

namespace App\Support;

/**
 * Parsea celdas del Excel "Mails Clientes" (facturas / cobranzas, con o sin bloques SUC).
 */
class ClienteEmailsExcelParser
{
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    /**
     * @return list<array{etiqueta_suc: ?string, emails: list<string>}>
     */
    public function parseCell(?string $raw): array
    {
        $text = $this->limpiarTexto($raw);
        if ($text === '') {
            return [];
        }

        if ($this->esSoloPortal($text)) {
            return [];
        }

        if (preg_match('/\bSUC\b/i', $text)) {
            return $this->parseConSucursales($text);
        }

        return [
            [
                'etiqueta_suc' => null,
                'emails' => $this->extraerEmails($text),
            ],
        ];
    }

    public function extraerEmails(string $text): array
    {
        if (!preg_match_all(self::EMAIL_PATTERN, $text, $matches)) {
            return [];
        }

        $emails = [];
        foreach ($matches[0] as $email) {
            $email = strtolower(trim(str_replace('\\_', '_', $email)));
            if ($email !== '' && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * @return list<array{etiqueta_suc: ?string, emails: list<string>}>
     */
    private function parseConSucursales(string $text): array
    {
        $partes = preg_split('/(?=\bSUC\s+)/i', $text) ?: [];
        $bloques = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === '' || !preg_match('/^SUC\s+/i', $parte)) {
                continue;
            }

            $parte = preg_replace('/^SUC\s+/i', '', $parte);
            if (!preg_match(self::EMAIL_PATTERN, $parte, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $posEmail = $match[0][1];
            $etiqueta = trim(substr($parte, 0, $posEmail));
            $etiqueta = rtrim($etiqueta, " ,;\t");
            $resto = substr($parte, $posEmail);
            $emails = $this->extraerEmails($resto);

            if ($etiqueta === '' || $emails === []) {
                continue;
            }

            $bloques[] = [
                'etiqueta_suc' => $etiqueta,
                'emails' => $emails,
            ];
        }

        return $bloques;
    }

    private function limpiarTexto(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $text = str_replace('\\_', '_', $text);

        return trim(preg_replace('/[ \t]+/u', ' ', $text) ?? '');
    }

    private function esSoloPortal(string $text): bool
    {
        $sinPortal = trim(preg_replace('/\bPORTAL\b/i', '', $text) ?? '');

        return $sinPortal === '' || !preg_match(self::EMAIL_PATTERN, $sinPortal);
    }
}
