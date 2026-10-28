{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('church.name') }} blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ config('church.description') }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->url() }}</link>
                <guid isPermaLink="true">{{ $post->url() }}</guid>
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                <category>{{ $post->topic }}</category>
                <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $post->authorName() }}</dc:creator>
                <description>{{ $post->summary() }}</description>
            </item>
        @endforeach
    </channel>
</rss>
