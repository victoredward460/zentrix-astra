@props(['headers', 'caption'])

<div class="table-wrap">
    <table {{ $attributes->class(['data-table']) }}>
        <caption class="sr-only">{{ $caption }}</caption>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th scope="col">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
