<?php

namespace AtxDigital\Ticketing\Support;

final class Url
{
    /**
     * @param  array<string, string>  $params
     */
    public static function appendQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }
}
