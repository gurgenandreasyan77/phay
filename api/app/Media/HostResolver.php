<?php

namespace App\Media;

/** The addresses a host name points to (tests answer for the network). */
class HostResolver
{
    /** @return list<string> IPv4 first, then IPv6 */
    public function ips(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $v4 = gethostbynamel($host) ?: [];
        $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$v4, ...$v6]));
    }
}
