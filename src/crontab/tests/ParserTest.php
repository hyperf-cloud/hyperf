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
namespace HyperfTest\Crontab;

use Carbon\Carbon;
use Hyperf\Crontab\Parser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ParserTest extends TestCase
{
    protected $timezone;

    protected function setUp(): void
    {
        $this->timezone = 'Asia/Shanghai';
    }

    public function testIsValid(): void
    {
        $parser = new Parser();
        $this->assertTrue($parser->isValid('* * * * *'));
        $this->assertTrue($parser->isValid('* * * * * *'));
        $this->assertTrue($parser->isValid('*/11 * * * * *'));
        $this->assertTrue($parser->isValid('10-15/1 * * * * *'));
        $this->assertTrue($parser->isValid('10-12/1,14-15/1 * * * * *'));
        $this->assertTrue($parser->isValid('10,14,,15, * * * * *'));
        $this->assertTrue($parser->isValid('10-15/1 10-12/1 10 * * *'));
    }

    public function testIsInvalid(): void
    {
        $parser = new Parser();
        $this->assertFalse($parser->isValid('* * *'));
        $this->assertFalse($parser->isValid('* * * * * * *'));
    }

    public function testParseSecondLevel()
    {
        $crontabString = '*/11 * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1561052867, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2019-06-21 01:47:00',
            '2019-06-21 01:47:11',
            '2019-06-21 01:47:22',
            '2019-06-21 01:47:33',
            '2019-06-21 01:47:44',
            '2019-06-21 01:47:55',
        ], $this->toDatatime($result));
        /** @var Carbon $last */
        $last = end($result);
        $result = $parser->parse($crontabString, $last->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2019-06-21 01:47:55',
            '2019-06-21 01:48:06',
            '2019-06-21 01:48:17',
            '2019-06-21 01:48:28',
            '2019-06-21 01:48:39',
            '2019-06-21 01:48:50',
        ], $this->toDatatime($result));
    }

    public function testParseSecondLevelBetween(): void
    {
        $crontabString = '10-15/1 * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1591754280, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2020-06-10 09:58:10',
            '2020-06-10 09:58:11',
            '2020-06-10 09:58:12',
            '2020-06-10 09:58:13',
            '2020-06-10 09:58:14',
            '2020-06-10 09:58:15',
        ], $this->toDatatime($result));
    }

    public function testParseSecondLevelForComma(): void
    {
        $crontabString = '10-12/1,14-15/1 * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1591754280, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2020-06-10 09:58:10',
            '2020-06-10 09:58:11',
            '2020-06-10 09:58:12',
            '2020-06-10 09:58:14',
            '2020-06-10 09:58:15',
        ], $this->toDatatime($result));
    }

    public function testParseSecondLevelWithoutBackslash(): void
    {
        $crontabString = '10-12,14-15/1 * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1591754280, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2020-06-10 09:58:10',
            '2020-06-10 09:58:11',
            '2020-06-10 09:58:12',
            '2020-06-10 09:58:14',
            '2020-06-10 09:58:15',
        ], $this->toDatatime($result));
    }

    public function testParseSecondLevelWithEmptyString()
    {
        $crontabString = '10,14,,15, * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1591754280, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2020-06-10 09:58:10',
            '2020-06-10 09:58:14',
            '2020-06-10 09:58:15',
        ], $this->toDatatime($result));
    }

    public function testParseMinuteLevelBetween(): void
    {
        $crontabString = '10-15/1 10-12/1 10 * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1591755010, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([
            '2020-06-10 10:10:10',
            '2020-06-10 10:10:11',
            '2020-06-10 10:10:12',
            '2020-06-10 10:10:13',
            '2020-06-10 10:10:14',
            '2020-06-10 10:10:15',
        ], $this->toDatatime($result));

        $last = end($result);
        $result = $parser->parse($crontabString, $last->addMinute()->startOfMinute(), $this->timezone);
        $this->assertSame([
            '2020-06-10 10:11:10',
            '2020-06-10 10:11:11',
            '2020-06-10 10:11:12',
            '2020-06-10 10:11:13',
            '2020-06-10 10:11:14',
            '2020-06-10 10:11:15',
        ], $this->toDatatime($result));

        $last = end($result);
        $result = $parser->parse($crontabString, $last->addMinute()->startOfMinute(), $this->timezone);

        $this->assertSame([
            '2020-06-10 10:12:10',
            '2020-06-10 10:12:11',
            '2020-06-10 10:12:12',
            '2020-06-10 10:12:13',
            '2020-06-10 10:12:14',
            '2020-06-10 10:12:15',
        ], $this->toDatatime($result));

        $last = end($result);
        $result = $parser->parse($crontabString, $last->addMinute()->startOfMinute(), $this->timezone);

        $this->assertSame([], $this->toDatatime($result));
    }

    public function testParseSecondLevelWithCarbonStartTime()
    {
        $crontabString = '*/11 * * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1561052867, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime, $this->timezone);
        $this->assertSame([
            '2019-06-21 01:47:00',
            '2019-06-21 01:47:11',
            '2019-06-21 01:47:22',
            '2019-06-21 01:47:33',
            '2019-06-21 01:47:44',
            '2019-06-21 01:47:55',
        ], $this->toDatatime($result));
        /** @var Carbon $last */
        $last = end($result);
        $result = $parser->parse($crontabString, $last, $this->timezone);
        $this->assertSame([
            '2019-06-21 01:47:55',
            '2019-06-21 01:48:06',
            '2019-06-21 01:48:17',
            '2019-06-21 01:48:28',
            '2019-06-21 01:48:39',
            '2019-06-21 01:48:50',
        ], $this->toDatatime($result));
    }

    public function testParseMinuteLevel()
    {
        $crontabString = '*/11 * * * *';
        $parser = new Parser();
        $startTime = Carbon::createFromTimestamp(1561052867, $this->timezone)->startOfMinute();
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame([], $this->toDatatime($result));

        $startTime->minute(33);
        $result = $parser->parse($crontabString, $startTime->getTimestamp(), $this->timezone);
        $this->assertSame(['2019-06-21 01:33:00'], $this->toDatatime($result));
    }

    /**
     * @param Carbon[] $result
     * @return string[]
     */
    protected function toDatatime(array $result)
    {
        $dates = [];
        foreach ($result as $date) {
            if (! $date instanceof Carbon) {
                continue;
            }
            $dates[] = $date->toDateTimeString();
        }
        return $dates;
    }
}
