<?php
declare(strict_types=1);

namespace Mittwald\ApiToolsPHP\Generator;

readonly class OperationResponse
{
    public function __construct(public string $type, public int|string $statusCode, public string $builderExpr, public ?string $comment = null)
    {
    }

    /**
     * @param OperationResponse[] $responses
     * @return ?OperationResponse
     */
    public static function getSuccessfulResponse(array $responses): ?OperationResponse
    {
        foreach ($responses as $response) {
            if ($response->statusCode >= 200 && $response->statusCode < 300) {
                return $response;
            }
        }

        foreach ($responses as $response) {
            if ($response->statusCode === "default") {
                return $response;
            }
        }

        return null;
    }

    /**
     * @param OperationResponse[] $responses
     * @return OperationResponse[]
     */
    public static function getUnsuccessfulResponses(array $responses): array
    {
        $out = [];
        $success = self::getSuccessfulResponse($responses);

        foreach ($responses as $response) {
            if ($response !== $success) {
                $out[] = $response;
            }
        }

        return $out;
    }
}