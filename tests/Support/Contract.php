<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Tests\Support;

/** Reads the vendored server contract in testdata/contract. */
final class Contract
{
    /** @var array<string, mixed>|null */
    private static ?array $openapi = null;

    /** @var array<string, mixed>|null */
    private static ?array $surface = null;

    /** @return array<string, mixed> */
    public static function openapi(): array
    {
        return self::$openapi ??= self::read('openapi.json');
    }

    /** @return array<string, mixed> */
    public static function surface(): array
    {
        return self::$surface ??= self::read('sdk-surface.json');
    }

    /**
     * Every operation: method, path, and its query, header and body parameter names.
     *
     * @return array<string, array{method: string, path: string, query: list<string>, headers: list<string>, body: list<string>}>
     */
    public static function operations(): array
    {
        $out = [];
        foreach (self::openapi()['paths'] as $path => $item) {
            $common = $item['parameters'] ?? [];
            foreach ($item as $method => $op) {
                if ($method === 'parameters') {
                    continue;
                }
                $params = array_map(self::resolve(...), array_merge($common, $op['parameters'] ?? []));
                $body = $op['requestBody']['content']['application/json']['schema'] ?? null;
                if (is_array($body) && isset($body['$ref'])) {
                    $body = self::resolve($body);
                }
                // PHP decodes a numeric-looking JSON key to an int; contract names are strings.
                $out[$op['operationId']] = [
                    'method' => strtoupper($method),
                    'path' => strval($path),
                    'query' => self::names($params, 'query'),
                    'headers' => self::names($params, 'header'),
                    'body' => is_array($body) ? array_map(strval(...), array_keys($body['properties'] ?? [])) : [],
                ];
            }
        }

        return $out;
    }

    /**
     * The schema an operation's success response carries under `data` (a component name, or
     * "inline"), its property names, and whether `data` is a list of it.
     *
     * @return array{schema: string, fields: list<string>, list: bool}|null null when the response has no JSON body
     */
    public static function responseData(string $operationId): ?array
    {
        foreach (self::openapi()['paths'] as $item) {
            foreach ($item as $method => $op) {
                if ($method === 'parameters' || $op['operationId'] !== $operationId) {
                    continue;
                }
                foreach ($op['responses'] as $code => $response) {
                    if (!str_starts_with(strval($code), '2')) {
                        continue;
                    }
                    $response = isset($response['$ref']) ? self::resolve($response) : $response;
                    $schema = $response['content']['application/json']['schema'] ?? null;
                    if (!is_array($schema)) {
                        return null;
                    }
                    $data = self::resolve($schema)['properties']['data'] ?? null;
                    if (!is_array($data)) {
                        return null;
                    }
                    $list = ($data['type'] ?? null) === 'array';
                    $item = $list ? ($data['items'] ?? []) : $data;
                    if (isset($item['$ref'])) {
                        $name = substr(strrchr($item['$ref'], '/') ?: '', 1);

                        return ['schema' => $name, 'fields' => self::properties($name), 'list' => $list];
                    }

                    return ['schema' => 'inline', 'fields' => array_map(strval(...), array_keys($item['properties'] ?? [])), 'list' => $list];
                }
            }
        }

        return null;
    }

    /** @return list<string> a component schema's property names */
    public static function properties(string $schema): array
    {
        $node = self::openapi()['components']['schemas'][$schema] ?? [];

        return array_map(strval(...), array_keys($node['properties'] ?? []));
    }

    /**
     * @param array<array<string, mixed>> $params
     *
     * @return list<string>
     */
    private static function names(array $params, string $in): array
    {
        return array_values(array_map(
            static fn(array $p): string => $p['name'],
            array_filter($params, static fn(array $p): bool => $p['in'] === $in),
        ));
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function resolve(array $node): array
    {
        if (!isset($node['$ref'])) {
            return $node;
        }
        $target = self::openapi();
        foreach (explode('/', substr($node['$ref'], 2)) as $part) {
            $target = $target[$part];
        }

        return $target;
    }

    /** @return array<string, mixed> */
    private static function read(string $file): array
    {
        $raw = file_get_contents(__DIR__ . '/../../testdata/contract/' . $file);
        if ($raw === false) {
            throw new \RuntimeException("read testdata/contract/{$file} (run make vendor-contract)");
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
