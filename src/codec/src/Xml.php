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
namespace Hyperf\Codec;

use Hyperf\Codec\Exception\InvalidArgumentException;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Xmlable;
use SimpleXMLElement;

class Xml
{
    public static function toXml(mixed $data, ?SimpleXMLElement $parentNode = null, string $root = 'root')
    {
        if ($data instanceof Xmlable) {
            return (string) $data;
        }
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } else {
            $data = (array) $data;
        }
        if ($parentNode === null) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>' . "<{$root}></{$root}>");
        } else {
            $xml = $parentNode;
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                self::toXml($value, $xml->addChild($key));
            } else {
                if (is_numeric($key)) {
                    $xml->addChild('item' . $key, (string) $value);
                } else {
                    $xml->addChild($key, (string) $value);
                }
            }
        }

        $result = $xml->asXML();
        if ($result === false) {
            $e = new InvalidArgumentException('Syntax error.');
            $e->setOriginal($data);
            throw $e;
        }
        return trim($result);
    }

    public static function toArray($xml): array
    {
        $respObject = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR);

        if ($respObject === false) {
            InvalidArgumentException::throw('Syntax error.', $xml);
        }

        return json_decode(json_encode($respObject), true);
    }
}
