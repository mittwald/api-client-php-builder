<?php
declare(strict_types=1);

namespace Mittwald\ApiToolsPHP\Utils\Strings;

class StatusTranslator
{
    private const httpCodes = [
        100 => 'Continue',
        101 => 'SwitchingProtocols',
        102 => 'Processing',
        103 => 'Checkpoint',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'NonAuthoritativeInformation',
        204 => 'NoContent',
        205 => 'ResetContent',
        206 => 'PartialContent',
        207 => 'MultiStatus',
        300 => 'MultipleChoices',
        301 => 'MovedPermanently',
        302 => 'Found',
        303 => 'SeeOther',
        304 => 'NotModified',
        305 => 'UseProxy',
        306 => 'SwitchProxy',
        307 => 'TemporaryRedirect',
        400 => 'BadRequest',
        401 => 'Unauthorized',
        402 => 'PaymentRequired',
        403 => 'Forbidden',
        404 => 'NotFound',
        405 => 'MethodNotAllowed',
        406 => 'NotAcceptable',
        407 => 'ProxyAuthenticationRequired',
        408 => 'RequestTimeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'LengthRequired',
        412 => 'PreconditionFailed',
        413 => 'RequestEntityTooLarge',
        414 => 'RequestURITooLong',
        415 => 'UnsupportedMediaType',
        416 => 'RequestedRangeNotSatisfiable',
        417 => 'ExpectationFailed',
        418 => 'ImATeapot',
        422 => 'UnprocessableEntity',
        423 => 'Locked',
        424 => 'FailedDependency',
        425 => 'UnorderedCollection',
        426 => 'UpgradeRequired',
        429 => 'TooManyRequests',
        449 => 'RetryWith',
        450 => 'BlockedByWindowsParentalControls',
        500 => 'InternalServerError',
        501 => 'NotImplemented',
        502 => 'BadGateway',
        503 => 'ServiceUnavailable',
        504 => 'GatewayTimeout',
        505 => 'HTTPVersionNotSupported',
        506 => 'VariantAlsoNegotiates',
        507 => 'InsufficientStorage',
        509 => 'BandwidthLimitExceeded',
        510 => 'NotExtended',
    ];

    public static function statusCodeToText(int $status): string
    {
        if (array_key_exists($status, self::httpCodes)) {
            return self::httpCodes[$status];
        }

        return "Unknown";
    }
}