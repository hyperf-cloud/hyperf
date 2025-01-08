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

namespace HyperfTest\Stringable;

use Hyperf\Stringable\Str;
use Hyperf\Stringable\Stringable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class StringableTest extends TestCase
{
    public function testCharAt()
    {
        $this->assertEquals('р', $this->stringable('Привет, мир!')->charAt(1));
        $this->assertEquals('ち', $this->stringable('「こんにちは世界」')->charAt(4));
        $this->assertEquals('w', $this->stringable('Привет, world!')->charAt(8));
        $this->assertEquals('界', $this->stringable('「こんにちは世界」')->charAt(-2));
        $this->assertEquals(null, $this->stringable('「こんにちは世界」')->charAt(-200));
        $this->assertEquals(null, $this->stringable('Привет, мир!')->charAt('Привет, мир!', 100));
    }

    public function testExactly()
    {
        $this->assertTrue($this->stringable('foo')->exactly($this->stringable('foo')));
        $this->assertTrue($this->stringable('foo')->exactly('foo'));

        $this->assertFalse($this->stringable('Foo')->exactly($this->stringable('foo')));
        $this->assertFalse($this->stringable('Foo')->exactly('foo'));
        $this->assertFalse($this->stringable('[]')->exactly([]));
        $this->assertFalse($this->stringable('0')->exactly(0));
    }

    public function testSlug()
    {
        $str = $this->stringable('hyperf_')->slug('_')->__toString();

        $this->assertSame('hyperf', $str);
    }

    public function testMask()
    {
        $str = $this->stringable('hyperf');
        $this->assertSame('******', $str->mask()->__toString());
        $this->assertSame('hyp***', $str->mask(3)->__toString());
        $this->assertSame('hyp*rf', $str->mask(3, 1)->__toString());
        $this->assertSame('***erf', $str->mask(0, 3)->__toString());
        $this->assertSame('------', $str->mask(0, 0, '-')->__toString());
        $this->assertSame('hyperf', $str->mask(6, 2)->__toString());
        $this->assertSame('hyperf', $str->mask(7)->__toString());
        $this->assertSame('hyp**********', $str->mask(3, 10)->__toString());
        $this->assertSame('***erf', $str->mask(-3)->__toString());
        $this->assertSame('hy*erf', $str->mask(-3, 1)->__toString());
        $this->assertSame('***erf', $str->mask(-3, 3)->__toString());
        $this->assertSame('*****erf', $str->mask(-3, 5)->__toString());

        $str = $this->stringable('你好啊');
        $this->assertSame('***', $str->mask()->__toString());

        $str = $this->stringable('你好世界');
        $this->assertSame('你好世*', $str->mask(3)->__toString());
        $this->assertSame('你好*界', $str->mask(2, 1)->__toString());
        $this->assertSame('***界', $str->mask(0, 3)->__toString());
        $this->assertSame('你*世界', $str->mask(1, 1)->__toString());
        $this->assertSame('----', $str->mask(0, 0, '-')->__toString());
        $this->assertSame('你好世界', $str->mask(6, 2)->__toString());
        $this->assertSame('你好世界', $str->mask(7)->__toString());
        $this->assertSame('你好世**********', $str->mask(3, 10)->__toString());
        $this->assertSame('***界', $str->mask(-1)->__toString());
        $this->assertSame('你好*界', $str->mask(-1, 1)->__toString());
        $this->assertSame('***好世界', $str->mask(-3, 3)->__toString());

        $this->expectException(InvalidArgumentException::class);
        $this->stringable('hyperf')->mask(-1, -1);
    }

    public function testStartsWith()
    {
        $this->assertFalse($this->stringable('hyperf.wiki')->startsWith('http://'));
        $this->assertFalse($this->stringable('hyperf.wiki')->startsWith(['http://', 'https://']));
        $this->assertTrue($this->stringable('http://www.hyperf.io')->startsWith('http://'));
        $this->assertTrue($this->stringable('https://www.hyperf.io')->startsWith(['http://', 'https://']));
    }

    public function testStripTags()
    {
        $this->assertSame('beforeafter', (string) $this->stringable('before<br>after')->stripTags());
        $this->assertSame('before<br>after', (string) $this->stringable('before<br>after')->stripTags('<br>'));
        $this->assertSame('before<br>after', (string) $this->stringable('<strong>before</strong><br>after')->stripTags('<br>'));
        $this->assertSame('<strong>before</strong><br>after', (string) $this->stringable('<strong>before</strong><br>after')->stripTags('<br><strong>'));
        $this->assertSame('<strong>before</strong><br>after', (string) $this->stringable('<strong>before</strong><br>after')->stripTags(['<br>', '<strong>']));
        $this->assertSame('beforeafter', (string) $this->stringable('before<br>after')->stripTags(null));
    }

    public function testWhenEmptyAndNot()
    {
        $empty = $this->stringable('');
        $this->assertTrue($empty->whenEmpty(function ($str, $value) {
            $this->assertTrue($value);
            return true;
        }));

        $this->assertSame($empty, $empty->whenEmpty(function ($str, $value) {
            $this->assertTrue($value);
            return null;
        }, function () {
            return true;
        }));

        $notEmpty = $this->stringable('123');
        $this->assertTrue($notEmpty->whenEmpty(function ($str, $value) {
            $this->assertFalse($value);
            return false;
        }, function () {
            return true;
        }));
    }

    public function testWhenAndUnless()
    {
        $str = $this->stringable('Hyperf is the best PHP framework');

        $this->assertSame('Hyperf is the best PHP framework!!!', $str->when(fn ($str) => $str->contains('best'), function ($str) {
            return $str->append('!!!');
        })->__toString());
        $this->assertSame('Hyperf is the best PHP framework!!!', $str->unless(fn ($str) => $str->contains('!!!'), function ($str) {
            return $str->append('!!!');
        })->__toString());
    }

    public function testArrayAccess()
    {
        $str = $this->stringable('my string');
        $this->assertSame('m', $str[0]);
        $this->assertSame('t', $str[4]);
        $this->assertTrue(isset($str[2]));
        $this->assertFalse(isset($str[10]));

        $str[0] = 'M';
        $this->assertSame('My string', (string) $str);
    }

    public function testBetweenFirst()
    {
        $this->assertSame('abc', (string) $this->stringable('abc')->betweenFirst('', 'c'));
        $this->assertSame('abc', (string) $this->stringable('abc')->betweenFirst('a', ''));
        $this->assertSame('abc', (string) $this->stringable('abc')->betweenFirst('', ''));
        $this->assertSame('b', (string) $this->stringable('abc')->betweenFirst('a', 'c'));
        $this->assertSame('b', (string) $this->stringable('dddabc')->betweenFirst('a', 'c'));
        $this->assertSame('b', (string) $this->stringable('abcddd')->betweenFirst('a', 'c'));
        $this->assertSame('b', (string) $this->stringable('dddabcddd')->betweenFirst('a', 'c'));
        $this->assertSame('nn', (string) $this->stringable('hannah')->betweenFirst('ha', 'ah'));
        $this->assertSame('a', (string) $this->stringable('[a]ab[b]')->betweenFirst('[', ']'));
        $this->assertSame('foo', (string) $this->stringable('foofoobar')->betweenFirst('foo', 'bar'));
        $this->assertSame('', (string) $this->stringable('foobarbar')->betweenFirst('foo', 'bar'));
    }

    public function testClassNamespace()
    {
        $this->assertEquals(
            Str::classNamespace($this::class),
            $this->stringable($this::class)->classNamespace()
        );
    }

    public function testExcerpt()
    {
        $this->assertSame('...is a beautiful morn...', (string) $this->stringable('This is a beautiful morning')->excerpt('beautiful', ['radius' => 5]));
    }

    public function testIs()
    {
        $this->assertTrue($this->stringable('/')->is('/'));
        $this->assertFalse($this->stringable('/')->is(' /'));
        $this->assertFalse($this->stringable('/a')->is('/'));
        $this->assertTrue($this->stringable('foo/bar/baz')->is('foo/*'));

        $this->assertTrue($this->stringable('App\Class@method')->is('*@*'));
        $this->assertTrue($this->stringable('app\Class@')->is('*@*'));
        $this->assertTrue($this->stringable('@method')->is('*@*'));

        // is case sensitive
        $this->assertFalse($this->stringable('foo/bar/baz')->is('*BAZ*'));
        $this->assertFalse($this->stringable('foo/bar/baz')->is('*FOO*'));
        $this->assertFalse($this->stringable('a')->is('A'));

        // is not case sensitive
        $this->assertTrue($this->stringable('a')->is('A', true));
        $this->assertTrue($this->stringable('foo/bar/baz')->is('*BAZ*', true));
        $this->assertTrue($this->stringable('a/')->is(['A*', 'B*'], true));
        $this->assertFalse($this->stringable('f/')->is(['A*', 'B*'], true));
        $this->assertTrue($this->stringable('foo')->is('FOO', true));
        $this->assertTrue($this->stringable('foo/bar/baz')->is('*FOO*', true));
        $this->assertTrue($this->stringable('FOO/bar')->is('foo/*', true));

        // Accepts array of patterns
        $this->assertTrue($this->stringable('a/')->is(['a*', 'b*']));
        $this->assertTrue($this->stringable('b/')->is(['a*', 'b*']));
        $this->assertFalse($this->stringable('f/')->is(['a*', 'b*']));

        // numeric values and patterns
        $this->assertFalse($this->stringable(123)->is(['a*', 'b*']));
        $this->assertTrue($this->stringable(11211)->is(['*2*', 'b*']));

        $this->assertTrue($this->stringable('blah/baz/foo')->is('*/foo'));

        // empty patterns
        $this->assertFalse($this->stringable('test')->is([]));
    }

    public function testIsAscii()
    {
        $this->assertTrue($this->stringable('Hello World!')->isAscii());
        $this->assertTrue($this->stringable('1234567890')->isAscii());
        $this->assertTrue($this->stringable('!@#$%^&*()')->isAscii());
        $this->assertFalse($this->stringable('Привет, мир!')->isAscii());
        $this->assertFalse($this->stringable('漢字')->isAscii());
        $this->assertFalse($this->stringable('áéíóú')->isAscii());
        $this->assertFalse($this->stringable('àèìòù')->isAscii());
        $this->assertFalse($this->stringable('äëïöü')->isAscii());
        $this->assertFalse($this->stringable('âêîôû')->isAscii());
        $this->assertFalse($this->stringable('ãõñ')->isAscii());
        $this->assertFalse($this->stringable('ç')->isAscii());
        $this->assertFalse($this->stringable('ß')->isAscii());
        $this->assertFalse($this->stringable('æ')->isAscii());
        $this->assertFalse($this->stringable('ø')->isAscii());
        $this->assertFalse($this->stringable('Æ')->isAscii());
        $this->assertFalse($this->stringable('Ö')->isAscii());
        $this->assertFalse($this->stringable('🙂')->isAscii());
    }

    public function testIsJson()
    {
        $this->assertTrue($this->stringable('1')->isJson());
        $this->assertTrue($this->stringable('[1,2,3]')->isJson());
        $this->assertTrue($this->stringable('[1,   2,   3]')->isJson());
        $this->assertTrue($this->stringable('{"first": "John", "last": "Doe"}')->isJson());
        $this->assertTrue($this->stringable('[{"first": "John", "last": "Doe"}, {"first": "Jane", "last": "Doe"}]')->isJson());

        $this->assertFalse($this->stringable('1,')->isJson());
        $this->assertFalse($this->stringable('[1,2,3')->isJson());
        $this->assertFalse($this->stringable('[1,   2   3]')->isJson());
        $this->assertFalse($this->stringable('{first: "John"}')->isJson());
        $this->assertFalse($this->stringable('[{first: "John"}, {first: "Jane"}]')->isJson());
        $this->assertFalse($this->stringable('')->isJson());
        $this->assertFalse($this->stringable(null)->isJson());
    }

    public function testNewLine()
    {
        $this->assertSame('Hyperf' . PHP_EOL, (string) $this->stringable('Hyperf')->newLine());
        $this->assertSame('foo' . PHP_EOL . PHP_EOL . 'bar', (string) $this->stringable('foo')->newLine(2)->append('bar'));
    }

    public function testPosition()
    {
        $this->assertSame(7, $this->stringable('Hello, World!')->position('W'));
        $this->assertSame(10, $this->stringable('This is a test string.')->position('test'));
        $this->assertSame(23, $this->stringable('This is a test string, test again.')->position('test', 15));
        $this->assertSame(0, $this->stringable('Hello, World!')->position('Hello'));
        $this->assertSame(7, $this->stringable('Hello, World!')->position('World!'));
        $this->assertSame(10, $this->stringable('This is a tEsT string.')->position('tEsT', 0, 'UTF-8'));
        $this->assertSame(7, $this->stringable('Hello, World!')->position('W', -6));
        $this->assertSame(18, $this->stringable('Äpfel, Birnen und Kirschen')->position('Kirschen', -10, 'UTF-8'));
        $this->assertSame(9, $this->stringable('@%€/=!"][$')->position('$', 0, 'UTF-8'));
        $this->assertFalse($this->stringable('Hello, World!')->position('w', 0, 'UTF-8'));
        $this->assertFalse($this->stringable('Hello, World!')->position('X', 0, 'UTF-8'));
        $this->assertFalse($this->stringable('')->position('test'));
        $this->assertFalse($this->stringable('Hello, World!')->position('X'));
    }

    public function testReverse()
    {
        $this->assertSame('FooBar', (string) $this->stringable('raBooF')->reverse());
        $this->assertSame('Teniszütő', (string) $this->stringable('őtüzsineT')->reverse());
        $this->assertSame('❤MultiByte☆', (string) $this->stringable('☆etyBitluM❤')->reverse());
    }

    public function testSquish()
    {
        $this->assertSame('words with spaces', (string) $this->stringable(' words  with   spaces ')->squish());
        $this->assertSame('words with spaces', (string) $this->stringable("words\t\twith\n\nspaces")->squish());
        $this->assertSame('words with spaces', (string) $this->stringable('
            words
            with
            spaces
        ')->squish());
    }

    public function testScan()
    {
        $this->assertSame([123456], $this->stringable('SN/123456')->scan('SN/%d')->toArray());
        $this->assertSame(['Otwell', 'Taylor'], $this->stringable('Otwell, Taylor')->scan('%[^,],%s')->toArray());
        $this->assertSame(['filename', 'jpg'], $this->stringable('filename.jpg')->scan('%[^.].%s')->toArray());
    }

    public function testSubstrReplace()
    {
        $this->assertSame('12:00', (string) $this->stringable('1200')->substrReplace(':', 2, 0));
        $this->assertSame('The Hyperf Framework', (string) $this->stringable('The Framework')->substrReplace('Hyperf ', 4, 0));
        $this->assertSame('Hyperf – The PHP Framework', (string) $this->stringable('Hyperf Framework')->substrReplace('– The PHP Framework', 7));
    }

    public function testSwap()
    {
        $this->assertSame('PHP 8 is fantastic', (string) $this->stringable('PHP is awesome')->swap([
            'PHP' => 'PHP 8',
            'awesome' => 'fantastic',
        ]));
    }

    public function testTake()
    {
        $this->assertSame('ab', (string) $this->stringable('abcdef')->take(2));
        $this->assertSame('ef', (string) $this->stringable('abcdef')->take(-2));
    }

    public function testTest()
    {
        $stringable = $this->stringable('foo bar');

        $this->assertTrue($stringable->test('/bar/'));
        $this->assertTrue($stringable->test('/foo (.*)/'));
    }

    public function testToBase64()
    {
        $this->assertSame(base64_encode(''), (string) $this->stringable('')->toBase64());
        $this->assertSame(base64_encode('foo'), (string) $this->stringable('foo')->toBase64());
    }

    public function testUnwrap()
    {
        $this->assertEquals('value', $this->stringable('"value"')->unwrap('"'));
        $this->assertEquals('bar', $this->stringable('foo-bar-baz')->unwrap('foo-', '-baz'));
        $this->assertEquals('some: "json"', $this->stringable('{some: "json"}')->unwrap('{', '}'));
    }

    public function testWrap()
    {
        $this->assertEquals('This is me!', (string) $this->stringable('is')->wrap('This ', ' me!'));
        $this->assertEquals('"value"', (string) $this->stringable('value')->wrap('"'));
    }

    public function testWhenContains()
    {
        $this->assertSame('Tony Stark', (string) $this->stringable('stark')->whenContains('tar', function ($stringable) {
            return $stringable->prepend('Tony ')->title();
        }, function ($stringable) {
            return $stringable->prepend('Arno ')->title();
        }));

        $this->assertSame('stark', (string) $this->stringable('stark')->whenContains('xxx', function ($stringable) {
            return $stringable->prepend('Tony ')->title();
        }));

        $this->assertSame('Arno Stark', (string) $this->stringable('stark')->whenContains('xxx', function ($stringable) {
            return $stringable->prepend('Tony ')->title();
        }, function ($stringable) {
            return $stringable->prepend('Arno ')->title();
        }));
    }

    public function testWhenContainsAll()
    {
        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenContainsAll(['tony', 'stark'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));

        $this->assertSame('tony stark', (string) $this->stringable('tony stark')->whenContainsAll(['xxx'], function ($stringable) {
            return $stringable->title();
        }));

        $this->assertSame('TonyStark', (string) $this->stringable('tony stark')->whenContainsAll(['tony', 'xxx'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));
    }

    public function testWhenEndsWith()
    {
        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenEndsWith('ark', function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));

        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenEndsWith(['kra', 'ark'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));

        $this->assertSame('tony stark', (string) $this->stringable('tony stark')->whenEndsWith(['xxx'], function ($stringable) {
            return $stringable->title();
        }));

        $this->assertSame('TonyStark', (string) $this->stringable('tony stark')->whenEndsWith(['tony', 'xxx'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));
    }

    public function testWhenExactly()
    {
        $this->assertSame('Nailed it...!', (string) $this->stringable('Tony Stark')->whenExactly('Tony Stark', function ($stringable) {
            return 'Nailed it...!';
        }, function ($stringable) {
            return 'Swing and a miss...!';
        }));

        $this->assertSame('Swing and a miss...!', (string) $this->stringable('Tony Stark')->whenExactly('Iron Man', function ($stringable) {
            return 'Nailed it...!';
        }, function ($stringable) {
            return 'Swing and a miss...!';
        }));

        $this->assertSame('Tony Stark', (string) $this->stringable('Tony Stark')->whenExactly('Iron Man', function ($stringable) {
            return 'Nailed it...!';
        }));
    }

    public function testWhenNotExactly()
    {
        $this->assertSame(
            'Iron Man',
            (string) $this->stringable('Tony')->whenNotExactly('Tony Stark', function ($stringable) {
                return 'Iron Man';
            })
        );

        $this->assertSame(
            'Swing and a miss...!',
            (string) $this->stringable('Tony Stark')->whenNotExactly('Tony Stark', function ($stringable) {
                return 'Iron Man';
            }, function ($stringable) {
                return 'Swing and a miss...!';
            })
        );
    }

    public function testWhenIs()
    {
        $this->assertSame('Winner: /', (string) $this->stringable('/')->whenIs('/', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }, function ($stringable) {
            return 'Try again';
        }));

        $this->assertSame('/', (string) $this->stringable('/')->whenIs(' /', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }));

        $this->assertSame('Try again', (string) $this->stringable('/')->whenIs(' /', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }, function ($stringable) {
            return 'Try again';
        }));

        $this->assertSame('Winner: foo/bar/baz', (string) $this->stringable('foo/bar/baz')->whenIs('foo/*', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }));
    }

    public function testWhenIsUuid()
    {
        $this->assertSame('Uuid: 2cdc7039-65a6-4ac7-8e5d-d554a98e7b15', (string) $this->stringable('2cdc7039-65a6-4ac7-8e5d-d554a98e7b15')->whenIsUuid(function ($stringable) {
            return $stringable->prepend('Uuid: ');
        }, function ($stringable) {
            return $stringable->prepend('Not Uuid: ');
        }));

        $this->assertSame('2cdc7039-65a6-4ac7-8e5d-d554a98', (string) $this->stringable('2cdc7039-65a6-4ac7-8e5d-d554a98')->whenIsUuid(function ($stringable) {
            return $stringable->prepend('Uuid: ');
        }));

        $this->assertSame('Not Uuid: 2cdc7039-65a6-4ac7-8e5d-d554a98', (string) $this->stringable('2cdc7039-65a6-4ac7-8e5d-d554a98')->whenIsUuid(function ($stringable) {
            return $stringable->prepend('Uuid: ');
        }, function ($stringable) {
            return $stringable->prepend('Not Uuid: ');
        }));
    }

    public function testWhenTest()
    {
        $this->assertSame('Winner: foo bar', (string) $this->stringable('foo bar')->whenTest('/bar/', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }, function ($stringable) {
            return 'Try again';
        }));

        $this->assertSame('Try again', (string) $this->stringable('foo bar')->whenTest('/link/', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }, function ($stringable) {
            return 'Try again';
        }));

        $this->assertSame('foo bar', (string) $this->stringable('foo bar')->whenTest('/link/', function ($stringable) {
            return $stringable->prepend('Winner: ');
        }));
    }

    public function testWhenStartsWith()
    {
        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenStartsWith('ton', function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));

        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenStartsWith(['ton', 'not'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));

        $this->assertSame('tony stark', (string) $this->stringable('tony stark')->whenStartsWith(['xxx'], function ($stringable) {
            return $stringable->title();
        }));

        $this->assertSame('Tony Stark', (string) $this->stringable('tony stark')->whenStartsWith(['tony', 'xxx'], function ($stringable) {
            return $stringable->title();
        }, function ($stringable) {
            return $stringable->studly();
        }));
    }

    public function testValueAndToString()
    {
        $this->assertSame('foo', $this->stringable('foo')->value());
        $this->assertSame('foo', $this->stringable('foo')->toString());
    }

    public function testReplaceMatches()
    {
        $this->assertSame('http://hyperf.io', (string) $this->stringable('https://hyperf.io')->replaceMatches('/^https:\/\//', 'http://'));
        $this->assertSame('http://hyperf.io', (string) $this->stringable('https://hyperf.io')->replaceMatches('/^https:\/\//', fn ($matches) => 'http://'));
    }

    /**
     * @param string $string
     * @return Stringable
     */
    protected function stringable($string = '')
    {
        return new Stringable($string);
    }
}
