<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Birthday forms ask for the day and month, with the year optional
 * (resources/views/components/birthday-input.blade.php). This joins the
 * three boxes into the one `birthday` date the controllers validate and
 * save. With no year, Member::NO_BIRTH_YEAR is stored.
 */
class CombineBirthday
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasAny(['birthday_day', 'birthday_month', 'birthday_year'])) {
            $request->merge(['birthday' => self::combine(
                $request->input('birthday_day'), $request->input('birthday_month'), $request->input('birthday_year'),
            )]);
        }

        return $next($request);
    }

    /**
     * A Y-m-d date, null when all three are empty, or text that fails the
     * "date" rule when the day or month is missing or the date can't exist.
     */
    public static function combine(mixed $day, mixed $month, mixed $year): ?string
    {
        $day = (int) $day;
        $month = (int) $month;
        $year = trim((string) $year);

        if (! $day && ! $month && $year === '') {
            return null;
        }
        $y = $year === '' ? Member::NO_BIRTH_YEAR : (int) $year;
        if (! $day || ! $month || ($year !== '' && ($y < 1900 || $y > (int) date('Y'))) || ! checkdate($month, $day, $y)) {
            return 'not a date';
        }

        return sprintf('%04d-%02d-%02d', $y, $month, $day);
    }
}
