<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeIndexApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('algolia-support.api_enabled')) {
            abort(404);
        }

        $token = (string) config('algolia-support.api_token');
        $given = (string) $request->bearerToken();

        if ($token === '' || $given === '' || ! hash_equals($token, $given)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
