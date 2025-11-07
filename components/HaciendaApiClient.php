<?php

namespace app\components;

use RuntimeException;

class HaciendaApiException extends RuntimeException
{
}

class HaciendaApiClient
{
    private const BASE_URL = 'https://api.hacienda.go.cr/fe/ae';

    public function getContribuyente(string $identificacion, float $timeout = 10.0, int $maxRetries = 3): array
    {
        $params = http_build_query(['identificacion' => $identificacion]);
        $lastException = null;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $ch = curl_init();
            if ($ch === false) {
                throw new HaciendaApiException('No se pudo inicializar la consulta HTTP.');
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => self::BASE_URL . '?' . $params,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FAILONERROR => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: FactoEnLaNube/1.0',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($responseBody === false) {
                $lastException = new HaciendaApiException('Error de red al consultar Hacienda: ' . $curlError);
            } else {
                if ($statusCode === 429 && $attempt < $maxRetries - 1) {
                    sleep(2 ** $attempt);
                    continue;
                }

                if ($statusCode >= 200 && $statusCode < 300) {
                    $payload = json_decode($responseBody, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new HaciendaApiException('Respuesta inválida de Hacienda: JSON no válido.');
                    }
                    return $payload;
                }

                $message = sprintf('HTTP %d: %s', $statusCode, $responseBody);
                $lastException = new HaciendaApiException('Error al consultar Hacienda: ' . $message);
                break;
            }

            if ($attempt < $maxRetries - 1) {
                sleep(2 ** $attempt);
            }
        }

        if ($lastException instanceof HaciendaApiException) {
            throw $lastException;
        }

        throw new HaciendaApiException('No se pudo consultar Hacienda por un error desconocido.');
    }
}


