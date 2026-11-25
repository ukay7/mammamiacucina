<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] }} {{ $doc['number'] }}</title>
    @include('documents.style')
    <style>
        body{background:#fff}.document{margin:0;padding:0;max-width:none}
        .credit-document-meta{margin:0 4px 10px}
        .credit-document-text{white-space:pre-wrap;overflow-wrap:break-word}
        .credit-document-details p{margin:4px 0}
        .credit-document-terms{padding:0 4px}
        .credit-document-terms h2{font-size:13px;margin:12px 0 4px;border-bottom:1px solid #dfcfb7;page-break-after:avoid}
        .credit-document-terms p{margin-bottom:10px}
        .credit-document-signature{margin-top:16px}
        .credit-document-signed-name{font-size:18px;font-weight:bold}
    </style>
</head>
<body>
<main class="document">
    @include('documents.header')
    @include('documents.credit-application-content')
    @unless($signature)
        <section class="doc-box">
            <h2>Electronic-signature consent statement</h2>
            <p class="credit-document-text">{{ $template['consent_statement'] }}</p>
        </section>
    @endunless
    <footer class="doc-footer">
        <strong>Thank you for choosing {{ rtrim($doc['brand']['name'], '.') }}.</strong>
        <p>Please include application {{ $doc['number'] }} with any enquiries.</p>
        @include('documents.registration')
    </footer>
</main>
</body>
</html>
