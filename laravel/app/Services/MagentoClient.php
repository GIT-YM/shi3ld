<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;

class MagentoClient
{
    public function request(): PendingRequest
    {
        $token = config('shi3ld.magento.access_token');
        if (! $token) {
            throw new LogicException('Magento API credentials have not been configured.');
        }

        // Mutating requests require explicit idempotency handling; no automatic retries here.
        return Http::baseUrl(rtrim(config('shi3ld.magento.base_url'), '/').'/rest/'.rawurlencode(config('shi3ld.magento.store_code')).'/V1/')
            ->withToken($token)->acceptJson()->connectTimeout(5)->timeout(20);
    }

    public function customer(int $id): array
    {
        return $this->request()->get('customers/'.$id)->throw()->json();
    }
}
