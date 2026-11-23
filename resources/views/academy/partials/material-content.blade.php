<p class="material-eyebrow">{{ ucfirst($level) }} · Cybersecurity Academy</p>
<h1>{{ $content['title'] }}</h1>
<p class="material-introduction">{{ $content['introduction'] }}</p>

@foreach ($content['sections'] as $index => $section)
    <section class="material-section">
        <h2>{{ $index + 1 }}. {{ $section['heading'] }}</h2>
        <p class="material-copy">{!! nl2br(e($section['body'])) !!}</p>

        @if ($section['example'] !== '')
            <aside class="material-example">
                <strong>Defensive example</strong>
                <p>{!! nl2br(e($section['example'])) !!}</p>
            </aside>
        @endif

        @if ($section['code'] !== '')
            <pre class="material-code"><code>{{ $section['code'] }}</code></pre>
        @endif

        @if ($section['takeaways'] !== [])
            <ul class="material-takeaways">
                @foreach ($section['takeaways'] as $takeaway)
                    <li>{{ $takeaway }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach

<section class="material-conclusion">
    <h2>Key conclusion</h2>
    <p>{!! nl2br(e($content['conclusion'])) !!}</p>
</section>
