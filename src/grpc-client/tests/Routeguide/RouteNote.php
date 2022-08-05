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
# source: route_guide.proto

namespace Routeguide;

use Google\Protobuf\Internal\GPBUtil;

/**
 * A RouteNote is a message sent while at a given point.
 *
 * Generated from protobuf message <code>routeguide.RouteNote</code>
 */
class RouteNote extends \Google\Protobuf\Internal\Message
{
    /**
     * The location from which the message is sent.
     *
     * Generated from protobuf field <code>.routeguide.Point location = 1;</code>
     */
    private $location;

    /**
     * The message to be sent.
     *
     * Generated from protobuf field <code>string message = 2;</code>
     */
    private string $message = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *                    Optional. Data for populating the Message object.
     *
     *     @var \Routeguide\Point $location
     *           The location from which the message is sent
     *     @var string $message
     *           The message to be sent.
     * }
     */
    public function __construct($data = null)
    {
        \GPBMetadata\RouteGuide::initOnce();
        parent::__construct($data);
    }

    /**
     * The location from which the message is sent.
     *
     * Generated from protobuf field <code>.routeguide.Point location = 1;</code>
     * @return \Routeguide\Point
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * The location from which the message is sent.
     *
     * Generated from protobuf field <code>.routeguide.Point location = 1;</code>
     * @param \Routeguide\Point $var
     * @return $this
     */
    public function setLocation($var)
    {
        GPBUtil::checkMessage($var, \Routeguide\Point::class);
        $this->location = $var;

        return $this;
    }

    /**
     * The message to be sent.
     *
     * Generated from protobuf field <code>string message = 2;</code>
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * The message to be sent.
     *
     * Generated from protobuf field <code>string message = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setMessage($var)
    {
        GPBUtil::checkString($var, true);
        $this->message = $var;

        return $this;
    }
}
