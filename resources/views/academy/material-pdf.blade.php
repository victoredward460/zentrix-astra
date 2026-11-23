<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $content['title'] }}</title>
    <style>
        @page { margin: 46px 50px; }
        body { color: #172235; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.65; }
        h1, h2 { color: #0c2b43; line-height: 1.25; }
        h1 { margin: 5px 0 12px; font-size: 24px; }
        h2 { margin: 22px 0 7px; border-bottom: 1px solid #d5e0e9; padding-bottom: 4px; font-size: 15px; }
        p { white-space: pre-line; }
        .material-eyebrow { color: #547086; font-size: 8px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase; }
        .material-introduction { color: #40576b; font-size: 13px; }
        .material-example { margin: 10px 0; padding: 7px 10px; border-left: 3px solid #43aabc; background: #f2f7fa; }
        .material-example p { margin-bottom: 0; }
        .material-code { padding: 9px; color: #172235; background: #eef3f6; font-family: DejaVu Sans Mono, monospace; font-size: 9px; white-space: pre-wrap; }
        .material-takeaways { padding-left: 18px; }
        .material-section { page-break-inside: avoid; }
        .material-conclusion { margin-top: 24px; border-top: 2px solid #43aabc; padding-top: 5px; }
    </style>
</head>
<body>
    @include('academy.partials.material-content', ['content' => $content, 'level' => $level])
</body>
</html>
