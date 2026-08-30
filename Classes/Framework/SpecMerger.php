<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\Flow\Annotations as Flow;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Problem\ProblemDocument;

/**
 * Puts the generated half of the API into the hand-maintained document.
 *
 * The API is migrating from hand-written controllers to attribute-annotated
 * Api classes one feature at a time, so for a while both exist. The YAML file
 * stays the document's spine - info, servers, security, security schemes and
 * tag order are all its - and every migrated operation is merged into it. Each
 * migration step deletes paths from the YAML and the generated document grows
 * by the same operations, so what is served never changes shape.
 *
 * One rewrite happens on the way in: the library documents its own generated
 * failures as RFC 9457 problem documents, while LegacyErrorTranslator turns
 * those into this API's {"error", "error_description"} envelope on the wire.
 * The document has to say what actually goes out, so those responses are
 * rewritten to the envelope here. Both sides of that bridge disappear together
 * when the API adopts problem+json.
 */
#[Flow\Scope('singleton')]
final class SpecMerger
{
    private const ERROR_SCHEMA_REF = ['$ref' => '#/components/schemas/Error'];
    private const LEGACY_ERROR_SCHEMA_REF = '#/components/schemas/LegacyError';
    private const RESPONSE_REFS = [
        401 => ['$ref' => '#/components/responses/Unauthorized'],
        403 => ['$ref' => '#/components/responses/Forbidden'],
    ];

    /**
     * @param array<string, mixed> $spec the parsed hand-maintained document
     * @return array<string, mixed>
     */
    public function merge(array $spec, CompiledApi $compiled): array
    {
        /** @var array<string, mixed> $generated */
        $generated = json_decode(
            (string)json_encode($compiled->document, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ((array)($generated['paths'] ?? []) as $path => $operations) {
            foreach ((array)$operations as $method => $operation) {
                $spec['paths'][$path][$method] = is_array($operation)
                    ? $this->withLegacyErrors(self::withoutLegacyErrorSchema($operation))
                    : $operation;
            }
        }

        foreach ((array)($generated['components']['schemas'] ?? []) as $name => $schema) {
            $spec['components']['schemas'][$name] = $schema;
        }

        // The YAML's tag list is the sidebar's running order, so a generated
        // tag is only appended when it names something new.
        $known = array_column((array)($spec['tags'] ?? []), 'name');
        foreach ((array)($generated['tags'] ?? []) as $tag) {
            if (!in_array($tag['name'] ?? null, $known, true)) {
                $spec['tags'][] = $tag;
            }
        }

        return $this->withoutUnreferencedSchemas($spec, array_keys((array)($generated['components']['schemas'] ?? [])));
    }

    /**
     * Drops generated schemas nothing points at any more.
     *
     * Rewriting the problem+json responses above orphans the schema that
     * described them, and a component no operation reaches is noise in a
     * published document (redocly says so out loud). Only generated names are
     * considered - what the hand-maintained document keeps around is its own
     * business.
     *
     * @param array<string, mixed> $spec
     * @param array<string> $generatedNames
     * @return array<string, mixed>
     */
    private function withoutUnreferencedSchemas(array $spec, array $generatedNames): array
    {
        foreach ($generatedNames as $name) {
            $encoded = (string)json_encode($spec, JSON_UNESCAPED_SLASHES);
            if (!str_contains($encoded, sprintf('"#/components/schemas/%s"', $name))) {
                unset($spec['components']['schemas'][$name]);
            }
        }

        return $spec;
    }

    /**
     * Points every reference to the LegacyError DTO at the document's own
     * `Error` schema.
     *
     * An operation that answers a deliberate failure - a missing node type -
     * returns it as a body type of its own, which compiles to a second schema
     * describing the envelope the YAML already describes as `Error`. Two names
     * for one type would be a worse document, so the generated one is folded
     * into the hand-maintained one here (and then dropped, unreferenced, by
     * the sweep below). Like every other rewrite in this class, it goes away
     * when the API adopts problem+json.
     *
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private static function withoutLegacyErrorSchema(array $operation): array
    {
        array_walk_recursive($operation, static function (mixed &$value, string|int $key): void {
            if ($key === '$ref' && $value === self::LEGACY_ERROR_SCHEMA_REF) {
                $value = self::ERROR_SCHEMA_REF['$ref'];
            }
        });

        return $operation;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function withLegacyErrors(array $operation): array
    {
        // Every secured operation can answer 403 - the token may lack the
        // scope, or the account's roles may not grant the endpoint - but
        // neither is visible to the compiler: one is enforced by the auth
        // context provider, the other by the Flow policy layer. The
        // hand-maintained document has always declared it, so it stays.
        if (($operation['security'] ?? []) !== []) {
            $operation['responses'][403] = self::RESPONSE_REFS[403];
        }

        foreach ((array)($operation['responses'] ?? []) as $status => $response) {
            if (!is_array($response) || !isset($response['content'][ProblemDocument::CONTENT_TYPE])) {
                continue;
            }
            $operation['responses'][$status] = self::RESPONSE_REFS[(int)$status] ?? [
                'description' => $response['description'] ?? '',
                'content' => ['application/json' => ['schema' => self::ERROR_SCHEMA_REF]],
            ];
        }

        return $operation;
    }
}
