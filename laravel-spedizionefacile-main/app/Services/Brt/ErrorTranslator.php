<?php

namespace App\Services\Brt;

class ErrorTranslator
{
    public function translate(int $code, string $codeDesc, string $message, array $createData): string
    {
        $city = $createData['consigneeCity'] ?? '?';
        $zip = $createData['consigneeZIPCode'] ?? '?';
        $province = $createData['consigneeProvinceAbbreviation'] ?? '?';

        if ($code === -63 || stripos($codeDesc, 'ROUTING') !== false) {
            return "Errore indirizzo BRT: la citta' '{$city}' non corrisponde al CAP '{$zip}' (provincia: {$province}). "
                ."Verificare che citta', CAP e provincia siano corretti e corrispondano tra loro.";
        }

        if ($code === -1 && (stripos($message, 'auth') !== false || stripos($message, 'password') !== false || stripos($message, 'user') !== false)) {
            return 'Errore autenticazione BRT: credenziali non valide. Verificare BRT_CLIENT_ID e BRT_PASSWORD nel file .env.';
        }

        if ($message) {
            return "Errore BRT (code: {$code}, {$codeDesc}): {$message}";
        }

        return "Errore BRT sconosciuto (code: {$code}).";
    }
}
