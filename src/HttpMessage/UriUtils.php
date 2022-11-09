<?php

namespace bdk\HttpMessage;

use Psr\Http\Message\UriInterface;

/**
 * Uri Utilities
 */
class UriUtils
{
    /**
     * Converts the relative URI into a new URI that is resolved against the base URI.
     *
     * @param UriInterface $base Base URI
     * @param UriInterface $rel  Relative URI
     *
     * @return UriInterface
     *
     * @link http://tools.ietf.org/html/rfc3986#section-5.2
     */
    public static function resolve(UriInterface $base, UriInterface $rel)
    {
        if ((string) $rel === '') {
            // we can simply return the same base URI instance for this same-document reference
            return $base;
        }
        if ($rel->getScheme() !== '') {
            // rel specified scheme... return rel (with path cleaned up)
            return $rel
                ->withPath(self::pathRemoveDots($rel->getPath()));
        }
        if ($rel->getAuthority() !== '') {
            // rel specified "authority"..
            //   return base's scheee, rel's everything else (with path cleaned up)
            return $rel
                ->withScheme($base->getScheme())
                ->withPath(self::pathRemoveDots($rel->getPath()));
        }
        if ($rel->getPath() === '') {
            $targetQuery = $rel->getQuery() !== ''
                ? $rel->getQuery()
                : $base->getQuery();
            return $base
                ->withQuery($targetQuery)
                ->withFragment($rel->getFragment());
        }
        $relPath = $rel->getPath();
        $lastSlashPos = \strrpos($base->getPath(), '/');
        $targetPath = $lastSlashPos === false
            ? $relPath
            : \substr($base->getPath(), 0, $lastSlashPos + 1) . $relPath;
        if ($relPath[0] === '/') {
            // \bdk\Test\Debug\Helper::stderr('rel path slash');
            $targetPath = $relPath;
        } elseif ($base->getAuthority() !== '' && $base->getPath() === '') {
            $targetPath = '/' . $relPath;
        }
        return $base
            ->withPath(self::pathRemoveDots($targetPath))
            ->withQuery($rel->getQuery())
            ->withFragment($rel->getFragment());
    }

    /**
     * Determines two Uri's should be considered cross-origin
     *
     * @param UriInterface $uri1 Uri 1
     * @param UriInterface $uri2 Uri2
     *
     * @return bool
     */
    public static function isCrossOrigin(UriInterface $uri1, UriInterface $uri2)
    {
        if (\strcasecmp($uri1->getHost(), $uri2->getHost()) !== 0) {
            return true;
        }

        if ($uri1->getScheme() !== $uri2->getScheme()) {
            return true;
        }

        return self::computePort($uri1) !== self::computePort($uri2);
    }

    /**
     * @param UriInterface $uri Uri instance
     *
     * @return int
     */
    private static function computePort(UriInterface $uri)
    {
        $port = $uri->getPort();
        if ($port !== null) {
            return $port;
        }
        return $uri->getScheme() === 'https' ? 443 : 80;
    }

    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @param string $path Path component
     *
     * @return string
     *
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    private static function pathRemoveDots($path)
    {
        if ($path === '') { //  || $path === '/'
            return $path;
        }

        $segments = \explode('/', $path);
        $segmentsNew = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                \array_pop($segmentsNew);
            } elseif ($segment !== '.') {
                $segmentsNew[] = $segment;
            }
        }

        $pathNew = \implode('/', $segmentsNew);

        if ($path[0] === '/' && (!isset($pathNew[0]) || $pathNew[0] !== '/')) {
            // Re-add the leading slash if necessary for cases like "/.."
            $pathNew = '/' . $pathNew;
        } elseif ($pathNew !== '' && \in_array($segment, array('.','..'), true)) {
            // Add the trailing slash if necessary
            // If pathNew is not empty, then $segment must be set and is the last segment from the foreach
            $pathNew .= '/';
        }

        return $pathNew;
    }
}
