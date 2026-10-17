<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');

        if (! $this->isSupported($locale)) {
            $locale = $this->fromAcceptLanguage($request) ?? config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }

    private function isSupported(?string $locale): bool
    {
        return $locale !== null && Locale::tryFrom($locale) !== null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $candidate = strtolower(substr($language, 0, 2));

            if ($this->isSupported($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
