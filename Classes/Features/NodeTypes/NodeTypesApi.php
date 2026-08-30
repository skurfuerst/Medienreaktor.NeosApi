<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes;

use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeType;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypes;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypeSummary;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypeSummaryWithProperties;
use Medienreaktor\NeosApi\Features\NodeTypes\Response\NodeTypeNotFound;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\IconNameMappingService;
use Neos\OpenApi\Attributes\Operation;

/**
 * Node type schema information - what generic clients (form generators, MCP
 * tools, validators) need to understand the content model.
 *
 * The scope requirement lives in each operation's own security declaration,
 * which is also what publishes it in the OpenAPI document; the account's roles
 * are checked by the Flow policy layer against the matcher for this class.
 */
class NodeTypesApi
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected IconNameMappingService $iconNameMappingService;

    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'contentRepository')]
    protected string $contentRepositoryId;

    /**
     * The node type groups (general, structure, plugins + site-specific ones)
     * that creation UIs group creatable node types by - each entry carries
     * label, position and collapsed.
     *
     * @var array<string, array<string, mixed>>
     */
    #[Flow\InjectConfiguration(package: 'Neos.Neos', path: 'nodeTypes.groups')]
    protected array $nodeTypeGroups;

    #[Operation(
        path: '/api/node-types',
        method: 'GET',
        summary: 'All node types',
        description: 'Node types (including abstract ones) and the configured creation-dialog groups. '
            . '`includeProperties=1` adds each node type\'s merged property and reference declarations '
            . '(name, type, label) to its entry - opt-in because it multiplies the payload and only '
            . 'schema visualizations need it.',
        operationId: 'listNodeTypes',
        security: ['oauth2' => ['neos.read']],
    )]
    public function index(bool $includeProperties = false): NodeTypes
    {
        $nodeTypes = [];
        foreach ($this->nodeTypeManager()->getNodeTypes(true) as $nodeType) {
            $summary = [
                'name' => $nodeType->name->value,
                'abstract' => $nodeType->isAbstract(),
                'superTypes' => array_keys($nodeType->getDeclaredSuperTypes()),
                // untranslated label id / icon name as configured (ui.icon is
                // a Font Awesome name by Neos convention) - what tree UIs
                // need without fetching the full configuration
                'label' => self::text($nodeType->getConfiguration('ui.label')),
                'icon' => $this->normalizeIcon(self::text($nodeType->getConfiguration('ui.icon'))),
                // group + position drive creation UIs: only node types with a
                // group are offered for creation (Neos convention), sorted by
                // position within their group
                'group' => self::text($nodeType->getConfiguration('ui.group')),
                'position' => $nodeType->getConfiguration('ui.position'),
            ];
            if (!$includeProperties) {
                $nodeTypes[] = new NodeTypeSummary(...$summary);
                continue;
            }

            $properties = [];
            foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
                // Underscore-prefixed properties (_hidden, _nodeType) are
                // internal plumbing, not content model.
                if (str_starts_with((string)$propertyName, '_')) {
                    continue;
                }
                $properties[(string)$propertyName] = [
                    'type' => $propertyConfiguration['type'] ?? null,
                    'label' => $propertyConfiguration['ui']['label'] ?? null,
                ];
            }
            $references = [];
            foreach (($nodeType->getConfiguration('references') ?? []) as $referenceName => $referenceConfiguration) {
                $references[(string)$referenceName] = [
                    'label' => $referenceConfiguration['ui']['label'] ?? null,
                    // maxItems 1 marks a singular reference (Neos 9 folds
                    // legacy `type: reference` declarations in this way)
                    'maxItems' => $referenceConfiguration['constraints']['maxItems'] ?? null,
                ];
            }
            $nodeTypes[] = new NodeTypeSummaryWithProperties(...$summary, properties: $properties, references: $references);
        }

        return new NodeTypes(nodeTypes: $nodeTypes, groups: $this->nodeTypeGroups);
    }

    #[Operation(
        path: '/api/node-types/{nodeTypeName}',
        method: 'GET',
        summary: 'One node type with its full configuration',
        description: '`nodeTypeName` is the fully qualified name, e.g. `Neos.Neos:Document`.',
        operationId: 'getNodeType',
        security: ['oauth2' => ['neos.read']],
    )]
    public function show(string $nodeTypeName): NodeType|NodeTypeNotFound
    {
        $nodeType = $this->nodeTypeManager()->getNodeType(NodeTypeName::fromString($nodeTypeName));
        if ($nodeType === null) {
            return new NodeTypeNotFound();
        }

        return new NodeType(
            name: $nodeType->name->value,
            abstract: $nodeType->isAbstract(),
            superTypes: array_keys($nodeType->getDeclaredSuperTypes()),
            configuration: $nodeType->getFullConfiguration(),
        );
    }

    private function nodeTypeManager(): \Neos\ContentRepository\Core\NodeType\NodeTypeManager
    {
        return $this->contentRepositoryRegistry
            ->get(ContentRepositoryId::fromString($this->contentRepositoryId))
            ->getNodeTypeManager();
    }

    /**
     * A configured ui.* value as the document has always described it: a
     * string or nothing. Node type configuration is untyped, and these three
     * members are declared `string | null`, so anything else is treated as
     * absent rather than published as a type no client is prepared for.
     */
    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Normalizes configured ui.icon values to modern Font Awesome classes.
     * Node types configure anything from FA3-era bare names ("picture") over
     * "icon-*" to full "fas fa-*" classes; Neos' own IconNameMappingService
     * holds the legacy mapping but only converts "icon-*" names, so bare
     * legacy names are prefixed first. Full classes (with a space) pass
     * through untouched.
     */
    private function normalizeIcon(?string $icon): ?string
    {
        if ($icon === null || $icon === '' || str_contains($icon, ' ')) {
            return $icon;
        }

        return $this->iconNameMappingService->convert(
            str_starts_with($icon, 'icon-') ? $icon : 'icon-' . $icon
        );
    }
}
