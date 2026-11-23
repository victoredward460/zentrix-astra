<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $content['title'] }}</title>
    <style>
        body { max-width: 860px; margin: 0 auto; padding: 48px 28px; color: #172235; font: 16px/1.7 Arial, sans-serif; }
        h1, h2 { color: #0c2b43; line-height: 1.25; }
        h1 { margin: 8px 0 16px; font-size: 34px; }
        h2 { margin: 32px 0 8px; border-bottom: 1px solid #d5e0e9; padding-bottom: 6px; font-size: 22px; }
        p { white-space: pre-line; }
        .material-eyebrow { color: #547086; font-size: 12px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        .material-introduction { color: #40576b; font-size: 18px; }
        .material-example { margin: 18px 0; padding: 12px 16px; border-left: 4px solid #43aabc; background: #f2f7fa; }
        .material-example p { margin-bottom: 0; }
        .material-code { overflow-wrap: anywhere; padding: 14px; border-radius: 6px; color: #e4edf4; background: #172235; white-space: pre-wrap; }
        .material-takeaways { padding-left: 24px; }
        .material-conclusion { margin-top: 36px; padding-top: 8px; border-top: 2px solid #43aabc; }
        @media print { body { padding: 0; } .material-section { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <main>
        @include('academy.partials.material-content', ['content' => $content, 'level' => $level])
    </main>
</body>
</html>
