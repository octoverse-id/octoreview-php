<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * Error codes the server sends (docs/errors.md). The contract gate checks this set against the
 * vendored contract's ErrorCode enum, in both directions.
 */
final class ErrorCode
{
    public const VALIDATION_ERROR = 'validation_error';
    public const INVALID_NAMESPACE = 'invalid_namespace';
    public const APPLICATION_MISMATCH = 'application_mismatch';
    public const MEDIA_TOO_LARGE = 'media_too_large';
    public const CONTENT_TYPE_NOT_ALLOWED = 'content_type_not_allowed';
    public const AUTHENTICATION_REQUIRED = 'authentication_required';
    public const INVALID_API_KEY = 'invalid_api_key';
    public const PERMISSION_DENIED = 'permission_denied';
    public const UPLOAD_TOKEN_INVALID = 'upload_token_invalid';
    public const MEDIA_TOKEN_INVALID = 'media_token_invalid';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const NOT_ACCEPTABLE = 'not_acceptable';
    public const REVIEW_EXISTS = 'review_exists';
    public const ENTITY_INACTIVE = 'entity_inactive';
    public const INVALID_TRANSITION = 'invalid_transition';
    public const REVIEW_CHANGED = 'review_changed';
    public const REVISION_SUPERSEDED = 'revision_superseded';
    public const REVISION_STALE = 'revision_stale';
    public const SOURCE_REF_IMMUTABLE = 'source_ref_immutable';
    public const REVIEW_NOT_ACCEPTING_MEDIA = 'review_not_accepting_media';
    public const MEDIA_LIMIT_EXCEEDED = 'media_limit_exceeded';
    public const STORAGE_QUOTA_EXCEEDED = 'storage_quota_exceeded';
    public const UPLOAD_EXPIRED = 'upload_expired';
    public const UPLOAD_NOT_FOUND = 'upload_not_found';
    public const UPLOAD_CHANGED = 'upload_changed';
    public const COMPLETE_IN_PROGRESS = 'complete_in_progress';
    public const UPLOAD_ALREADY_COMPLETED = 'upload_already_completed';
    public const UPLOAD_TOKEN_EXPIRED = 'upload_token_expired';
    public const MEDIA_TOKEN_EXPIRED = 'media_token_expired';
    public const CONTENT_LENGTH_REQUIRED = 'content_length_required';
    public const UPLOAD_TOO_LARGE = 'upload_too_large';
    public const UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';
    public const RANGE_NOT_SATISFIABLE = 'range_not_satisfiable';
    public const MEDIA_REJECTED = 'media_rejected';
    public const INTERNAL_ERROR = 'internal_error';
    public const STORAGE_UNAVAILABLE = 'storage_unavailable';
    public const STORAGE_CREDENTIALS_EXPIRING = 'storage_credentials_expiring';
    public const TOO_MANY_COMPLETIONS = 'too_many_completions';

    /** Every code above. */
    public const ALL = [
        self::VALIDATION_ERROR,
        self::INVALID_NAMESPACE,
        self::APPLICATION_MISMATCH,
        self::MEDIA_TOO_LARGE,
        self::CONTENT_TYPE_NOT_ALLOWED,
        self::AUTHENTICATION_REQUIRED,
        self::INVALID_API_KEY,
        self::PERMISSION_DENIED,
        self::UPLOAD_TOKEN_INVALID,
        self::MEDIA_TOKEN_INVALID,
        self::NOT_FOUND,
        self::METHOD_NOT_ALLOWED,
        self::NOT_ACCEPTABLE,
        self::REVIEW_EXISTS,
        self::ENTITY_INACTIVE,
        self::INVALID_TRANSITION,
        self::REVIEW_CHANGED,
        self::REVISION_SUPERSEDED,
        self::REVISION_STALE,
        self::SOURCE_REF_IMMUTABLE,
        self::REVIEW_NOT_ACCEPTING_MEDIA,
        self::MEDIA_LIMIT_EXCEEDED,
        self::STORAGE_QUOTA_EXCEEDED,
        self::UPLOAD_EXPIRED,
        self::UPLOAD_NOT_FOUND,
        self::UPLOAD_CHANGED,
        self::COMPLETE_IN_PROGRESS,
        self::UPLOAD_ALREADY_COMPLETED,
        self::UPLOAD_TOKEN_EXPIRED,
        self::MEDIA_TOKEN_EXPIRED,
        self::CONTENT_LENGTH_REQUIRED,
        self::UPLOAD_TOO_LARGE,
        self::UNSUPPORTED_MEDIA_TYPE,
        self::RANGE_NOT_SATISFIABLE,
        self::MEDIA_REJECTED,
        self::INTERNAL_ERROR,
        self::STORAGE_UNAVAILABLE,
        self::STORAGE_CREDENTIALS_EXPIRING,
        self::TOO_MANY_COMPLETIONS,
    ];
}
