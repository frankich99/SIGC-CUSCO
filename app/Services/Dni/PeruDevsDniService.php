<?php

namespace App\Services\Dni;

use App\Contracts\DniLookupService;
use App\DTOs\DniPerson;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PeruDevsDniService implements DniLookupService
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        $this->apiKey = $this->apiKey ?? config('services.perudevs.key');
        $this->baseUrl = $this->baseUrl ?? config('services.perudevs.base_url', 'https://api.perudevs.com/api/v1');
    }

    /**
     * Consult DNI with PeruDevs API, with caching and validation.
     */
    public function search(string $dni): ?DniPerson
    {
        $cleanDni = trim($dni);

        if (! preg_match('/^\d{8}$/', $cleanDni)) {
            return null;
        }

        if (empty($this->apiKey)) {
            Log::warning('PeruDevs API key is not configured in services.perudevs.key.');

            return null;
        }

        $cacheKey = "dni:perudevs:{$cleanDni}";

        /** @var array<string, mixed>|null $cachedData */
        $cachedData = Cache::get($cacheKey);

        if ($cachedData !== null) {
            return DniPerson::fromPeruDevs($cachedData);
        }

        try {
            $timeout = (int) config('services.perudevs.timeout', 15);

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$this->baseUrl}/dni/complete", [
                    'document' => $cleanDni,
                    'key' => $this->apiKey,
                ]);

            if (! $response->successful()) {
                Log::warning("PeruDevs DNI lookup failed HTTP status {$response->status()} for DNI {$cleanDni}", [
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! is_array($data) || empty($data['estado']) || empty($data['resultado'])) {
                return null;
            }

            $resultado = (array) $data['resultado'];

            // Cache successful lookups for 30 days to save API credits
            Cache::put($cacheKey, $resultado, now()->addDays(30));

            return DniPerson::fromPeruDevs($resultado);
        } catch (Throwable $e) {
            Log::error("PeruDevs DNI service exception for DNI {$cleanDni}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return null;
        }
    }
}
