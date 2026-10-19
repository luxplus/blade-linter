<?php

namespace Luxplus\BladeLinter\Tests;

use Luxplus\BladeLinter\Linter;
use Luxplus\BladeLinter\Rules\ElementContentOnOwnLines;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

final class ElementContentOnOwnLinesTest extends TestCase
{
    private Linter $linter;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->linter = new Linter([new ElementContentOnOwnLines()]);
    }

    /**
     * @return list<int>
     */
    private function violationLines(string $template, ?Linter $linter = null): array
    {
        $result = ($linter ?? $this->linter)->lint($template);
        self::assertNull($result->skippedReason);

        return array_map(fn($violation) => $violation->line, $result->violations);
    }

    private static function linterWithMaxLineLength(int $maxLineLength): Linter
    {
        return new Linter([new ElementContentOnOwnLines(maxLineLength: $maxLineLength)]);
    }

    #[TestWith(['<p>{{ $something }}</p>'])]
    #[TestWith(["<div>\n    <p>{{ \\App\\Support\\Convert::toString(\$error) }}</p>\n</div>"])]
    #[TestWith(["<ul>\n    <li>Read <a href=\"#\">the terms</a>.</li>\n</ul>"])]
    #[TestWith(['<p>Total: <strong>{{ $total }}</strong></p>'])]
    #[TestWith(['<td><input type="checkbox" name="a"></td>'])]
    public function testShortTextContentStaysOnOneLine(string $template): void
    {
        self::assertSame([], $this->violationLines($template));
    }

    public function testTextContentMovesWhenTheLineIsTooLong(): void
    {
        $linter = self::linterWithMaxLineLength(40);
        $template = <<<'EOD'
            <div>
                <p>{{ $short }}</p>
                <p>{{ \App\Support\Convert::toString($error) }}</p>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <p>{{ $short }}</p>
                <p>
                    {{ \App\Support\Convert::toString($error) }}
                </p>
            </div>
            EOD;

        self::assertSame([3], $this->violationLines($template, $linter));
        self::assertSame($expected, $linter->fix($template));
    }

    public function testLineLengthCountsCharactersNotBytes(): void
    {
        $linter = self::linterWithMaxLineLength(40);

        self::assertSame([], $this->violationLines('<p>' . str_repeat('æ', 30) . '</p>', $linter));
        self::assertSame([1], $this->violationLines('<p>' . str_repeat('æ', 40) . '</p>', $linter));
    }

    public function testLineLengthIsMeasuredAfterSiblingsAreSplit(): void
    {
        $linter = self::linterWithMaxLineLength(30);
        $template = <<<'EOD'
            <tr><td>{{ $first }}</td><td>{{ $second }}</td></tr>
            EOD;
        $expected = <<<'EOD'
            <tr>
                <td>{{ $first }}</td>
                <td>{{ $second }}</td>
            </tr>
            EOD;

        self::assertSame($expected, $linter->fix($template));
    }

    public function testExpandedElementsAreClean(): void
    {
        $template = <<<'EOD'
            <div>
                <p>
                    {{ $something }}
                </p>
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    #[TestWith(['<div class="clearfix"></div>'])]
    #[TestWith(['<td> </td>'])]
    #[TestWith(["<div>\n</div>"])]
    public function testEmptyElementsStayAsTheyAre(string $template): void
    {
        self::assertSame([], $this->violationLines($template));
    }

    public function testBlockChildrenMoveOntoLinesOfTheirOwn(): void
    {
        $template = <<<'EOD'
            <table>
                <tr><th>A</th><th></th></tr>
            </table>
            EOD;
        $expected = <<<'EOD'
            <table>
                <tr>
                    <th>A</th>
                    <th></th>
                </tr>
            </table>
            EOD;

        self::assertSame([2], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testSingleWrappedElementCountsAsStructure(): void
    {
        $template = <<<'EOD'
            <ul>
                <li><a href="#">Home</a></li>
            </ul>
            EOD;
        $expected = <<<'EOD'
            <ul>
                <li>
                    <a href="#">Home</a>
                </li>
            </ul>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testInlineElementAloneInExpandedParentMovesWhenTooLong(): void
    {
        $linter = self::linterWithMaxLineLength(30);
        $template = <<<'EOD'
            <li><a href="#">{{ $category->name }}</a></li>
            EOD;
        $expected = <<<'EOD'
            <li>
                <a href="#">
                    {{ $category->name }}
                </a>
            </li>
            EOD;

        self::assertSame($expected, $linter->fix($template));
    }

    public function testInlineElementNextToTextNeverMoves(): void
    {
        $linter = self::linterWithMaxLineLength(30);
        $template = <<<'EOD'
            <ul>
                <li>Read <a href="#">the terms and conditions</a>.</li>
            </ul>
            EOD;
        $expected = <<<'EOD'
            <ul>
                <li>
                    Read <a href="#">the terms and conditions</a>.
                </li>
            </ul>
            EOD;

        self::assertSame($expected, $linter->fix($template));
    }

    #[TestWith(['<span>{{ $something_rather_long }}</span>'])]
    #[TestWith(["<div>\n    <label>{{ \$something_rather_long }}</label>\n    <input name=\"name\">\n</div>"])]
    public function testInlineElementsOutsideAnExpandedParentAreLeftAlone(string $template): void
    {
        self::assertSame([], $this->violationLines($template, self::linterWithMaxLineLength(20)));
    }

    public function testInlineChainAloneInBlockIsExpanded(): void
    {
        $template = <<<'EOD'
            <td><button type="submit"><span>Save</span></button></td>
            EOD;
        $expected = <<<'EOD'
            <td>
                <button type="submit">
                    <span>Save</span>
                </button>
            </td>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testBlockDirectivesCountAsStructureAndAreIndented(): void
    {
        $template = <<<'EOD'
            <tr>
                <td>@if ($a)<div>a</div>@else<div>b</div>@endif</td>
            </tr>
            EOD;
        $expected = <<<'EOD'
            <tr>
                <td>
                    @if ($a)
                        <div>a</div>
                    @else
                        <div>b</div>
                    @endif
                </td>
            </tr>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testComponentsAreIndentedLikeElements(): void
    {
        $template = <<<'EOD'
            <x-card><div>a</div></x-card>
            EOD;
        $expected = <<<'EOD'
            <x-card>
                <div>a</div>
            </x-card>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testContentSpanningLinesMovesOffTheTagLines(): void
    {
        $template = <<<'EOD'
            <div>
                <div
                    class="a"
                >Hello
                    world</div>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <div
                    class="a"
                >
                    Hello
                    world
                </div>
            </div>
            EOD;

        self::assertSame([4, 5], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testShortContentAfterWrappedAttributesStays(): void
    {
        $template = <<<'EOD'
            <div class="label"
                style="color: red">Active</div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testTrailingCommentStaysOnTheClosingLine(): void
    {
        $template = <<<'EOD'
            <div>
                <div class="row">
                    <p>{{ $a }}</p>
                </div> {{-- /.row --}}
                <div class="row">
                    <p>{{ $b }}</p>
                </div><!-- /.row -->
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    #[TestWith(['<div style="white-space: pre-wrap;">{{ $note }}</div>'])]
    #[TestWith(['<div class="box whitespace-pre-line"><p>{{ $note }}</p></div>'])]
    #[TestWith(['<td><textarea name="a">{{ $note }}</textarea></td>'])]
    #[TestWith(['<pre>{{ $note }}</pre>'])]
    public function testWhitespaceSensitiveContentIsLeftAlone(string $template): void
    {
        $linter = self::linterWithMaxLineLength(10);

        self::assertStringNotContainsString("\n    {{", $linter->fix($template));
        self::assertStringNotContainsString("\n    <p>", $linter->fix($template));
    }

    public function testOpeningTagCutShortByUnknownDirectiveIsLeftAlone(): void
    {
        $template = <<<'EOD'
            <div @style(['display: none' => $hidden])>{{ $x }}</div>
            EOD;

        self::assertSame([], $this->violationLines($template, self::linterWithMaxLineLength(10)));
    }

    public function testMultibyteContentKeepsOffsetsAligned(): void
    {
        $template = <<<'EOD'
            <div><p>Størrelse på æske ⋅ Loading…</p><p>æøå</p></div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <p>Størrelse på æske ⋅ Loading…</p>
                <p>æøå</p>
            </div>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testElementListsAreConfigurable(): void
    {
        $linter = new Linter([new ElementContentOnOwnLines(['section'], ['em'], ['keep'], 20)]);
        $template = <<<'EOD'
            <div><p>a</p></div>
            <section><em>{{ $something }}</em></section>
            <section class="keep"><em>c</em></section>
            EOD;
        $expected = <<<'EOD'
            <div><p>a</p></div>
            <section>
                <em>
                    {{ $something }}
                </em>
            </section>
            <section class="keep"><em>c</em></section>
            EOD;

        self::assertSame([2], $this->violationLines($template, $linter));
        self::assertSame($expected, $linter->fix($template));
    }

    public function testExpandedSiblingsGetBlankLinesFromTheDefaultRules(): void
    {
        $linter = new Linter();
        $template = <<<'EOD'
            <table>
                <tr><td>A</td><td><div>b</div></td></tr>
            </table>
            EOD;
        $expected = <<<'EOD'
            <table>
                <tr>
                    <td>A</td>

                    <td>
                        <div>b</div>
                    </td>
                </tr>
            </table>
            EOD;

        self::assertSame($expected, $linter->fix($template));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        yield 'paragraph' => ["<div>\n    <p>{{ \$a }}</p>\n</div>\n"];
        yield 'table row' => ["<tr><td>{{ \$a }}</td><td>@if (\$b)<span>b</span>@endif</td></tr>\n"];
        yield 'nested lists' => ["<ul><li><a href=\"#\">a</a><ul><li>b</li></ul></li></ul>\n"];
        yield 'loop' => ["@foreach (\$rows as \$row)<div>{{ \$row }}</div>@endforeach\n"];
        yield 'wrapped' => ["<div\n    class=\"a\"\n    id=\"b\"\n><span>a</span></div>\n"];
        yield 'long text' => ['<div><p>' . str_repeat('word ', 30) . "</p></div>\n"];
    }

    #[DataProvider('templates')]
    public function testFixIsIdempotent(string $template): void
    {
        $linter = new Linter();
        $fixedOnce = $linter->fix($template);

        self::assertSame([], $this->violationLines($fixedOnce, $linter));
        self::assertSame($fixedOnce, $linter->fix($fixedOnce));
    }
}
