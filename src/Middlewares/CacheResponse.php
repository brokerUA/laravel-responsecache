<?php

namespace Spatie\ResponseCache\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Spatie\ResponseCache\ResponseCache;
use Spatie\ResponseCache\Events\CacheMissed;
use Symfony\Component\HttpFoundation\Response;
use Spatie\ResponseCache\Events\ResponseCacheHit;

class CacheResponse
{
    /** @var \Spatie\ResponseCache\ResponseCache */
    protected $responseCache;

    public function __construct(ResponseCache $responseCache)
    {
        $this->responseCache = $responseCache;
    }

    public function handle(Request $request, Closure $next, $csrfReplace = null, $lifetimeInMinutes = null): Response
    {
        if ($this->responseCache->enabled($request)) {
            if ($this->responseCache->hasBeenCached($request)) {
                event(new ResponseCacheHit($request));

                $response = $this->responseCache->getCachedResponseFor($request);

                return ($csrfReplace) ? $this->csrf_replace($response) : $response;
            }
        }

        $response = $next($request);

        if ($this->responseCache->enabled($request)) {
            if ($this->responseCache->shouldCache($request, $response)) {
                $this->responseCache->cacheResponse($request, $response, $lifetimeInMinutes);
            }
        }

        event(new CacheMissed($request));

        return $response;
    }
    
    /**
     * @param Response $response
     * @return Response
     */
    protected function csrf_replace(Response $response): Response
    {

        $pattern = '/<input type="hidden" name="_token" value="\w+">/';
        $replacement = '<input type="hidden" name="_token" value="' . csrf_token() . '">';

        $content = preg_replace($pattern, $replacement, $response->getContent());

        return $response->setContent($content);
    }
    
}
