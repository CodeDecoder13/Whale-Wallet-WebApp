{{-- Rendered on the server: most search, social and AI crawlers do not run the JavaScript app. --}}
@php
    $name = request()->route()?->getName();
    $seo = config('seo.pages')[$name] ?? null;
@endphp
@if (! $seo)
        <meta name="robots" content="noindex, nofollow">
@else
    @php
        [$title, $description] = $seo;
        $url = rtrim(config('app.url'), '/').(request()->path() === '/' ? '/' : '/'.request()->path());
        $image = rtrim(config('app.url'), '/').'/apple-touch-icon.png';
        $graph = [
            ['@type' => 'Organization', 'name' => 'Whale Wallet', 'url' => config('app.url'), 'logo' => $image, 'email' => 'dev@whale-wallet.cloud'],
            ['@type' => 'WebPage', 'name' => $title, 'description' => $description, 'url' => $url],
        ];
        if ($name === 'home') {
            $graph[] = ['@type' => 'SoftwareApplication', 'name' => 'Whale Wallet', 'applicationCategory' => 'FinanceApplication', 'operatingSystem' => 'Web', 'description' => $description, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PHP']];
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], config('seo.faq'))];
        }
    @endphp
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="index, follow, max-image-preview:large">
        <link rel="canonical" href="{{ $url }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Whale Wallet">
        <meta property="og:locale" content="en_PH">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary">
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
