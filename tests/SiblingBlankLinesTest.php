<?php

namespace Luxplus\BladeLinter\Tests;

use Luxplus\BladeLinter\Linter;
use Luxplus\BladeLinter\Rules\SiblingBlankLines;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

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

    public function testWrappedAttributesCountAsMultiline(): void
    {
        $template = <<<'EOD'
            <div>
                <x-icon
                    name="star"
                    size="lg"
                />
                <span>{{ $a }}</span>
                <a href="/a"
                    class="link">{{ $b }}</a>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <x-icon
                    name="star"
                    size="lg"
                />

                <span>{{ $a }}</span>

                <a href="/a"
                    class="link">{{ $b }}</a>
            </div>
            EOD;

        self::assertSame([6, 7], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
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

    public function testDifferentRootLevelDirectivesNeedBlankLine(): void
    {
        $template = <<<'EOD'
            @extends('layouts.master')
            @include('layouts.partials.default-scripts')
            EOD;
        $expected = <<<'EOD'
            @extends('layouts.master')

            @include('layouts.partials.default-scripts')
            EOD;

        self::assertSame([2], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
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

    #[TestWith([4])]
    #[TestWith([6])]
    public function testMultibyteTextBeforeDirectivesKeepsLinesAligned(int $ellipses): void
    {
        $text = 'Loading data' . str_repeat('…', $ellipses);
        $template = <<<EOD
            @section('content')
                <div>
                    <p>{$text}</p>
                </div>
            @endsection

            @push('scripts')
                @vite('x.js')
            @endpush
            EOD;

        self::assertSame([], $this->violationLines($template));
        self::assertSame($template, $this->linter->fix($template));
    }

    public function testMultilineSectionAfterMultibyteTextRequiresBlankLine(): void
    {
        $template = <<<'EOD'
            <p>Loading data…………</p>

            @section('content')
                <p>{{ $a }}</p>
            @endsection
            @section('footer')
                <p>{{ $b }}</p>
            @endsection
            EOD;
        $expected = <<<'EOD'
            <p>Loading data…………</p>

            @section('content')
                <p>{{ $a }}</p>
            @endsection

            @section('footer')
                <p>{{ $b }}</p>
            @endsection
            EOD;

        self::assertSame([6], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
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

    private const string BLADESTAN_PROPS_TEMPLATE = <<<'EOD'
        @php
            /**
             * @bladestan-signature
             * @var App\ViewModels\DiscountTier $tier
             */
        @endphp

        @props(['tier'])

        <x-badges.campaign-tag cssClass="discount"
            :text="text(1572, $tier->getMaxDiscountPercentFormatted())" />
        EOD;

    private const string HEAD_LAYOUT_TEMPLATE = <<<'EOD'
        <link rel="preload"
            href="/fonts/a.woff2"
            crossorigin="anonymous">

        <link rel="preload"
            href="/fonts/b.woff2"
            crossorigin="anonymous">

        @vite('resources/css/swiper.scss')
        @vite('resources/css/app.scss')
        @vite('resources/js/app.js')

        <body class="body-app">
            @yield('content')

            @stack('scripts')
        </body>
        EOD;

    /**
     * @return list<string>
     */
    private function violationMessages(string $template): array
    {
        return array_map(fn($violation) => $violation->message, $this->linter->lint($template)->violations);
    }

    public function testPropsFollowedByWrappedComponentIsClean(): void
    {
        self::assertSame([], $this->violationLines(self::BLADESTAN_PROPS_TEMPLATE));
    }

    public function testPropsFollowedByOneLineComponentNeedsBlankLine(): void
    {
        $template = <<<'EOD'
            @php
                /**
                 * @bladestan-signature
                 * @var App\ViewModels\DiscountTier $tier
                 */
            @endphp

            @props(['tier'])
            <x-badges.campaign-tag cssClass="discount" :text="text(1572, $tier->getMaxDiscountPercentFormatted())" />
            EOD;
        $expected = <<<'EOD'
            @php
                /**
                 * @bladestan-signature
                 * @var App\ViewModels\DiscountTier $tier
                 */
            @endphp

            @props(['tier'])

            <x-badges.campaign-tag cssClass="discount" :text="text(1572, $tier->getMaxDiscountPercentFormatted())" />
            EOD;

        self::assertSame([9], $this->violationLines($template));
        self::assertSame(['Missing blank line around directive `@props`'], $this->violationMessages($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testHeadLayoutIsClean(): void
    {
        self::assertSame([], $this->violationLines(self::HEAD_LAYOUT_TEMPLATE));
    }

    public function testHeadLayoutWithoutBlankLinesIsFixed(): void
    {
        $template = self::withoutBlankLines(self::HEAD_LAYOUT_TEMPLATE);
        self::assertStringNotContainsString("\n\n", $template);

        self::assertSame(self::HEAD_LAYOUT_TEMPLATE, $this->linter->fix($template));
    }

    public function testHeadLayoutWithExtraBlankLinesIsFixed(): void
    {
        $template = <<<'EOD'
            <link rel="preload"
                href="/fonts/a.woff2"
                crossorigin="anonymous">


            <link rel="preload"
                href="/fonts/b.woff2"
                crossorigin="anonymous">

            @vite('resources/css/swiper.scss')

            @vite('resources/css/app.scss')
            @vite('resources/js/app.js')
            <body class="body-app">

                @yield('content')
                @stack('scripts')

            </body>
            EOD;

        self::assertSame(self::HEAD_LAYOUT_TEMPLATE, $this->linter->fix($template));
    }

    public function testBlankLineInsideDirectiveGroupIsReported(): void
    {
        $template = <<<'EOD'
            @vite('resources/css/app.scss')

            @vite('resources/js/app.js')
            EOD;

        self::assertSame([2], $this->violationLines($template));
        self::assertSame(['Unexpected blank line inside a group of `@vite` directives'], $this->violationMessages($template));
    }

    public function testFormDirectivesAreSeparatedFromEachOtherAndInputs(): void
    {
        $template = <<<'EOD'
            <form method="POST" action="/save">
                @csrf
                @method('PUT')
                <input type="text">
            </form>
            EOD;
        $expected = <<<'EOD'
            <form method="POST" action="/save">
                @csrf

                @method('PUT')

                <input type="text">
            </form>
            EOD;

        self::assertSame([3, 4], $this->violationLines($template));
        self::assertSame([
            'Missing blank line between directives `@csrf` and `@method`',
            'Missing blank line around directive `@method`',
        ], $this->violationMessages($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testMultilineDirectiveBreaksOutOfItsGroup(): void
    {
        $template = <<<'EOD'
            @vite('resources/css/app.scss')
            @vite([
                'resources/js/a.js',
                'resources/js/b.js',
            ])
            @vite('resources/js/app.js')
            EOD;
        $expected = <<<'EOD'
            @vite('resources/css/app.scss')

            @vite([
                'resources/js/a.js',
                'resources/js/b.js',
            ])

            @vite('resources/js/app.js')
            EOD;

        self::assertSame([2, 6], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testDirectiveNamesMustMatchExactlyToGroup(): void
    {
        $template = <<<'EOD'
            @vite('resources/js/app.js')
            @viteReactRefresh
            EOD;
        $expected = <<<'EOD'
            @vite('resources/js/app.js')

            @viteReactRefresh
            EOD;

        self::assertSame([2], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testIncludeFamilyIsSpacedLikeElements(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                @include('partials.a')

                @includeIf('partials.b')
                @includeWhen($show, 'partials.c')

                @includeUnless($hide, 'partials.d')
                @includeFirst(['partials.e', 'partials.f'])
                <span>{{ $b }}</span>
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @include('partials.a')
                @includeIf('partials.b')
                @includeWhen($show, 'partials.c')
                @includeUnless($hide, 'partials.d')
                @includeFirst(['partials.e', 'partials.f'])
                <span>{{ $b }}</span>
            </div>
            EOD;

        self::assertSame([3, 5, 8], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testIncludeNextToOtherDirectiveNeedsBlankLine(): void
    {
        $template = <<<'EOD'
            <form>
                @csrf
                @include('partials.fields')
                @method('PUT')
            </form>
            EOD;
        $expected = <<<'EOD'
            <form>
                @csrf

                @include('partials.fields')

                @method('PUT')
            </form>
            EOD;

        self::assertSame([3, 4], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testMultilineIncludeNeedsBlankLine(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @include('partials.a', [
                    'b' => $b,
                ])
                @include('partials.c')
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                @include('partials.a', [
                    'b' => $b,
                ])

                @include('partials.c')
            </div>
            EOD;

        self::assertSame([3, 6], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testElementDirectivesAreConfigurable(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span>
                @include('partials.a')
                @stack('scripts')
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <span>{{ $a }}</span>

                @include('partials.a')

                @stack('scripts')
            </div>
            EOD;
        $linter = new Linter([new SiblingBlankLines(['stack'])]);

        self::assertSame([3, 4], array_map(fn($violation) => $violation->line, $linter->lint($template)->violations));
        self::assertSame($expected, $linter->fix($template));
    }

    public function testOnlyLineLeadingBladeDirectivesCount(): void
    {
        $template = <<<'EOD'
            <div>
                <span>{{ $a }}</span> @include('partials.a')
                <button @click="open = true">Open</button>
                @@notADirective
                <span>{{ $b }}</span>
                @{{ $notAnEcho }}
                <span>{{ $c }}</span>
            </div>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testDirectivesInsideOpaqueElementsAreIgnored(): void
    {
        $template = <<<'EOD'
            <script>
                const a = @json($a);
                @if ($b)
                    init();
                @endif
            </script>
            EOD;

        self::assertSame([], $this->violationLines($template));
        self::assertSame($template, $this->linter->fix($template));
    }

    public function testDirectivesAtBranchEdgesStayUnpadded(): void
    {
        $template = <<<'EOD'
            @if ($a)
                @csrf
            @else
                @method('PUT')
                @csrf
            @endif
            EOD;
        $expected = <<<'EOD'
            @if ($a)
                @csrf
            @else
                @method('PUT')

                @csrf
            @endif
            EOD;

        self::assertSame([5], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    public function testCommentTouchingDirectiveIsSkipped(): void
    {
        $template = <<<'EOD'
            <form>
                @csrf
                {{-- token above --}}
                <input type="text">
            </form>
            EOD;

        self::assertSame([], $this->violationLines($template));
    }

    public function testMultibyteContentKeepsDirectiveLinesAligned(): void
    {
        $template = <<<'EOD'
            <div>
                <p>Størrelse på æske ⋅ Loading…………</p>
                @csrf
                <p>æøå</p> @include('partials.a')
                <span>{{ $a }}</span>
                @stack('partials.b')
                @stack('partials.c')
            </div>
            EOD;
        $expected = <<<'EOD'
            <div>
                <p>Størrelse på æske ⋅ Loading…………</p>

                @csrf

                <p>æøå</p> @include('partials.a')
                <span>{{ $a }}</span>

                @stack('partials.b')
                @stack('partials.c')
            </div>
            EOD;

        self::assertSame([3, 4, 6], $this->violationLines($template));
        self::assertSame($expected, $this->linter->fix($template));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function directiveTemplates(): iterable
    {
        yield 'bladestan props' => [self::BLADESTAN_PROPS_TEMPLATE];
        yield 'one-line component after props' => ["@props(['tier'])\n<x-badges.campaign-tag cssClass=\"discount\" :text=\"text(1572)\" />\n"];
        yield 'head layout' => [self::HEAD_LAYOUT_TEMPLATE];
        yield 'head layout without blanks' => [self::withoutBlankLines(self::HEAD_LAYOUT_TEMPLATE)];
        yield 'form' => ["<form>\n    @csrf\n    @method('PUT')\n    <input type=\"text\">\n</form>\n"];
        yield 'multi-line vite' => ["@vite('a')\n@vite([\n    'b',\n])\n@vite('c')\n"];
        yield 'include family' => ["<p>a</p>\n\n@include('a')\n\n@includeWhen(\$x, 'b')\n@csrf\n"];
        yield 'else branches' => ["@if (\$a)\n    @csrf\n\n@else\n    @method('PUT')\n    @csrf\n@endif\n"];
    }

    #[DataProvider('directiveTemplates')]
    public function testDirectiveFixIsIdempotent(string $template): void
    {
        $fixedOnce = $this->linter->fix($template);

        self::assertSame([], $this->violationLines($fixedOnce));
        self::assertSame($fixedOnce, $this->linter->fix($fixedOnce));
    }

    private static function withoutBlankLines(string $template): string
    {
        return implode("\n", array_filter(explode("\n", $template), fn(string $line): bool => mb_trim($line) !== ''));
    }
}
