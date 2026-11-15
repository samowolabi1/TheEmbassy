<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 24px; background: #FBF8F1; font-family: Arial, sans-serif; color: #0B1526">
    <div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; padding: 28px">
        <p style="margin: 0 0 6px; font-size: 13px; font-weight: bold; letter-spacing: 0.1em; color: #8A6304">{{ strtoupper(config('church.short_name')) }}</p>
        <h1 style="margin: 0 0 12px; font-size: 22px; color: #0A1D3F">{{ $headline }}</h1>
        <p style="margin: 0 0 18px; font-size: 15px; line-height: 1.6">{{ $intro }}</p>
        @if ($figures)
            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0 0 18px">
                <tr>
                    @foreach ($figures as $label => $n)
                        <td style="padding: 10px; background: #F4EFE3; border: 4px solid #FFFFFF; border-radius: 10px; text-align: center; vertical-align: top">
                            <div style="font-size: 22px; font-weight: bold; color: #0A1D3F">{{ $n }}</div>
                            <div style="font-size: 12px; color: #56627A">{{ $label }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
        @if ($items)
            <ul style="margin: 0 0 20px; padding: 0; list-style: none">
                @foreach ($items as $item)
                    <li style="padding: 10px 0; border-top: 1px solid #ECE5D3; font-size: 15px">
                        <strong>{{ $item[0] }}</strong>
                        @if (! empty($item[1]))<br><span style="font-size: 13px; color: #56627A">{{ $item[1] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($url)
            <p style="margin: 0"><a href="{{ $url }}" style="display: inline-block; background: #E0AA1E; color: #06132B; font-weight: bold; text-decoration: none; padding: 12px 20px; border-radius: 999px">{{ $button }}</a></p>
        @endif
    </div>
</body>
</html>
