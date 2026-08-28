<?php

namespace Luxplus\BladeLinter\Tests;

use Luxplus\BladeLinter\Linter;
use Override;

final class SiblingBlankLinesTest extends TestCase
{
    private Linter $linter;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->linter = new Linter();
    }

    /**
     * @return list<int>
     */
    private function violationLines(string $template): array
    {
        $result = $this->linter->lint($template);
        self::assertNull($result->skippedReason);

        return array_map(fn($violation) => $violation->line, $result->violations);
    }

    public function testAdjacentOneLinersNeedNoBlankLine(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                <span>{{ $b }}</span>
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testBlankLineBetweenOneLinersIsRemoved(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                <span>{{ $b }}</span>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                <span>{{ $b }}</span>
            </div>
            EOD;

        self::assertSame([3], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testMultilineSiblingRequiresBlankLine(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @if ($cond)
                    <p>{{ $b }}</p>
                @endif
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                @if ($cond)
                    <p>{{ $b }}</p>
                @endif
            </div>
            EOD;

        self::assertSame([3], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testStoryExampleSpacing(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @if ($x)
                    <p>{{ $b }}</p>
                @endif
                <span>{{ $c }}</span>
                <span>{{ $d }}</span>
                @if ($y)
                    <p>{{ $e }}</p>
                @endif
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                @if ($x)
                    <p>{{ $b }}</p>
                @endif

                <span>{{ $c }}</span>
                <span>{{ $d }}</span>

                @if ($y)
                    <p>{{ $e }}</p>
                @endif
            </div>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testWrappedAttributesCountAsSingleLine(): void
    {
        $template = <<<'EOD'
            <div>
                <x-icon
                    name="star"
                    size="lg"
                />
                <span>{{ $a }}</span>
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testBladeCommentBetweenSiblingsIsIgnored(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                {{-- note --}}

                @if ($cond)
                    <p>{{ $b }}</p>
                @endif
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testNoBlankLinesAroundElse(): void
    {
        $template = <<<'EOD'
            @if ($cond)
                <p>{{ $a }}</p>

            @else
                <p>{{ $b }}</p>
            @endif
            EOD;
        $expected = <<<'EOD'
            @if ($cond)
                <p>{{ $a }}</p>
            @else
                <p>{{ $b }}</p>
            @endif
            EOD;

        self::assertSame([3], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testNoBlankPaddingInsideScope(): void
    {
        $template = <<<'EOD'
            <div>

                <span>{{ $a }}</span>

            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
            </div>
            EOD;

        self::assertSame([2, 4], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testScriptContentIsUntouched(): void
    {
        $template = <<<'EOD'
            <div>
                <script>
                    const a = 1;

                    const b = 2;
                </script>
                <span>{{ $a }}</span>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <script>
                    const a = 1;

                    const b = 2;
                </script>

                <span>{{ $a }}</span>
            </div>
            EOD;

        self::assertSame([7], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testMultilineSiblingsWithSingleBlankAreClean(): void
    {
        $template = <<<'EOD'
            <div>
                @if ($x)
                    <p>{{ $a }}</p>
                @endif

                @if ($y)
                    <p>{{ $b }}</p>
                @endif
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testFixIsIdempotent(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @if ($x)
                    <p>{{ $b }}</p>
                @endif
                <span>{{ $c }}</span>
            </div>
            EOD;

        $fixedOnce = $this->linter->fix($template);
        self::assertSame($fixedOnce, $this->linter->fix($fixedOnce));
        self::assertSame([], $this->violationLines($fixedOnce));
    }

    public function testConditionalWrapperDoesNotCrash(): void
    {
        $template = <<<'EOD'
            @if ($big)
                <div class="big">
            @else
                <div class="small">
            @endif
                <p>{{ $a }}</p>
            </div>
            EOD;

        $result = $this->linter->lint($template);
        self::assertNull($result->skippedReason);
        $fixed = $this->linter->fix($template);
        self::assertSame($fixed, $this->linter->fix($fixed));
    }

    public function testMasterLayoutDoctypeNeedsNoBlank(): void
    {
        $template = <<<'EOD'
            <!DOCTYPE html>
            <html lang="en">
                <head>
                    <title>{{ $title }}</title>
                </head>

                <body>
                    <p>{{ $content }}</p>
                </body>
            </html>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testBladestanSignatureConvention(): void
    {
        $template = <<<'EOD'
            @php
                /**
                 * @bladestan-signature
                 * @var string $title
                 */
            @endphp

            @extends('app.master')

            @section('content')
                <p>{{ $title }}</p>
            @endsection
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testForelseEmptyBranchGetsNoAdjacentBlanks(): void
    {
        $template = <<<'EOD'
            <ul>
                @forelse ($items as $item)
                    <li>{{ $item }}</li>

                @empty
                    <li>none</li>
                @endforelse
            </ul>
            EOD;
        $expected = <<<'EOD'
            <ul>
                @forelse ($items as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>none</li>
                @endforelse
            </ul>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testRootLevelOneLinersNeedNoBlankLine(): void
    {
        $template = <<<'EOD'
            @extends('layouts.master')
            @include('layouts.partials.default-scripts')
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testNonRootOneLinersStillNeedNoBlankLine(): void
    {
        $template = <<<'EOD'
            @section('body')
                @include('layouts.partials.header._top-bar-non-member')
                @include('layouts.partials.header._header')
            @endsection
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testGoldenLayoutStructureIsClean(): void
    {
        $template = <<<'EOD'
            @extends('layouts.master')

            @prepend('scripts')
                @vite('resources/webshop/js/shop.js')
            @endprepend

            @section('body')
                <x-webshop::header.top-bar-member />

                <header>
                    @include('layouts.partials.header._top-bar-non-member')
                    @include('layouts.partials.header._header')

                    <div class="lux-streamers">
                        <x-webshop::lux-scroll.horizontal class="lux-inner">
                            @stack('campaigns')
                            @stack('streamers')
                        </x-webshop::lux-scroll.horizontal>
                    </div>
                </header>

                <main class="show-support-button">
                    @yield('content')

                    <div class="lux-support-button">
                        <a href="{{ slug('/support') }}">
                            <x-webshop::icons.svg alt=""
                                :svg="icon('support.svg')" />
                        </a>
                    </div>
                </main>
            @endsection

            @include('layouts.partials.default-scripts')
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testMultibyteContentKeepsOffsetsAligned(): void
    {
        $template = <<<'EOD'
            <div>
                <p>
                    {{ $item->productTypeName }} ⋅ Størrelse på æske
                </p>

                <div class="lux-brand">
                    <p>{{ $item->brandName }}</p>
                </div>
                <span>{{ $a }}</span>
            </div>
            EOD;

        self::assertSame([9], $this->violationLines($template));
    }

    public function testTextRunsActAsSiblings(): void
    {
        $template = <<<'EOD'
            <div>
                Some text that
                wraps two lines
                <span>{{ $a }}</span>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                Some text that
                wraps two lines

                <span>{{ $a }}</span>
            </div>
            EOD;

        self::assertSame($expected, $this->linter->fix($template));
    }
}
