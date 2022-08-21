<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\GrpcClient;

use Google\Protobuf\Internal\Message;
use Hyperf\Grpc\Parser;
use Hyperf\Utils\CodeGen\Package;
use Swoole\Http2\Request as BaseRequest;

class Request extends BaseRequest
{
    private const DEFAULT_CONTENT_TYPE = 'application/grpc+proto';

    /**
     * @var null|bool
     */
    public $usePipelineRead;

    public function __construct(string $path, Message $argument = null, $headers = [])
    {
        $this->method = 'POST';
        $this->headers = array_replace($this->getDefaultHeaders(), $headers);
        $this->path = $path;
        $argument && $this->data = Parser::serializeMessage($argument);
    }

    public function getDefaultHeaders(): array
    {
        return [
            'content-type' => self::DEFAULT_CONTENT_TYPE,
            'te' => 'trailers',
            'user-agent' => $this->buildDefaultUserAgent(),
        ];
    }

    protected function buildDefaultUserAgent(): string
    {
        $userAgent = 'grpc-php-hyperf/1.0';
        $grpcClientVersion = Package::getPrettyVersion('hyperf/grpc-client');
        if ($grpcClientVersion) {
            $explodedVersions = explode('@', $grpcClientVersion);
            $userAgent .= ' (hyperf-grpc-client/' . $explodedVersions[0] . ')';
        }
        return $userAgent;
    }
}
