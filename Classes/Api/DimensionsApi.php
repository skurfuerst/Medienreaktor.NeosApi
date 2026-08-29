<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Api;

use Medienreaktor\NeosApi\Api\Dto\ContentDimension;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensions;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensionValue;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensionValues;
use Medienreaktor\NeosApi\Api\Dto\Dimensions;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\OpenApi\Attributes\Operation;

/**
 * Content dimension configuration and the set of allowed dimension space
 * points - clients need this to construct valid node addresses and to offer
 * a dimension switcher.
 *
 * The scope requirement lives in the operation's own security declaration,
 * which is also what publishes it in the OpenAPI document; the account's roles
 * are checked by the Flow policy layer against the matcher for this class.
 */
class DimensionsApi
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected TranslationHelper $translationHelper;

    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'contentRepository')]
    protected string $contentRepositoryId;

    #[Operation(
        path: '/api/dimensions',
        method: 'GET',
        summary: 'The configured content dimensions',
        operationId: 'listDimensions',
        security: ['oauth2' => ['neos.read']],
    )]
    public function index(): Dimensions
    {
        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($this->contentRepositoryId));

        // Dimensions ordered by priority, values in configuration order with
        // specializations directly after their generalization (depth-first),
        // so clients can render the hierarchy from specializationDepth alone.
        $dimensions = [];
        foreach ($contentRepository->getContentDimensionSource()->getContentDimensionsOrderedByPriority() as $dimension) {
            $values = [];
            foreach ($dimension->values->getIterator() as $value) {
                $values[] = new ContentDimensionValue(
                    value: $value->value,
                    label: $this->translateLabel($value->getConfigurationValue('label')) ?? $value->value,
                    specializationDepth: $value->specializationDepth->value,
                );
            }
            $icon = $dimension->getConfigurationValue('icon');
            $dimensions[] = new ContentDimension(
                id: $dimension->id->value,
                label: $this->translateLabel($dimension->getConfigurationValue('label')) ?? $dimension->id->value,
                icon: is_string($icon) ? $icon : null,
                values: new ContentDimensionValues(...$values),
            );
        }

        $allowedDimensionSpacePoints = array_map(
            static fn (DimensionSpacePoint $point) => $point->coordinates,
            iterator_to_array($contentRepository->getVariationGraph()->getDimensionSpacePoints(), false)
        );

        return new Dimensions(
            dimensions: new ContentDimensions(...$dimensions),
            allowedDimensionSpacePoints: array_values($allowedDimensionSpacePoints),
        );
    }

    /**
     * Labels may be plain strings or XLIFF ids ("Vendor.Package:Source:key");
     * the helper resolves the latter and passes plain strings through.
     */
    private function translateLabel(mixed $label): ?string
    {
        if (!is_string($label) || $label === '') {
            return null;
        }

        return $this->translationHelper->translate($label) ?? $label;
    }
}
