<?php

namespace Tests\Fakes;

use App\Media\HostResolver;

/** DNS in tests: the names a test gives, a public address for the rest. */
class FakeHostResolver extends HostResolver
{
    /** @param  array<string, list<string>>  $hosts */
    public function __construct(public array $hosts = []) {}

    public function ips(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return $this->hosts[$host] ?? ['93.184.216.34'];
    }
}
