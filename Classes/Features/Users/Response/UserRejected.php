<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 400 for everything a well-formed body can still get wrong: a value the
 * schema cannot judge (an unknown or abstract role, a malformed address, an
 * empty name once trimmed) and the three self-lockout guards.
 *
 * One class rather than one per code, because the status, the body shape and
 * the reason are identical - only the code differs, and the code is in the
 * body where a client reads it. The named constructors are the enumeration:
 * they are what keeps the wording of a decade-old contract in one place.
 */
final readonly class UserRejected implements ApiResponse
{
    private function __construct(
        private string $error,
        private string $description,
    ) {
    }

    public static function invalidUsername(): self
    {
        return new self('invalid_username', 'The username must not be empty.');
    }

    public static function invalidPassword(): self
    {
        return new self('invalid_password', 'The password must not be empty.');
    }

    public static function invalidName(): self
    {
        return new self('invalid_name', 'First name and last name must not be empty.');
    }

    public static function invalidEmail(): self
    {
        return new self('invalid_email', 'The given email address is not valid.');
    }

    public static function rolesNotAList(): self
    {
        return new self('invalid_role', 'Roles must be given as a list of role identifiers.');
    }

    public static function unknownRole(string $roleIdentifier): self
    {
        return new self('invalid_role', sprintf('The role "%s" does not exist.', $roleIdentifier));
    }

    public static function abstractRole(string $roleIdentifier): self
    {
        return new self('invalid_role', sprintf('The role "%s" is abstract and cannot be assigned.', $roleIdentifier));
    }

    public static function cannotDemoteSelf(): self
    {
        return new self('cannot_demote_self', 'You cannot remove your own Administrator role.');
    }

    public static function cannotDeactivateSelf(): self
    {
        return new self('cannot_deactivate_self', 'You cannot deactivate your own user.');
    }

    public static function cannotDeleteSelf(): self
    {
        return new self('cannot_delete_self', 'You cannot delete your own user.');
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(400);
    }

    public static function description(): string
    {
        return '`invalid_username`, `invalid_password`, `invalid_name`, `invalid_email`, `invalid_role`, '
            . '`cannot_demote_self`, `cannot_deactivate_self` or `cannot_delete_self` - which one is in `error`.';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(LegacyError::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public function body(): LegacyError
    {
        return new LegacyError($this->error, $this->description);
    }
}
